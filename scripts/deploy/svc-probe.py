import ctypes
import subprocess
import sys
import time

OUT = sys.argv[1] if len(sys.argv) > 1 else "_showdown.txt"
NSSM = r"D:\poms-app\scripts\deploy\nssm.exe"
NAME = "poms-queue"

adv = ctypes.WinDLL("advapi32.dll", use_last_error=True)
adv.OpenSCManagerW.restype = ctypes.c_void_p
adv.OpenSCManagerW.argtypes = [ctypes.c_void_p, ctypes.c_void_p, ctypes.c_ulong]
adv.OpenServiceW.restype = ctypes.c_void_p
adv.OpenServiceW.argtypes = [ctypes.c_void_p, ctypes.c_void_p, ctypes.c_ulong]
adv.CloseServiceHandle.argtypes = [ctypes.c_void_p]
adv.StartServiceW.argtypes = [ctypes.c_void_p, ctypes.c_ulong, ctypes.c_void_p]
adv.ControlService.argtypes = [ctypes.c_void_p, ctypes.c_ulong, ctypes.c_void_p]
adv.QueryServiceStatus.argtypes = [ctypes.c_void_p, ctypes.c_void_p]

CREATE_NO_WINDOW = 0x08000000


class SERVICE_STATUS(ctypes.Structure):
    _fields_ = [("dwServiceType", ctypes.c_ulong),
                ("dwCurrentState", ctypes.c_ulong),
                ("dwControlsAccepted", ctypes.c_ulong),
                ("dwWin32ExitCode", ctypes.c_ulong),
                ("dwServiceSpecificExitCode", ctypes.c_ulong),
                ("dwCheckPoint", ctypes.c_ulong),
                ("dwWaitHint", ctypes.c_ulong)]


SC_MANAGER_CONNECT = 0x0001
SERVICE_ALL_ACCESS = 0xF01FF
SERVICE_QUERY_STATUS = 0x0004
SERVICE_STOP = 0x0020   # 0x0020 (0x0002 = SERVICE_CHANGE_CONFIG!)
SERVICE_START = 0x0010
SERVICE_CONTROL_STOP = 0x0001
SVC_STOPPED, SVC_RUNNING = 1, 4

lines = []


def admin():
    try:
        return bool(ctypes.windll.shell32.IsUserAnAdmin())
    except Exception:
        return False


lines.append(f"IsUserAnAdmin={admin()}")

scm = adv.OpenSCManagerW(None, None, SC_MANAGER_CONNECT)
lines.append(f"OpenSCManager={'OK' if scm else 'FAIL err=' + str(ctypes.get_last_error())}")


def state_of():
    h = adv.OpenServiceW(scm, NAME, SERVICE_QUERY_STATUS)
    if not h:
        return None
    st = SERVICE_STATUS()
    ok = adv.QueryServiceStatus(h, ctypes.byref(st))
    adv.CloseServiceHandle(h)
    return st.dwCurrentState if ok else None


def wait(target, timeout):
    end = time.time() + timeout
    while time.time() < end:
        s = state_of()
        if s == target:
            return s
        time.sleep(0.4)
    return state_of()


def run(cmd):
    try:
        r = subprocess.run(cmd, capture_output=True, text=True,
                           errors="replace", timeout=60,
                           creationflags=CREATE_NO_WINDOW)
        out = ((r.stdout or "") + (r.stderr or "")).strip().replace("\n", " | ")
        return f"rc={r.returncode} out='{out[:300]}'"
    except Exception as e:
        return f"EXC {e}"


s0 = state_of()
lines.append(f"state awal={s0}")

# --- A: ctypes dengan handle SERVICE_ALL_ACCESS
h = adv.OpenServiceW(scm, NAME, SERVICE_ALL_ACCESS)
err = ctypes.get_last_error()
lines.append(f"[A ctypes-ALL] open={'OK' if h else 'FAIL err=' + str(err)}")
if h:
    st = SERVICE_STATUS()
    ok = adv.ControlService(h, SERVICE_CONTROL_STOP, ctypes.byref(st))
    lines.append(f"[A ctypes-ALL] ControlService(STOP)="
                 f"{'OK' if ok else 'FAIL err=' + str(ctypes.get_last_error())}")
    if ok:
        s1 = wait(SVC_STOPPED, 25)
        lines.append(f"[A] state setelah stop={s1}")
        if s1 == SVC_STOPPED:
            ok2 = adv.StartServiceW(h, 0, None)
            lines.append(f"[A] StartService="
                         f"{'OK' if ok2 else 'FAIL err=' + str(ctypes.get_last_error())}")
            s2 = wait(SVC_RUNNING, 30)
            lines.append(f"[A] state setelah start={s2}")
    adv.CloseServiceHandle(h)

s_now = state_of()
lines.append(f"state setelah A={s_now}")

# --- B: sc.exe stop/start (binari milik Windows)
if s_now != SVC_STOPPED:
    lines.append(f"[B sc.exe] stop: " + run(["sc.exe", "stop", NAME]))
    s1 = wait(SVC_STOPPED, 25)
    lines.append(f"[B sc.exe] state setelah stop={s1}")
    if s1 == SVC_STOPPED:
        lines.append(f"[B sc.exe] start: " + run(["sc.exe", "start", NAME]))
        s2 = wait(SVC_RUNNING, 30)
        lines.append(f"[B sc.exe] state setelah start={s2}")

s_now = state_of()
lines.append(f"state setelah B={s_now}")

# --- C: nssm restart
if s_now != SVC_RUNNING:
    lines.append(f"[C nssm] restart: " + run([NSSM, "restart", NAME]))
    s2 = wait(SVC_RUNNING, 40)
    lines.append(f"[C nssm] state setelah restart={s2}")

lines.append(f"state akhir={state_of()}")

with open(OUT, "w", encoding="utf-8") as f:
    f.write("\n".join(lines) + "\n")
print("\n".join(lines))
