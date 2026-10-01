<?php

namespace App\Exports;

use App\Services\ValidationService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class StationLogsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    /**
     * Column labels per station.
     */
    protected array $columns;

    protected Collection $logs;

    public function __construct(
        protected string $station,
        protected string $dateFrom,
        protected string $dateTo,
        protected string $plantId,
        protected bool $allPlants = false,
    ) {
        $this->columns = $this->stationColumns();

        $modelClass = app(ValidationService::class)->getStationModel($this->station);

        $query = $modelClass::query()
            ->with(['user', 'verifier'])
            ->whereDate('timestamp_kirim', '>=', $this->dateFrom)
            ->whereDate('timestamp_kirim', '<=', $this->dateTo)
            ->orderBy('timestamp_kirim', 'asc');

        if (!$this->allPlants) {
            $query->where('plant_id', $this->plantId);
        }

        $this->logs = $query->get();
    }

    public function collection(): Collection
    {
        return $this->logs;
    }

    public function headings(): array
    {
        return array_merge(
            ['ID', 'Operator', 'Plant', 'Waktu Kirim', 'Waktu Server'],
            array_keys($this->columns),
            ['Flagged', 'Verified']
        );
    }

    public function map($log): array
    {
        $data = [];
        foreach ($this->columns as $column) {
            $value = $log->{$column};

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('d/m/Y H:i:s');
            }

            $data[] = $value;
        }

        return array_merge(
            [
                $log->id,
                $log->user->name ?? 'N/A',
                $log->plant_id,
                optional($log->timestamp_kirim)->format('d/m/Y H:i:s'),
                optional($log->timestamp_server)->format('d/m/Y H:i:s'),
            ],
            $data,
            [$log->is_flagged ? 'YA' : 'TIDAK', $log->is_verified ? 'YA' : 'TIDAK']
        );
    }

    public function title(): string
    {
        return ucfirst($this->station);
    }

    protected function stationColumns(): array
    {
        return match($this->station) {
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
}
