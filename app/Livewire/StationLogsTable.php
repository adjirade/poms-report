<?php

namespace App\Livewire;

use App\Models\LogKernel;
use App\Models\LogKlarifikasi;
use App\Models\LogLab;
use App\Models\LogMaintenance;
use App\Models\LogPress;
use App\Models\LogSortasi;
use App\Models\LogSterilizer;
use App\Models\LogTimbang;
use App\Support\StationLogDepartmentTrait;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StationLogsTable extends Component
{
    use StationLogDepartmentTrait, WithPagination;

    public string $station;

    #[Url]
    public string $dateFrom;

    #[Url]
    public string $dateTo;

    /** all | flagged | unverified | verified */
    #[Url]
    public string $filterType = 'all';

    #[Url]
    public string $search = '';

    public function mount(string $station)
    {
        $this->station = $station;
        $this->dateFrom = now()->startOfDay()->format('Y-m-d');
        $this->dateTo = now()->endOfDay()->format('Y-m-d');
    }

    public function render()
    {
        $logs = $this->getLogs();

        return view('livewire.station-logs-table', [
            'logs' => $logs,
            'columns' => $this->getColumnsForStation(),
        ]);
    }

    protected function getLogs()
    {
        $modelClass = $this->getModelClass();
        $user = auth()->user();

        $query = $modelClass::query()
            ->with(['user', 'verifier'])
            ->whereDate('timestamp_kirim', '>=', $this->dateFrom)
            ->whereDate('timestamp_kirim', '<=', $this->dateTo)
            ->orderBy('timestamp_kirim', 'desc');

        // Filter by plant (except HQ admin who can see all)
        if (! Gate::allows('view-all-plants')) {
            $query->where('plant_id', $user->plant_id);
        }

        // Filter by department for asisten
        if ($user->role === 'asisten' && $user->department) {
            $departmentStations = $this->getDepartmentStations($user->department);
            if (! in_array($this->station, $departmentStations)) {
                abort(403, 'Anda tidak memiliki akses ke stasiun ini.');
            }
        }

        // Apply filters
        $query = match ($this->filterType) {
            'flagged' => $query->where('is_flagged', true),
            'unverified' => $query->where('is_verified', false),
            'verified' => $query->where('is_verified', true),
            default => $query,
        };

        // Apply search across user name and station-specific searchable columns
        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $searchableColumns = $this->getSearchableColumnsForStation();

            $query->where(function ($q) use ($term, $searchableColumns) {
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', $term));

                foreach ($searchableColumns as $column) {
                    $q->orWhere($column, 'like', $term);
                }
            });
        }

        return $query->paginate(50);
    }

    protected function getModelClass()
    {
        return match ($this->station) {
            'timbang' => LogTimbang::class,
            'sortasi' => LogSortasi::class,
            'sterilizer' => LogSterilizer::class,
            'press' => LogPress::class,
            'klarifikasi' => LogKlarifikasi::class,
            'kernel' => LogKernel::class,
            'lab' => LogLab::class,
            'maintenance' => LogMaintenance::class,
            default => throw new \Exception("Unknown station: {$this->station}"),
        };
    }

    protected function getSearchableColumnsForStation(): array
    {
        return match ($this->station) {
            'timbang', 'sortasi' => ['no_spb'],
            'sterilizer' => ['no_rebusan'],
            'press' => ['no_press'],
            'klarifikasi' => ['no_tangki'],
            'maintenance' => ['kode_mesin', 'keterangan_perbaikan'],
            default => [],
        };
    }

    protected function getColumnsForStation(): array
    {
        return match ($this->station) {
            'timbang' => [
                'No SPB' => 'no_spb',
                'Tonase Bruto (kg)' => 'tonase_bruto',
                'Tonase Tarra (kg)' => 'tonase_tarra',
                'Potongan (%)' => 'potongan_persen',
            ],
            'sortasi' => [
                'No SPB' => 'no_spb',
                'Buah Mentah (%)' => 'buah_mentah_persen',
                'Buah Matang (%)' => 'buah_matang_persen',
                'Jankos (%)' => 'jankos_persen',
                'Tangkai Panjang (%)' => 'tangkai_panjang_persen',
            ],
            'sterilizer' => [
                'No Rebusan' => 'no_rebusan',
                'Tekanan (Bar)' => 'tekanan_bar',
                'Suhu (°C)' => 'suhu_celcius',
                'Durasi (Menit)' => 'durasi_menit',
            ],
            'press' => [
                'No Press' => 'no_press',
                'Tekanan Hidrolik (Kg/cm²)' => 'tekanan_hidrolik',
                'Ampere Motor' => 'ampere_motor',
                'Tambah Air (%)' => 'tambah_air_persen',
            ],
            'klarifikasi' => [
                'No Tangki' => 'no_tangki',
                'Suhu Tangki (°C)' => 'suhu_tangki_celcius',
                'Level Minyak (cm)' => 'level_minyak_cm',
                'Kadar Air (%)' => 'kadar_air_persen',
            ],
            'kernel' => [
                'Suhu Silo (°C)' => 'suhu_silo_celcius',
                'Losses Inti (%)' => 'losses_inti_persen',
                'Kadar Kotoran (%)' => 'kadar_kotoran_persen',
            ],
            'lab' => [
                'Kadar ALB CPO (%)' => 'kadar_alb_cpo',
                'Losses Fiber (%)' => 'losses_fiber_persen',
                'Losses Jankos (%)' => 'losses_jankos_persen',
            ],
            'maintenance' => [
                'Kode Mesin' => 'kode_mesin',
                'Jam Jalan (HM)' => 'jam_jalan_hm',
                'Status' => 'status_kondisi',
                'Keterangan' => 'keterangan_perbaikan',
            ],
            default => [],
        };
    }

    public function verifyRecord($recordId)
    {
        if (! Gate::allows('verify-data')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk verifikasi data.');

            return;
        }

        $user = auth()->user();

        // Asisten terbatas pada stasiun departemennya (PRD §3) — defense-in-depth;
        // getLogs() sudah abort 403 saat render tabel stasiun lain.
        if ($user->role === 'asisten' && $user->department) {
            if (! in_array($this->station, $this->getDepartmentStations($user->department), true)) {
                session()->flash('error', 'Anda hanya dapat memverifikasi data stasiun di departemen Anda.');

                return;
            }
        }

        $modelClass = $this->getModelClass();
        $record = $modelClass::findOrFail($recordId);

        // Plant isolation (developer may verify across plants)
        if ($record->plant_id !== $user->plant_id && ! $user->hasRole('developer')) {
            session()->flash('error', 'Data milik plant lain, tidak dapat diverifikasi.');

            return;
        }

        if ($record->is_verified) {
            session()->flash('warning', 'Data sudah diverifikasi sebelumnya.');

            return;
        }

        $record->update([
            'is_verified' => true,
            'verified_by' => auth()->id(),
        ]);

        session()->flash('success', 'Data berhasil diverifikasi.');
    }

    public function resetFilters()
    {
        $this->dateFrom = now()->startOfDay()->format('Y-m-d');
        $this->dateTo = now()->endOfDay()->format('Y-m-d');
        $this->filterType = 'all';
        $this->search = '';
        $this->resetPage();
    }
}
