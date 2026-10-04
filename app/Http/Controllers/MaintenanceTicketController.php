<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Services\MaintenanceTicketService;
use Illuminate\Http\Request;

/**
 * B3 — Tiket Maintenance.
 *
 * Daftar tiket (dengan filter status & mesin), form laporan kerusakan, dan
 * perubahan status. Riwayat per mesin ditampilkan saat filter `kode_mesin`
 * diisi. Semua query dibatasi ke plant user yang login.
 */
class MaintenanceTicketController extends Controller
{
    public function __construct(protected MaintenanceTicketService $service) {}

    public function index(Request $request)
    {
        $plantId = (string) auth()->user()->plant_id;
        $status = (string) $request->query('status', '');
        $mesin = trim((string) $request->query('kode_mesin', ''));

        $query = MaintenanceTicket::forPlant($plantId)
            ->with(['reporter:id,name', 'assignee:id,name'])
            ->latest();

        if (in_array($status, MaintenanceTicket::STATUSES, true)) {
            $query->withStatus($status);
        }
        if ($mesin !== '') {
            $query->where('kode_mesin', 'like', "%{$mesin}%");
        }

        $tickets = $query->paginate(20)->withQueryString();

        // Ringkasan jumlah tiket per status (1 query agregat, hindari N+1).
        $counts = MaintenanceTicket::forPlant($plantId)
            ->selectRaw("sum(case when status = 'open' then 1 else 0 end) as open_count")
            ->selectRaw("sum(case when status = 'dikerjakan' then 1 else 0 end) as in_progress_count")
            ->selectRaw("sum(case when status = 'selesai' then 1 else 0 end) as done_count")
            ->first();

        // Riwayat per mesin (bila filter mesin diisi).
        $history = $mesin !== ''
            ? MaintenanceTicket::forPlant($plantId)->where('kode_mesin', $mesin)->latest()->limit(50)->get()
            : collect();

        $technicians = User::query()
            ->where('plant_id', $plantId)
            ->where('department', 'maintenance')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('maintenance.tickets', [
            'tickets' => $tickets,
            'counts' => $counts,
            'history' => $history,
            'status' => $status,
            'mesin' => $mesin,
            'technicians' => $technicians,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_mesin' => ['required', 'string', 'max:50'],
            'judul' => ['required', 'string', 'max:120'],
            'deskripsi' => ['required', 'string', 'max:2000'],
            'prioritas' => ['nullable', 'in:rendah,sedang,tinggi'],
        ]);

        $this->service->create($validated, $request->user());

        return back()->with('success', '✅ Tiket maintenance dibuat. Departemen maintenance telah diberi tahu.');
    }

    public function update(Request $request, MaintenanceTicket $ticket)
    {
        // Batasi ke plant user (id tiket dari plant lain -> 403).
        abort_unless($ticket->plant_id === (string) $request->user()->plant_id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:open,dikerjakan,selesai'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->service->changeStatus(
            $ticket,
            $validated['status'],
            $validated['resolution_note'] ?? null,
            isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
        );

        return back()->with('success', "✅ Tiket #{$ticket->id} diperbarui menjadi \"{$ticket->statusLabel()}\".");
    }
}
