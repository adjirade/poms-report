<?php

namespace App\Support;

/**
 * Shared station department mapping used by Livewire dashboards and Outbound SMS.
 */
trait StationLogDepartmentTrait
{
    /**
     * Map a department to the station keys its users may submit/see.
     */
    public function getDepartmentStations(string $department): array
    {
        return match ($department) {
            'proses' => ['timbang', 'sortasi', 'sterilizer', 'press', 'klarifikasi', 'kernel'],
            'maintenance' => ['maintenance'],
            'lab' => ['lab'],
            default => [],
        };
    }

    /**
     * Kebalikan getDepartmentStations(): departemen pemilik sebuah stasiun.
     * Dipakai routing notifikasi (mis. record flagged -> asisten departemen).
     */
    public function departmentForStation(string $station): ?string
    {
        foreach (['proses', 'maintenance', 'lab'] as $department) {
            if (in_array($station, $this->getDepartmentStations($department), true)) {
                return $department;
            }
        }

        return null;
    }
}
