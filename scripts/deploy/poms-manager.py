#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
================================================================================
 POMS SERVICE MANAGER — Launcher adaptif + Watchdog + Auto-Optimize (GUI)
================================================================================
 Satu file, tanpa dependensi eksternal (tkinter bawaan Python).

 Fitur:
   1. Launcher adaptif  : saat dibuka, otomatis menyalakan layanan yang mati
                          (poms-queue / poms-schedule / poms-poll / poms-web).
   2. Watchdog          : layanan yang mati tanpa perintah user di-restart
                          otomatis; web yang gagal diakses dibersihkan dari
                          proses `php -S` zombie lalu di-restart bila perlu.
   3. Auto-optimize     : perubahan pada .env / app / config / routes /
                          resources/views / migrations otomatis memicu
                          `php artisan config:clear` + `php artisan optimize`
                          lalu restart layanan yang memegang kode lama.
   4. Log realtime      : tab per log (service-*.log + laravel.log) untuk
                          debug/troubleshooting, baris ERROR disorot merah.

 Cara pakai (dari folder project):
     python scripts/deploy/poms-manager.py            # buka GUI
     python scripts/deploy/poms-manager.py --selftest # cek tanpa GUI
     python scripts/deploy/poms-manager.py --smoke    # uji UI sekali jalan

 Catatan hak akses:
     Kontrol layanan Windows butuh Administrator. Saat dibuka, aplikasi akan
     menawarkan elevasi (UAC). Tanpa admin, aplikasi tetap jalan sebagai
     monitor; tiap aksi layanan akan memunculkan UAC terpisah.
================================================================================
"""

import argparse
import ctypes
import os
import queue
import shutil
import subprocess
import sys
import threading
import time
import urllib.error
import urllib.request
from ctypes import Structure, byref, c_int, c_ulong, c_void_p, sizeof
from datetime import datetime
from pathlib import Path

# ------------------------------------------------------------------ konstanta
SCRIPT_DIR = Path(__file__).resolve().parent
DEFAULT_PROJECT = SCRIPT_DIR.parent.parent          # scripts/deploy -> root project

POLL_INTERVAL = 4.0      # detik antar siklus watchdog
TAIL_INTERVAL = 1.2      # detik antar penarikan log
QUIET_PERIOD = 4.0       # ke Tenang (detik) sebelum auto-optimize dieksekusi
WEB_FAIL_KILL = 3        # kegagalan web berturut sebelum bersih-bersih php -S zombie
WEB_FAIL_RESTART = 7     # kegagalan web berturut sebelum restart layanan web
CREATE_NO_WINDOW = 0x08000000

try:
    import tkinter as tk
    from tkinter import messagebox, ttk, font as tkfont
    TK_OK = True
except Exception:                                            # pragma: no cover
    TK_OK = False

# ---------------------------------------------------------------- tema emerald
C_BG        = "#071b13"   # latar jendela (hijau sangat gelap)
C_BG_CARD   = "#0c2a1e"
C_BORDER    = "#14532d"
C_HEADER1   = "#047857"
C_HEADER2   = "#022c22"
C_FG        = "#ecfdf5"
C_FG_DIM    = "#8fc4a8"
C_ACCENT    = "#059669"
C_ACCENT_HI = "#10b981"
C_DANGER    = "#b91c1c"
C_DANGER_HI = "#dc2626"
C_WARN      = "#b45309"
C_WARN_HI   = "#d97706"
C_GHOST     = "#0f3d2c"
C_GHOST_HI  = "#155e42"
C_LOG_BG    = "#05130d"
C_OK        = "#34d399"
C_ERR       = "#f87171"
C_AMBER     = "#fbbf24"

SERVICES = [
    {"name": "poms-queue",    "label": "Queue Worker",    "slug": "queue",
     "desc": "Job Telegram & antrian database"},
    {"name": "poms-schedule", "label": "Scheduler",       "slug": "schedule",
     "desc": "Rekap harian/mingguan & backup otomatis"},
    {"name": "poms-poll",     "label": "Telegram Poller", "slug": "poll",
     "desc": "Menarik pesan masuk dari bot Telegram"},
    {"name": "poms-web",      "label": "Web Server",      "slug": "web",
     "desc": "Laravel serve :8000 (LAN)"},
]

# stop order dipakai restart pasc-optimize (queue dulu: pegang kode lama)
RESTART_AFTER_CODE = ["poms-queue", "poms-poll", "poms-schedule"]
RESTART_AFTER_ENV  = ["poms-queue", "poms-poll", "poms-schedule", "poms-web"]

WATCH_FILES = [".env", "composer.json", "composer.lock"]
WATCH_DIRS  = ["app", "config", "routes", "resources/views", "resources/lang",
               "database/migrations", "database/seeders"]
WATCH_GLOBS = ["bootstrap/*.php"]
SCAN_EXCLUDE = {"node_modules", "vendor", "storage", ".git", "tests",
                "bootstrap/cache"}

# --------------------------------------------------------------- win32 helpers
DWORD = c_ulong

SC_MANAGER_CONNECT  = 0x0001
SERVICE_QUERY_STATUS = 0x0004
SERVICE_START        = 0x0010
SERVICE_STOP         = 0x0002
SERVICE_CONTROL_STOP = 0x0001

STATE_NOT_INSTALLED = 0        # sentinel internal
STATE_ERROR         = -1       # sentinel internal
SVC_RUNNING         = 4
SVC_STOPPED         = 1

STATE_TEXT = {
    1: ("Mati", C_ERR),
    2: ("Mulai\u2026", C_AMBER),
    3: ("Berhenti\u2026", C_AMBER),
    4: ("Berjalan", C_OK),
    5: ("Lanjut\u2026", C_AMBER),
    6: ("Jeda\u2026", C_AMBER),
    7: ("Dijeda", C_AMBER),
}

_is_nt = os.name == "nt"
if _is_nt:
    _adv = ctypes.WinDLL("advapi32.dll")
    _k32 = ctypes.WinDLL("kernel32.dll")
    _shell32 = ctypes.windll.shell32

    class SERVICE_STATUS(Structure):
        _fields_ = [("dwServiceType", DWORD), ("dwCurrentState", DWORD),
                    ("dwControlsAccepted", DWORD), ("dwWin32ExitCode", DWORD),
                    ("dwServiceSpecificExitCode", DWORD), ("dwCheckPoint", DWORD),
                    ("dwWaitHint", DWORD)]

    class SERVICE_STATUS_PROCESS(Structure):
        _fields_ = [("dwServiceType", DWORD), ("dwCurrentState", DWORD),
                    ("dwControlsAccepted", DWORD), ("dwWin32ExitCode", DWORD),
                    ("dwServiceSpecificExitCode", DWORD), ("dwCheckPoint", DWORD),
                    ("dwWaitHint", DWORD), ("dwProcessId", DWORD),
                    ("dwServiceFlags", DWORD)]

    _adv.OpenSCManagerW.restype = c_void_p
    _adv.OpenSCManagerW.argtypes = [c_void_p, c_void_p, DWORD]
    _adv.OpenServiceW.restype = c_void_p
    _adv.OpenServiceW.argtypes = [c_void_p, c_void_p, DWORD]
    _adv.StartServiceW.argtypes = [c_void_p, DWORD, c_void_p]
    _adv.ControlService.argtypes = [c_void_p, DWORD, c_void_p]
    _adv.QueryServiceStatusEx.argtypes = [c_void_p, c_int, c_void_p, DWORD, c_void_p]
    _adv.CloseServiceHandle.argtypes = [c_void_p]


class ServiceError(Exception):
    def __init__(self, msg, winerror=0):
        super().__init__(msg)
        self.winerror = winerror


def is_admin() -> bool:
    """True bila proses berjalan dengan hak Administrator."""
    if not _is_nt:
        return False
    try:
        return bool(_shell32.IsUserAnAdmin())
    except Exception:
        return False


def query_service(name: str):
    """Return (state, pid). state = kode Win32 1..7, atau sentinel."""
    if not _is_nt:
        return (STATE_ERROR, 0)
    scm = _adv.OpenSCManagerW(None, None, SC_MANAGER_CONNECT)
    if not scm:
        return (STATE_ERROR, 0)
    h = None
    try:
        h = _adv.OpenServiceW(scm, name, SERVICE_QUERY_STATUS)
        if not h:
            return (STATE_NOT_INSTALLED, 0) if _k32.GetLastError() == 1060 \
                else (STATE_ERROR, 0)
        ssp = SERVICE_STATUS_PROCESS()
        needed = DWORD(0)
        if not _adv.QueryServiceStatusEx(h, 0, byref(ssp), sizeof(ssp),
                                         byref(needed)):
            return (STATE_ERROR, 0)
        return (ssp.dwCurrentState, ssp.dwProcessId)
    finally:
        if h:
            _adv.CloseServiceHandle(h)
        _adv.CloseServiceHandle(scm)


def svc_start(name: str) -> None:
    if not _is_nt:
        raise ServiceError("Hanya untuk Windows")
    scm = _adv.OpenSCManagerW(None, None, SC_MANAGER_CONNECT)
    if not scm:
        raise ServiceError("Gagal membuka Service Control Manager", 0)
    h = None
    try:
        h = _adv.OpenServiceW(scm, name, SERVICE_START)
        if not h:
            err = _k32.GetLastError()
            if err == 1060:
                raise ServiceError("layanan belum terpasang", err)
            if err == 5:
                raise ServiceError("akses ditolak (butuh Administrator)", err)
            raise ServiceError(f"gagal membuka layanan (error {err})", err)
        if not _adv.StartServiceW(h, 0, None):
            err = _k32.GetLastError()
            if err == 1056:                     # sudah berjalan
                return
            if err == 5:
                raise ServiceError("akses ditolak (butuh Administrator)", err)
            raise ServiceError(f"gagal start (error {err})", err)
    finally:
        if h:
            _adv.CloseServiceHandle(h)
        _adv.CloseServiceHandle(scm)


def svc_stop(name: str) -> None:
    if not _is_nt:
        raise ServiceError("Hanya untuk Windows")
    scm = _adv.OpenSCManagerW(None, None, SC_MANAGER_CONNECT)
    if not scm:
        raise ServiceError("Gagal membuka Service Control Manager", 0)
    h = None
    try:
        h = _adv.OpenServiceW(scm, name, SERVICE_STOP)
        if not h:
            err = _k32.GetLastError()
            if err == 1060:
                raise ServiceError("layanan belum terpasang", err)
            if err == 5:
                raise ServiceError("akses ditolak (butuh Administrator)", err)
            raise ServiceError(f"gagal membuka layanan (error {err})", err)
        st = SERVICE_STATUS()
        if not _adv.ControlService(h, SERVICE_CONTROL_STOP, byref(st)):
            err = _k32.GetLastError()
            if err == 1062:                     # memang sudah mati
                return
            if err == 5:
                raise ServiceError("akses ditolak (butuh Administrator)", err)
            raise ServiceError(f"gagal stop (error {err})", err)
    finally:
        if h:
            _adv.CloseServiceHandle(h)
        _adv.CloseServiceHandle(scm)


def wait_state(name: str, targets, timeout: float):
    """Tunggu layanan mencapai salah satu state target; return state akhir."""
    deadline = time.time() + timeout
    st = STATE_ERROR
    while time.time() < deadline:
        st, _ = query_service(name)
        if st in targets:
            return st
        time.sleep(0.4)
    st, _ = query_service(name)
    return st


def shell_runas(exe: str, params, cwd, show=0) -> bool:
    """Jalankan exe dengan elevasi UAC. Return True bila berhasil diluncurkan."""
    if not _is_nt:
        return False
    rc = _shell32.ShellExecuteW(None, "runas", exe,
                                params if params is not None else None,
                                str(cwd) if cwd else None, show)
    return rc > 32


def kill_orphan_php_servers():
    """Bunuh proses `php -S` yatim (parent-nya sudah mati) yang menahan port."""
    if not _is_nt:
        return []
    ps = ("$ErrorActionPreference='SilentlyContinue';"
          "$procs = Get-CimInstance Win32_Process | Where-Object { "
          "$_.Name -eq 'php.exe' -and $_.CommandLine -like '*-S *' };"
          "foreach ($p in $procs) { "
          "$parent = Get-Process -Id $p.ParentProcessId -ErrorAction SilentlyContinue;"
          "if (-not $parent) { Stop-Process -Id $p.ProcessId -Force "
          "-ErrorAction SilentlyContinue; Write-Output ('KILLED ' + $p.ProcessId) } }")
    try:
        r = subprocess.run(["powershell", "-NoProfile", "-Command", ps],
                           capture_output=True, text=True, errors="replace",
                           timeout=25, creationflags=CREATE_NO_WINDOW)
        return [int(l.split()[1]) for l in (r.stdout or "").splitlines()
                if l.strip().startswith("KILLED")]
    except Exception:
        return []


# --------------------------------------------------------------- helper umum
def find_php():
    """Cari php.exe: env POMS_PHP -> path instalasi PHP -> PATH."""
    cand = [os.environ.get("POMS_PHP"),
            r"C:\Users\Adjira\AppData\Local\Programs\PHP\current\php.exe",
            shutil.which("php")]
    for c in cand:
        if c and Path(c).exists():
            return c
    return None


def find_nssm(project: Path):
    p = Path(project) / "scripts" / "deploy" / "nssm.exe"
    if p.exists():
        return p
    w = shutil.which("nssm")
    return Path(w) if w else None


def read_env_safe(project: Path) -> dict:
    """Baca kunci .env yang aman ditampilkan (tanpa rahasia)."""
    info = {}
    p = Path(project) / ".env"
    try:
        for line in p.read_text(encoding="utf-8", errors="replace").splitlines():
            line = line.strip()
            if line.startswith("APP_URL="):
                info["APP_URL"] = line.split("=", 1)[1].strip().strip('"\'')
            elif line.startswith("APP_ENV="):
                info["APP_ENV"] = line.split("=", 1)[1].strip().strip('"\'')
            elif line.startswith("APP_NAME="):
                info["APP_NAME"] = line.split("=", 1)[1].strip().strip('"\'')
    except OSError:
        pass
    return info


def log_paths(project: Path):
    base = Path(project) / "storage" / "logs"
    return [("queue", base / "service-queue.log"),
            ("schedule", base / "service-schedule.log"),
            ("poll", base / "service-poll.log"),
            ("web", base / "service-web.log"),
            ("laravel", base / "laravel.log")]


def build_fingerprint(project: Path) -> dict:
    """Kumpulan {path: (mtime_ns, size)} untuk deteksi perubahan kode."""
    root = Path(project)
    fp = {}
    for rel in WATCH_FILES:
        p = root / rel
        try:
            st = p.stat()
            fp[str(p)] = (st.st_mtime_ns, st.st_size)
        except OSError:
            pass
    for d in WATCH_DIRS:
        base = root / d
        if not base.exists():
            continue
        for dirpath, dirnames, filenames in os.walk(base):
            dirnames[:] = [x for x in dirnames
                           if x not in SCAN_EXCLUDE and not x.startswith(".")]
            for fn in filenames:
                if fn.startswith(".") or fn.endswith((".pyc", ".orig")):
                    continue
                p = Path(dirpath) / fn
                try:
                    st = p.stat()
                except OSError:
                    continue
                fp[str(p)] = (st.st_mtime_ns, st.st_size)
    for pat in WATCH_GLOBS:
        for p in root.glob(pat):
            if p.is_file():
                try:
                    st = p.stat()
                    fp[str(p)] = (st.st_mtime_ns, st.st_size)
                except OSError:
                    pass
    return fp


_OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({}))


def http_probe(url: str, timeout: float = 3.0):
    """Cek HTTP web. Return (server_merespons, kode_http, ms, pesan_error)."""
    t0 = time.perf_counter()
    req = urllib.request.Request(
        url, headers={"User-Agent": "POMS-Manager/1.0", "Cache-Control": "no-cache"})
    try:
        with _OPENER.open(req, timeout=timeout) as resp:
            code = getattr(resp, "status", None) or resp.getcode()
            resp.read(256)
            return True, code, int((time.perf_counter() - t0) * 1000), None
    except urllib.error.HTTPError as e:
        return True, e.code, int((time.perf_counter() - t0) * 1000), None
    except Exception as e:                                   # noqa: BLE001
        return False, None, int((time.perf_counter() - t0) * 1000), \
            f"{type(e).__name__}: {e}"


class LogFollower:
    """Tail satu file log: kirim baris baru sejak bacaan terakhir."""

    def __init__(self, path: Path, init_bytes=262144, init_lines=120):
        self.path = Path(path)
        self.offset = -1
        self.init_bytes = init_bytes
        self.init_lines = init_lines
        self.remainder = ""

    def poll(self):
        """Return list baris baru (atau None bila tidak ada)."""
        try:
            size = self.path.stat().st_size
        except OSError:
            return None
        if self.offset < 0 or size < self.offset:    # awal / rotasi / truncate
            self.remainder = ""
            start = max(0, size - self.init_bytes)
            try:
                with open(self.path, "rb") as f:
                    f.seek(start)
                    data = f.read()
            except OSError:
                return None
            self.offset = size
            lines = data.decode("utf-8", "replace").splitlines()
            return lines[-self.init_lines:] if lines else None
        if size == self.offset:
            return None
        try:
            with open(self.path, "rb") as f:
                f.seek(self.offset)
                data = f.read()
        except OSError:
            return None
        self.offset = size
        text = self.remainder + data.decode("utf-8", "replace")
        if text.endswith("\n"):
            self.remainder = ""
            return text.splitlines() or None
        idx = text.rfind("\n")
        if idx == -1:
            self.remainder = text
            return None
        self.remainder = text[idx + 1:]
        return text[:idx].splitlines() or None


# ------------------------------------------------------------------- GUI App
class App:
    def __init__(self, root, opts):
        self.root = root
        self.opts = opts
        self.root_dir = Path(opts.project)
        self.php = find_php()
        self.nssm = find_nssm(self.root_dir)
        self.uiq = queue.Queue()
        self.stop_evt = threading.Event()
        self._closing = False
        self._relaunched = False
        self._mutex = None

        # state layanan
        self.user_stopped = {s["name"]: False for s in SERVICES}
        self.stop_streak = {s["name"]: 0 for s in SERVICES}
        self.last_auto = {s["name"]: 0.0 for s in SERVICES}
        self.busy = set()
        self.busy_lock = threading.Lock()

        # state optimize / web
        self.optimizing = False
        self.opt_lock = threading.Lock()
        self.pending_since = None
        self.fp = build_fingerprint(self.root_dir)
        self.web_fail = 0
        self.web_kill_at = None
        self.last_web_fix = 0.0

        # flag checkbox (diakses lintas-thread sebagai bool python)
        self.flags = {"auto_restart": True, "auto_optimize": True,
                      "web_watch": True, "autoscroll": True}

        env = read_env_safe(self.root_dir)
        self.app_url = env.get("APP_URL", "http://localhost:8000")
        self.app_env = env.get("APP_ENV", "?")
        self.app_name = env.get("APP_NAME", "POMS")

        self.cards = {}
        self.log_texts = {}
        self.followers = {}

        if not self.opts.smoke:
            self._mutex = _k32.CreateMutexW(None, False,
                                            "POMS-Service-Manager-Mutex")
            if _k32.GetLastError() == 183:               # ERROR_ALREADY_EXISTS
                messagebox.showwarning(
                    "POMS Service Manager",
                    "Aplikasi sudah berjalan di jendela lain.")
                raise SystemExit(0)

        for name in ("TkDefaultFont", "TkTextFont", "TkMenuFont",
                     "TkHeadingFont"):
            try:
                tkfont.nametofont(name).configure(family="Segoe UI")
            except Exception:
                pass

        self.root.title(f"{self.app_name} Service Manager")
        self.root.geometry("1150x780")
        self.root.minsize(980, 640)
        self.root.configure(bg=C_BG)
        self.root.protocol("WM_DELETE_WINDOW", self._on_close)

        self._build_style()
        self._build_header()
        self._build_banner_zone()
        self._build_top()
        self._build_logs()

        if not is_admin() and not self.opts.smoke:
            self._show_banner(
                "monitor",
                "Mode monitor \u2014 kontrol layanan butuh hak Administrator.",
                "\U0001F512 Jalankan sebagai Administrator",
                self._elevate_relaunch)
            if messagebox.askyesno(
                    "Administrator",
                    "Kontrol layanan Windows butuh hak Administrator.\n\n"
                    "Jalankan ulang aplikasi ini sebagai Administrator?\n"
                    "(Pilih Tidak untuk mode monitor saja)"):
                if self._elevate_relaunch():
                    self._relaunched = True
                    return

        self._act("\U0001F33F POMS Service Manager siap "
                  f"(project: {self.root_dir})", "ok")
        if not self.php:
            self._act("PHP tidak ditemukan \u2014 set variabel POMS_PHP ke "
                      "php.exe", "err")
        if not is_admin():
            self._act("Berjalan tanpa Administrator: aksi layanan akan "
                      "memunculkan UAC per klik; watchdog auto-restart "
                      "tidak aktif.", "warn")

        if not self.opts.smoke:
            threading.Thread(target=self._poller, daemon=True).start()
            threading.Thread(target=self._tailer, daemon=True).start()
            self.root.after(1000, self._tick_clock)
            self.root.after(80, self._drain)

    # -------------------------------------------------------------- helpers UI
    def run(self) -> int:
        if self._relaunched:
            try:
                self.root.destroy()
            except Exception:
                pass
            return 0
        if self.opts.smoke:
            self._poll_cycle(first=True)
            self._drain_once()
            self.root.update_idletasks()
            print("SMOKE OK - UI dibangun & 1 siklus polling sukses")
            self.root.destroy()
            return 0
        self.root.mainloop()
        return 0

    def _act(self, msg, level="info"):
        """Log aktivitas (thread-safe) ke tab Aktivitas."""
        ts = datetime.now().strftime("%H:%M:%S")
        tag = {"ok": "ok", "err": "err", "warn": "warn"}.get(level)
        self.uiq.put(lambda m=msg, t=tag, s=ts:
                     self._append_log("act", f"[{s}] {m}", t))

    def _set_busy(self, msg):
        self.uiq.put(lambda m=msg: self.lbl_busy.configure(
            text=m, fg=C_AMBER if m not in ("", "Idle") else C_FG_DIM))

    def _refresh_card(self, name):
        st, pid = query_service(name)
        self.uiq.put(lambda n=name, s=st, p=pid: self._set_card(n, s, p))
        return st

    def _set_card(self, name, state, pid):
        w = self.cards.get(name)
        if w is None:
            return
        txt, col = state_view(state)
        try:
            w["state"].configure(text=txt, fg=col)
            w["dot"].configure(fg=col)
            w["pid"].configure(text=f"PID {pid}" if pid else "PID \u2013")
        except tk.TclError:
            pass

    # ----------------------------------------------------------- building UI
    def _build_style(self):
        style = ttk.Style(self.root)
        try:
            style.theme_use("clam")
        except Exception:
            pass
        style.configure(".", background=C_BG, foreground=C_FG,
                        font=("Segoe UI", 9))
        style.configure("TNotebook", background=C_BG, borderwidth=0,
                        tabmargins=[0, 0, 0, 0])
        style.configure("TNotebook.Tab", background=C_GHOST,
                        foreground=C_FG_DIM, padding=(16, 8),
                        font=("Segoe UI", 9, "bold"))
        style.map("TNotebook.Tab",
                  background=[("selected", C_ACCENT)],
                  foreground=[("selected", "#ffffff")])

    @staticmethod
    def _hex_lerp(a: str, b: str, t: float) -> str:
        av = [int(a[i:i + 2], 16) for i in (1, 3, 5)]
        bv = [int(b[i:i + 2], 16) for i in (1, 3, 5)]
        return "#%02x%02x%02x" % tuple(
            int(x + (y - x) * t) for x, y in zip(av, bv))

    def _build_header(self):
        self.header = tk.Canvas(self.root, height=64, highlightthickness=0,
                                bg=C_HEADER2)
        self.header.pack(fill="x")
        self.header.bind("<Configure>", lambda e: self._paint_header())
        self._paint_header()

    def _paint_header(self):
        c = self.header
        c.delete("all")
        w = max(c.winfo_width(), 800)
        h = 64
        steps = 40
        for i in range(steps):
            col = self._hex_lerp(C_HEADER1, C_HEADER2, i / (steps - 1))
            y0 = int(h * i / steps)
            y1 = int(h * (i + 1) / steps)
            c.create_line(0, y0, w, y1, fill=col, width=max(2, y1 - y0 + 1))
        c.create_text(18, 20, anchor="w", text="\U0001F334 POMS Service Manager",
                      fill="#ffffff", font=("Segoe UI", 15, "bold"))
        c.create_text(19, 44, anchor="w",
                      text="launcher adaptif \u00b7 watchdog \u00b7 auto-optimize",
                      fill="#a7f3d0", font=("Segoe UI", 8))
        # badge kanan: env + admin
        admin = is_admin()
        c.create_text(w - 150, 24, anchor="e", text=f"APP_ENV: {self.app_env}",
                      fill="#d1fae5", font=("Segoe UI", 9))
        c.create_text(w - 150, 42, anchor="e",
                      text="ADMIN \u2713" if admin else "MODE MONITOR",
                      fill="#a7f3d0" if admin else C_AMBER,
                      font=("Segoe UI", 9, "bold"))
        self._hclock = c.create_text(w - 20, 33, anchor="e",
                                     text=time.strftime("%H:%M:%S"),
                                     fill="#ffffff", font=("Segoe UI", 11, "bold"))

    def _tick_clock(self):
        if self._closing:
            return
        try:
            self.header.itemconfigure(self._hclock,
                                      text=time.strftime("%H:%M:%S"))
        except Exception:
            pass
        self.root.after(1000, self._tick_clock)

    def _build_banner_zone(self):
        self.banner_zone = tk.Frame(self.root, bg=C_BG)
        self.banner_zone.pack(fill="x")
        self._banner = None

    def _show_banner(self, kind, text, btn_text, cmd):
        self._hide_banner()
        bg = "#7c2d12" if kind == "monitor" else "#7f1d1d"
        fr = tk.Frame(self.banner_zone, bg=bg)
        fr.pack(fill="x")
        tk.Label(fr, text=text, bg=bg, fg="#fef3c7" if kind == "monitor"
                 else "#fecaca", font=("Segoe UI", 9, "bold"),
                 padx=16, pady=6).pack(side="left")
        tk.Button(fr, text=btn_text, command=cmd, bg="#1f2937", fg="#f9fafb",
                  activebackground="#374151", activeforeground="#ffffff",
                  relief="flat", bd=0, cursor="hand2",
                  font=("Segoe UI", 8, "bold"), padx=12, pady=4
                  ).pack(side="right", padx=10, pady=5)
        self._banner = fr

    def _hide_banner(self):
        if self._banner is not None:
            try:
                self._banner.destroy()
            except Exception:
                pass
            self._banner = None

    def _build_top(self):
        top = tk.Frame(self.root, bg=C_BG)
        top.pack(fill="x", padx=14, pady=(10, 2))

        # ---- strip status
        strip = tk.Frame(top, bg=C_BG)
        strip.pack(fill="x", pady=(0, 8))
        self.chip_web = tk.Label(strip, text="Web: memeriksa\u2026", bg=C_GHOST,
                                 fg=C_FG_DIM, font=("Segoe UI", 9, "bold"),
                                 padx=12, pady=5)
        self.chip_web.pack(side="left")
        tk.Label(strip, text=f"  APP_URL: {self.app_url}", bg=C_BG,
                 fg=C_FG_DIM, font=("Segoe UI", 9)).pack(side="left")
        self.lbl_updated = tk.Label(strip, text="update: -", bg=C_BG,
                                    fg=C_FG_DIM, font=("Segoe UI", 8))
        self.lbl_updated.pack(side="left", padx=12)
        self.lbl_busy = tk.Label(strip, text="Idle", bg=C_BG, fg=C_FG_DIM,
                                 font=("Segoe UI", 9, "bold"))
        self.lbl_busy.pack(side="right")

        # ---- grid kartu layanan 2x2
        grid = tk.Frame(top, bg=C_BG)
        grid.pack(fill="x")
        for i in range(2):
            grid.columnconfigure(i, weight=1)
        for i, spec in enumerate(SERVICES):
            card = self._build_card(grid, spec)
            card.grid(row=i // 2, column=i % 2, sticky="nsew", padx=4, pady=4)

        # ---- action bar
        bar = tk.Frame(top, bg=C_BG)
        bar.pack(fill="x", pady=(8, 0))
        self._mkbtn(bar, "\u25B6  Mulai Semua",
                    lambda: self._spawn_all("start"), "primary")
        self._mkbtn(bar, "\u25A0  Stop Semua",
                    lambda: self._spawn_all("stop"), "danger")
        self._mkbtn(bar, "\u21BB  Restart Semua",
                    lambda: self._spawn_all("restart"), "warn")
        self.btn_opt = self._mkbtn(bar, "\u26A1  Optimize Sekarang",
                                   self._spawn_optimize, "primary")
        tk.Frame(bar, bg=C_BORDER, width=2).pack(side="left", fill="y",
                                                padx=12, pady=2)
        self._add_check(bar, "Auto-restart watchdog", "auto_restart")
        self._add_check(bar, "Auto-optimize (kode/.env)", "auto_optimize")
        self._add_check(bar, "Pantau web & bersihkan php zombie", "web_watch")

    def _build_card(self, parent, spec):
        name = spec["name"]
        card = tk.Frame(parent, bg=C_BG_CARD, highlightthickness=1,
                        highlightbackground=C_BORDER)
        card.columnconfigure(1, weight=1)
        dot = tk.Label(card, text="\u25CF", font=("Segoe UI", 16),
                       bg=C_BG_CARD, fg=C_FG_DIM)
        dot.grid(row=0, column=0, rowspan=2, padx=(12, 6), pady=10)
        tk.Label(card, text=spec["label"], font=("Segoe UI", 11, "bold"),
                 bg=C_BG_CARD, fg=C_FG).grid(row=0, column=1, sticky="w")
        tk.Label(card, text=spec["desc"], font=("Segoe UI", 8),
                 bg=C_BG_CARD, fg=C_FG_DIM).grid(row=1, column=1, sticky="w")
        state_lbl = tk.Label(card, text="Memeriksa\u2026",
                             font=("Segoe UI", 9, "bold"), bg=C_BG_CARD,
                             fg=C_FG_DIM, width=15, anchor="e")
        state_lbl.grid(row=0, column=2, sticky="e", padx=(6, 2))
        pid_lbl = tk.Label(card, text="PID \u2013", font=("Segoe UI", 8),
                           bg=C_BG_CARD, fg=C_FG_DIM, width=15, anchor="e")
        pid_lbl.grid(row=1, column=2, sticky="e", padx=(6, 2))
        btns = tk.Frame(card, bg=C_BG_CARD)
        btns.grid(row=0, column=3, rowspan=2, padx=(6, 12), pady=8)
        self._mkbtn(btns, "\u25B6", lambda n=name: self._spawn_action(n, "start"),
                    "primary", small=True)
        self._mkbtn(btns, "\u25A0", lambda n=name: self._spawn_action(n, "stop"),
                    "danger", small=True)
        self._mkbtn(btns, "\u21BB", lambda n=name: self._spawn_action(n, "restart"),
                    "warn", small=True)
        self.cards[name] = {"dot": dot, "state": state_lbl, "pid": pid_lbl}
        return card

    @staticmethod
    def _mkbtn(parent, text, cmd, kind="primary", small=False):
        pal = {"primary": (C_ACCENT, C_ACCENT_HI),
               "danger": (C_DANGER, C_DANGER_HI),
               "warn": (C_WARN, C_WARN_HI),
               "ghost": (C_GHOST, C_GHOST_HI)}
        base, hi = pal[kind]
        b = tk.Button(parent, text=text, command=cmd, bg=base, fg=C_FG,
                      activebackground=hi, activeforeground="#ffffff",
                      relief="flat", bd=0, cursor="hand2",
                      font=("Segoe UI", 8 if small else 9, "bold"),
                      padx=10 if small else 14, pady=4 if small else 7)
        b.pack(side="left", padx=3)
        b.bind("<Enter>", lambda e, w=b, c=hi: w.configure(bg=c))
        b.bind("<Leave>", lambda e, w=b, c=base: w.configure(bg=c))
        return b

    def _add_check(self, parent, text, key):
        cb = tk.Checkbutton(parent, text=text, command=lambda: self._flip(key),
                            bg=C_BG, fg=C_FG_DIM, activebackground=C_BG,
                            activeforeground=C_FG, selectcolor=C_BG_CARD,
                            highlightthickness=0, bd=0, cursor="hand2",
                            font=("Segoe UI", 8))
        cb.pack(side="left", padx=(10, 0))
        cb.select()

    def _flip(self, key):
        self.flags[key] = not self.flags.get(key, True)

    def _build_logs(self):
        holder = tk.Frame(self.root, bg=C_BG)
        holder.pack(fill="both", expand=True, padx=14, pady=(6, 12))
        tool = tk.Frame(holder, bg=C_BG)
        tool.pack(fill="x")
        tk.Label(tool, text="\U0001FAB2 Log realtime", bg=C_BG, fg=C_FG,
                 font=("Segoe UI", 9, "bold")).pack(side="left")
        self._mkbtn(tool, "Bersihkan", self._clear_active_tab, "ghost",
                    small=True)
        self._mkbtn(tool, "Buka folder log",
                    lambda: self._open_log_dir(), "ghost", small=True)
        cb = tk.Checkbutton(tool, text="Auto-scroll",
                            command=lambda: self._flip("autoscroll"),
                            bg=C_BG, fg=C_FG_DIM, activebackground=C_BG,
                            activeforeground=C_FG, selectcolor=C_BG_CARD,
                            highlightthickness=0, bd=0, cursor="hand2",
                            font=("Segoe UI", 8))
        cb.pack(side="right")
        cb.select()

        self.nb = ttk.Notebook(holder)
        self.nb.pack(fill="both", expand=True, pady=(4, 0))

        tabs = [("act", "Aktivitas")] + [(slug, label) for slug, label in [
            ("queue", "Queue"), ("schedule", "Scheduler"), ("poll", "Poller"),
            ("web", "Web"), ("laravel", "Laravel")]]
        for slug, label in tabs:
            frame = tk.Frame(self.nb, bg=C_LOG_BG)
            self.nb.add(frame, text=f" {label} ")
            txt = tk.Text(frame, bg=C_LOG_BG, fg="#c9e8d6",
                          insertbackground=C_FG, font=("Consolas", 9),
                          wrap="none", state="disabled", relief="flat",
                          padx=10, pady=8, spacing1=1, undo=False)
            scroll = ttk.Scrollbar(frame, command=txt.yview)
            txt.configure(yscrollcommand=scroll.set)
            scroll.pack(side="right", fill="y")
            txt.pack(side="left", fill="both", expand=True)
            txt.tag_configure("err", foreground=C_ERR)
            txt.tag_configure("warn", foreground=C_AMBER)
            txt.tag_configure("ok", foreground=C_OK)
            txt.tag_configure("dim", foreground="#7a9e8b")
            self.log_texts[slug] = txt

        for slug, path in log_paths(self.root_dir):
            self.followers[slug] = LogFollower(path)

    # ----------------------------------------------------------- log helpers
    @staticmethod
    def _line_tag(line: str):
        low = line.lower()
        if ("production.error" in low or "exception" in low or "[error]" in low
                or low.strip().startswith("error") or " error:" in low
                or "failed" in low and "queue" in low):
            return "err"
        if "warning" in low:
            return "warn"
        return None

    def _append_log(self, slug, line, tag=None):
        txt = self.log_texts.get(slug)
        if txt is None:
            return
        try:
            txt.configure(state="normal")
            if tag is None:
                tag = self._line_tag(line)
            txt.insert("end", line + "\n", (tag,) if tag else ())
            end = int(txt.index("end-1c").split(".")[0])
            if end > 1200:
                txt.delete("1.0", f"{end - 1200}.0")
            if self.flags["autoscroll"]:
                txt.see("end")
            txt.configure(state="disabled")
        except tk.TclError:
            pass

    def _clear_active_tab(self):
        slug = self._active_slug()
        txt = self.log_texts.get(slug)
        if txt:
            txt.configure(state="normal")
            txt.delete("1.0", "end")
            txt.configure(state="disabled")

    def _active_slug(self):
        idx = self.nb.index(self.nb.select())
        return ["act", "queue", "schedule", "poll", "web", "laravel"][idx]

    def _open_log_dir(self):
        p = self.root_dir / "storage" / "logs"
        if p.exists():
            os.startfile(str(p))                                  # noqa: S606

    # ------------------------------------------------------------ uiq draining
    def _drain_once(self):
        try:
            while True:
                fn = self.uiq.get_nowait()
                try:
                    fn()
                except tk.TclError:
                    pass
        except queue.Empty:
            pass

    def _drain(self):
        if self._closing:
            return
        self._drain_once()
        self.root.after(80, self._drain)

    # --------------------------------------------------------- aksi layanan
    def _spawn_action(self, name, action):
        threading.Thread(target=self._do_service_action, daemon=True,
                         args=(name, action, "manual")).start()

    def _spawn_all(self, action):
        threading.Thread(target=self._do_all, daemon=True,
                         args=(action,)).start()

    def _spawn_optimize(self):
        threading.Thread(target=self._optimize_job, daemon=True,
                         args=(None, "manual")).start()

    def _do_all(self, action):
        if action == "stop":
            for s in SERVICES:                     # cegah watchdog ikut campur
                self.user_stopped[s["name"]] = True
        else:
            for s in SERVICES:
                self.user_stopped[s["name"]] = False
        self._act(f"Eksekusi '{action}' untuk semua layanan\u2026", "info")
        for s in SERVICES:
            if self.stop_evt.is_set():
                return
            self._do_service_action(s["name"], action, via="batch")

    def _do_service_action(self, name, action, via="manual"):
        with self.busy_lock:
            if name in self.busy:
                self._act(f"{name}: aksi lain sedang berjalan, lewati.", "warn")
                return
            self.busy.add(name)
        verb = {"start": "Menyalakan", "stop": "Menghentikan",
                "restart": "Me-restart"}[action]
        self._set_busy(f"{verb} {name}\u2026")
        self._act(f"{verb} {name}\u2026", "info")
        try:
            if action == "stop":
                self.user_stopped[name] = True
            else:
                self.user_stopped[name] = False
            ok, msg = self._ctl(name, action)
            self._act(("\u2705 " if ok else "\u274C ") + f"{name}: {msg}",
                      "ok" if ok else "err")
            self._refresh_card(name)
        except Exception as e:                                   # noqa: BLE001
            self._act(f"\u274C {name}: kesalahan tak terduga: {e}", "err")
        finally:
            with self.busy_lock:
                self.busy.discard(name)
            self._set_busy("Idle")

    def _ctl(self, name, action):
        """Kontrol layanan via ctypes; fallback elevasi bila akses ditolak."""
        try:
            if action == "start":
                svc_start(name)
                st = wait_state(name, (SVC_RUNNING,), 30)
                if st != SVC_RUNNING:
                    return False, "gagal mencapai state RUNNING (timeout)"
                return True, "berjalan"
            if action == "stop":
                st, _ = query_service(name)
                if st != SVC_STOPPED:
                    svc_stop(name)
                    st = wait_state(name, (SVC_STOPPED,), 25)
                if st != SVC_STOPPED:
                    return False, "gagal berhenti (timeout)"
                return True, "dihentikan"
            if action == "restart":
                st, _ = query_service(name)
                if st != SVC_STOPPED:
                    svc_stop(name)
                    st = wait_state(name, (SVC_STOPPED,), 25)
                    if st != SVC_STOPPED:
                        return False, "gagal berhenti sebelum start (timeout)"
                svc_start(name)
                st = wait_state(name, (SVC_RUNNING,), 30)
                if st != SVC_RUNNING:
                    return False, "gagal mencapai state RUNNING (timeout)"
                return True, "di-restart"
            return False, f"aksi tidak dikenal: {action}"
        except ServiceError as e:
            if e.winerror == 5 and not is_admin() and not self.opts.smoke:
                return self._elevated_action(name, action)
            return False, str(e)

    def _elevated_action(self, name, action):
        """Fallback: luncurkan aksi via UAC (nssm/PowerShell), lalu cek state."""
        self._act(f"{name}: akses ditolak \u2014 mencoba via UAC "
                  "(klik 'Ya' bila muncul)", "warn")
        launched = False
        if self.nssm:
            launched = shell_runas(str(self.nssm), f"{action} {name}",
                                   str(self.nssm.parent))
        if not launched:
            ps = {"start": "Start-Service", "stop": "Stop-Service",
                  "restart": "Restart-Service"}.get(action)
            if ps:
                launched = shell_runas(
                    "powershell.exe", f"-NoProfile -Command {ps} -Name {name}",
                    None)
        if not launched:
            return False, "elevasi dibatalkan / gagal"
        target = SVC_STOPPED if action == "stop" else SVC_RUNNING
        st = wait_state(name, (target,), 45)
        if st == target:
            return True, f"{action} via UAC berhasil"
        return False, f"{action} via UAC tidak mencapai target (timeout)"

    def _elevate_relaunch(self) -> bool:
        """Relaunch aplikasi ini sendiri dengan UAC."""
        try:
            if self._mutex:
                _k32.CloseHandle(self._mutex)
                self._mutex = None
        except Exception:
            pass
        exe = sys.executable
        pyw = Path(exe).with_name("pythonw.exe")
        if pyw.exists():
            exe = str(pyw)
        script = os.path.abspath(sys.argv[0])
        ok = shell_runas(exe, f'"{script}"', str(SCRIPT_DIR), show=1)
        if ok:
            self._closing = True
            self.stop_evt.set()
        else:
            messagebox.showwarning(
                "Elevasi", "Permintaan Administrator dibatalkan / gagal.")
        return ok

    # ---------------------------------------------------------- poller/watchdog
    def _poller(self):
        first = True
        while not self.stop_evt.is_set():
            try:
                self._poll_cycle(first)
            except Exception as e:                           # noqa: BLE001
                self._act(f"Poller error: {e}", "err")
            first = False
            self.stop_evt.wait(POLL_INTERVAL)

    def _poll_cycle(self, first=False):
        results = {}
        for spec in SERVICES:
            results[spec["name"]] = self._refresh_card(spec["name"])

        now = time.time()
        bootstrap = first and not self.opts.smoke

        # ---- watchdog auto-restart
        for spec in SERVICES:
            name = spec["name"]
            st = results[name]
            if st == SVC_STOPPED:
                if self.user_stopped.get(name):
                    continue
                self.stop_streak[name] = self.stop_streak.get(name, 0) + 1
                need = 1 if bootstrap else 2
                if not self.flags["auto_restart"]:
                    continue
                if self.stop_streak[name] < need:
                    continue
                if now - self.last_auto[name] < 30:
                    continue
                self.last_auto[name] = now
                if is_admin():
                    self._act(f"\U0001F501 Watchdog: {name} mati tanpa "
                              "perintah \u2014 menyalakan ulang\u2026", "warn")
                    try:
                        svc_start(name)
                        self._act(f"\u2705 Watchdog: {name} kembali berjalan.",
                                  "ok")
                    except ServiceError as e:
                        self._act(f"\u26A0\uFE0F Watchdog gagal menyalakan "
                                  f"{name}: {e}", "err")
                else:
                    self._act(f"\u26A0\uFE0F {name} mati. Auto-restart butuh "
                              "mode Administrator (buka via tombol elevasi).",
                              "warn")
            else:
                self.stop_streak[name] = 0

        # ---- layanan belum terpasang?
        if bootstrap:
            missing = [s["name"] for s in SERVICES
                       if results[s["name"]] == STATE_NOT_INSTALLED]
            if missing:
                self.uiq.put(lambda m=", ".join(missing): self._show_banner(
                    "danger",
                    f"Layanan belum terpasang: {m}",
                    "\U0001F6E0 Pasang Layanan (UAC)",
                    self._install_services))
                self._act(f"Layanan belum terpasang: {m} \u2014 jalankan "
                          "installer (tombol di banner atas).", "err")
        elif self._banner is not None and all(
                results[s["name"]] != STATE_NOT_INSTALLED for s in SERVICES):
            self.uiq.put(self._hide_banner)

        # ---- probe web
        ok, code, ms, err = http_probe(self.app_url)
        self.uiq.put(lambda o=ok, c=code, m=ms, e=err: self._set_web_chip(
            o, c, m, e))
        if not ok and code is None:
            self.web_fail += 1
            if (self.flags["web_watch"] and not self.optimizing
                    and not self.opts.smoke
                    and results.get("poms-web") == SVC_RUNNING):
                if self.web_fail == WEB_FAIL_KILL:
                    pids = kill_orphan_php_servers()
                    if pids:
                        self._act(f"\U0001F9F9 Membersihkan proses `php -S` "
                                  f"zombie (PID {pids}) yang menahan port "
                                  "8000\u2026", "warn")
                    self.web_kill_at = now
                elif (self.web_fail >= WEB_FAIL_RESTART
                      and now - self.last_web_fix > 120
                      and (self.web_kill_at is None
                           or now - self.web_kill_at > 8)):
                    self.last_web_fix = now
                    self._act("\U0001F501 Web tetap tidak bisa diakses \u2014 "
                              "restart poms-web\u2026", "warn")
                    threading.Thread(target=self._do_service_action,
                                     daemon=True,
                                     args=("poms-web", "restart",
                                           "watchdog")).start()
        else:
            self.web_fail = 0
            self.web_kill_at = None

        self.uiq.put(lambda: self.lbl_updated.configure(
            text="update: " + time.strftime("%H:%M:%S")))

        # ---- file watcher -> auto-optimize
        if (self.flags["auto_optimize"] and not self.optimizing
                and not self.opts.smoke):
            new_fp = build_fingerprint(self.root_dir)
            changed = {p for p, v in new_fp.items() if self.fp.get(p) != v}
            if changed:
                if self.pending_since is None:
                    self.pending_since = now
                    n = len(changed)
                    preview = sorted(
                        str(Path(p).relative_to(self.root_dir))
                        for p in list(changed)[:8])
                    self._act(f"\U0001F440 Perubahan terdeteksi ({n} file): "
                              + ", ".join(preview) + ("\u2026" if n > 8 else ""),
                              "info")
                elif now - self.pending_since >= QUIET_PERIOD:
                    self.pending_since = None
                    self.fp = new_fp
                    threading.Thread(target=self._optimize_job, daemon=True,
                                     args=(sorted(changed), "auto")).start()
            else:
                self.pending_since = None

    def _set_web_chip(self, ok, code, ms, err):
        if ok and code and code < 400:
            self.chip_web.configure(
                text=f"Web: OK {code} ({ms} ms)", bg=C_ACCENT, fg="#ffffff")
        elif ok:
            self.chip_web.configure(
                text=f"Web: HTTP {code} ({ms} ms)", bg=C_WARN, fg="#ffffff")
        else:
            self.chip_web.configure(
                text="Web: GAGAL", bg=C_DANGER, fg="#ffffff")
            if err:
                self._act(f"Web gagal diakses: {err}", "err")

    def _install_services(self):
        bat = SCRIPT_DIR / "install-services.bat"
        if not bat.exists():
            messagebox.showerror("Installer", f"Tidak ditemukan: {bat}")
            return
        self._act("Meluncurkan installer layanan (UAC)\u2026", "info")
        if not shell_runas(str(bat), None, str(SCRIPT_DIR), show=1):
            self._act("Elevasi installer dibatalkan.", "warn")

    # ------------------------------------------------------------- optimize
    def _run_php(self, args, timeout=180) -> bool:
        if not self.php:
            self._act("PHP tidak ditemukan \u2014 lewati perintah artisan.",
                      "err")
            return False
        cmd = [self.php, "artisan", *args]
        self._act(f"$ php artisan {' '.join(args)}", "dim")
        try:
            r = subprocess.run(
                cmd, cwd=str(self.root_dir), capture_output=True, text=True,
                errors="replace", timeout=timeout,
                creationflags=CREATE_NO_WINDOW if _is_nt else 0)
        except FileNotFoundError:
            self._act(f"PHP tidak bisa dijalankan: {self.php}", "err")
            return False
        except subprocess.TimeoutExpired:
            self._act(f"php artisan {args[0]} timeout ({timeout}s)", "err")
            return False
        out = ((r.stdout or "") + (r.stderr or "")).strip()
        lines = [l for l in out.splitlines() if l.strip()]
        for l in lines[-25:]:
            self._act("   " + l, "err" if r.returncode != 0 else "dim")
        if r.returncode != 0:
            self._act(f"\u274C php artisan {args[0]} gagal "
                      f"(exit {r.returncode})", "err")
            return False
        return True

    def _optimize_job(self, changed, trigger):
        with self.opt_lock:
            if self.optimizing:
                return
            self.optimizing = True
        try:
            self.uiq.put(lambda: self.btn_opt.configure(state="disabled"))
            self._set_busy("\u26A1 Optimizing\u2026")
            env_changed = changed is not None and any(
                Path(p).name == ".env" and Path(p).parent == self.root_dir
                for p in changed)
            if changed is None:
                self._act("\u26A1 Optimize manual dimulai\u2026", "info")
            else:
                self._act(f"\u26A1 Auto-optimize ({trigger}, {len(changed)} "
                          "file berubah)\u2026", "info")

            ok1 = self._run_php(["config:clear"], 120)
            ok2 = self._run_php(["optimize"], 240)

            if changed:
                for p in changed:
                    rel = str(Path(p).relative_to(self.root_dir))
                    if rel.startswith("composer"):
                        self._act("\u26A0\uFE0F composer.json/lock berubah "
                                  "\u2014 jalankan `composer install` manual.",
                                  "warn")
                    elif rel.startswith("database" + os.sep + "migrations"):
                        self._act("\u26A0\uFE0F Migrasi berubah \u2014 jalankan "
                                  "`php artisan migrate` manual (tidak "
                                  "dieksekusi otomatis).", "warn")

            if ok1 and ok2:
                targets = RESTART_AFTER_ENV if env_changed \
                    else RESTART_AFTER_CODE
                alasan = ".env" if env_changed else "kode"
                self._act(f"\u21BB Restart layanan pemegang {alasan} lama: "
                          + ", ".join(targets), "info")
                for name in targets:
                    if self.stop_evt.is_set():
                        break
                    ok, msg = self._ctl(name, "restart")
                    self._act(("\u2705 " if ok else "\u274C ")
                              + f"{name}: {msg}", "ok" if ok else "err")
                    self._refresh_card(name)
                self._act(f"\u2705 Optimize selesai "
                          f"({time.strftime('%H:%M:%S')}).", "ok")
            else:
                self._act("Optimize tidak selesai sempurna \u2014 layanan TIDAK "
                          "di-restart. Periksa pesan di atas.", "err")
        except Exception as e:                                   # noqa: BLE001
            self._act(f"\u274C Auto-optimize error: {e}", "err")
        finally:
            self.fp = build_fingerprint(self.root_dir)
            self.optimizing = False
            self.uiq.put(lambda: self.btn_opt.configure(state="normal"))
            self._set_busy("Idle")

    # ---------------------------------------------------------------- tailer
    def _tailer(self):
        while not self.stop_evt.is_set():
            for slug, follower in self.followers.items():
                try:
                    lines = follower.poll()
                except Exception:
                    lines = None
                if lines:
                    for l in lines:
                        self.uiq.put(
                            lambda s=slug, line=l: self._append_log(s, line))
            self.stop_evt.wait(TAIL_INTERVAL)

    # ----------------------------------------------------------------- close
    def _on_close(self):
        self._closing = True
        self.stop_evt.set()
        try:
            if self._mutex:
                _k32.CloseHandle(self._mutex)
                self._mutex = None
        except Exception:
            pass
        self.root.destroy()


# ------------------------------------------------------------------- selftest
def run_selftest(opts) -> int:
    print("=== POMS Service Manager \u2014 selftest ===")
    print(f"Python     : {sys.version.split()[0]}  "
          f"(tkinter: {'OK' if TK_OK else 'TIDAK ADA'})")
    print(f"Admin      : {'YA' if is_admin() else 'TIDAK (aksi layanan butuh admin)'}")
    print(f"Project    : {opts.project}")
    print(f"PHP        : {find_php() or 'TIDAK DITEMUKAN'}")
    print(f"NSSM       : {find_nssm(Path(opts.project)) or 'TIDAK DITEMUKAN (fallback PowerShell)'}")
    env = read_env_safe(Path(opts.project))
    print(f"APP        : {env.get('APP_NAME', '?')} | env={env.get('APP_ENV', '?')} "
          f"| url={env.get('APP_URL', '?')}")
    print("Layanan:")
    all_running = True
    for s in SERVICES:
        st, pid = query_service(s["name"])
        txt, _ = STATE_TEXT.get(st, state_view(st))
        if st not in (SVC_RUNNING,):
            all_running = False
        print(f"  {s['name']:<14} {txt:<16} PID {pid if pid else '-'}")
    url = env.get("APP_URL") or "http://localhost:8000"
    ok, code, ms, err = http_probe(url)
    print(f"Web        : {'OK ' + str(code) + f' ({ms} ms)' if ok else 'GAGAL: ' + str(err)}")
    logs = sum(1 for _, p in log_paths(Path(opts.project)) if Path(p).exists())
    print(f"Log files  : {logs}/5 ditemukan")
    fp = build_fingerprint(Path(opts.project))
    print(f"Pantau kode: {len(fp)} file dalam fingerprint")
    print("Selftest selesai.")
    return 0 if all_running else 0


def state_view(state):
    if state == STATE_NOT_INSTALLED:
        return ("Belum terpasang", C_FG_DIM)
    if state == STATE_ERROR:
        return ("Gagal cek", C_ERR)
    return STATE_TEXT.get(state, ("Tidak diketahui", C_FG_DIM))


def main() -> int:
    if os.name != "nt":
        print("Script ini untuk Windows (mengelola layanan NSSM).")
        return 1
    ap = argparse.ArgumentParser(description="POMS Service Manager")
    ap.add_argument("--project", default=str(DEFAULT_PROJECT),
                    help="path root project Laravel (default: deteksi otomatis)")
    ap.add_argument("--selftest", action="store_true",
                    help="cek layanan/path/web tanpa membuka GUI")
    ap.add_argument("--smoke", action="store_true",
                    help="uji bangun UI + 1 siklus polling lalu keluar")
    opts = ap.parse_args()
    opts.project = str(Path(opts.project).resolve())

    if opts.selftest:
        return run_selftest(opts)

    if not TK_OK:
        print("tkinter tidak tersedia pada Python ini.")
        return 1

    try:
        ctypes.windll.shcore.SetProcessDpiAwareness(1)
    except Exception:
        pass

    root = tk.Tk()
    app = App(root, opts)
    return app.run()


if __name__ == "__main__":
    sys.exit(main())
