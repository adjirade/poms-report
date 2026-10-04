<?php

namespace App\Services;

use App\Models\LogKernel;
use App\Models\LogKlarifikasi;
use App\Models\LogLab;
use App\Models\LogMaintenance;
use App\Models\LogPress;
use App\Models\LogSortasi;
use App\Models\LogSterilizer;
use App\Models\LogTimbang;
use App\Models\ValidationRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ValidationService
{
    /**
     * Validate station data against rules
     *
     * @return array ['valid' => bool, 'errors' => array, 'flagged_params' => array]
     *
     * @throws \Exception
     */
    public function validate(string $plantId, string $stationName, array $parameters): array
    {
        $rules = ValidationRule::where('plant_id', $plantId)
            ->where('station_name', $stationName)
            ->get()
            ->keyBy('parameter_name');

        $errors = [];
        $flaggedParams = [];

        foreach ($parameters as $paramName => $value) {
            // Skip non-validatable fields
            if (in_array($paramName, ['user_id', 'plant_id', 'timestamp_kirim', 'timestamp_server', 'notes'])) {
                continue;
            }

            $rule = $rules->get($paramName);

            if (! $rule) {
                $errors[] = "Parameter '{$paramName}' tidak memiliki aturan validasi untuk stasiun '{$stationName}'.";

                continue;
            }

            // Validate based on data type
            if ($rule->data_type === 'enum') {
                $allowedValues = explode(',', $rule->allowed_values);
                if (! in_array($value, $allowedValues)) {
                    $errors[] = $rule->getErrorMessage($value);
                    $flaggedParams[] = $paramName;
                }
            } elseif ($rule->data_type === 'string') {
                // Teks bebas (mis. no_spb, kode_mesin, keterangan_perbaikan).
                // Tidak ada rentang/enum yang bisa dicek; kewajiban mengisi
                // (required) ditegakkan di lapisan form/parser per perintah.
                continue;
            } else {
                // Numeric validation — tolak nilai non-numeric secara eksplisit
                // agar tidak ter-cast diam-diam menjadi 0.0 dan lolos range check.
                if (! is_numeric($value)) {
                    $errors[] = "Parameter '{$paramName}' harus berupa angka (diterima: '{$value}').";
                    $flaggedParams[] = $paramName;

                    continue;
                }

                $numericValue = (float) $value;
                if ($numericValue < $rule->min_value || $numericValue > $rule->max_value) {
                    $errors[] = $rule->getErrorMessage($value);
                    $flaggedParams[] = $paramName;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'flagged_params' => $flaggedParams,
        ];
    }

    /**
     * Check time discrepancy for anti-fraud detection
     *
     * @param  Carbon  $timestampKirim
     * @param  Carbon  $timestampServer
     * @return bool True if discrepancy > 4 hours
     */
    public function checkTimeDiscrepancy($timestampKirim, $timestampServer): bool
    {
        $diff = abs($timestampServer->diffInHours($timestampKirim));

        return $diff > 4;
    }

    /**
     * Validate and prepare data for database insertion
     * Includes double timestamping and flagging logic
     *
     * @param  Carbon  $messageDate  Telegram message.date
     * @return array ['success' => bool, 'data' => array|null, 'errors' => array]
     */
    public function validateAndPrepare(string $plantId, string $stationName, array $data, $messageDate): array
    {
        // Validate parameters
        $validation = $this->validate($plantId, $stationName, $data);

        if (! $validation['valid']) {
            return [
                'success' => false,
                'data' => null,
                'errors' => $validation['errors'],
            ];
        }

        // Prepare timestamps
        $timestampKirim = $messageDate;
        $timestampServer = now();

        // Check time discrepancy for fraud detection
        $isFlagged = $this->checkTimeDiscrepancy($timestampKirim, $timestampServer);

        // Add timestamps to data
        $data['timestamp_kirim'] = $timestampKirim;
        $data['timestamp_server'] = $timestampServer;
        $data['is_flagged'] = $isFlagged;

        return [
            'success' => true,
            'data' => $data,
            'errors' => [],
        ];
    }

    /**
     * Get station model class based on station name
     *
     * @return string Model class name
     *
     * @throws \Exception
     */
    public function getStationModel(string $stationName): string
    {
        $modelMap = [
            'timbang' => LogTimbang::class,
            'sortasi' => LogSortasi::class,
            'sterilizer' => LogSterilizer::class,
            'press' => LogPress::class,
            'klarifikasi' => LogKlarifikasi::class,
            'kernel' => LogKernel::class,
            'lab' => LogLab::class,
            'maintenance' => LogMaintenance::class,
        ];

        if (! isset($modelMap[$stationName])) {
            throw new \Exception("Stasiun '{$stationName}' tidak dikenal.");
        }

        return $modelMap[$stationName];
    }

    /**
     * Save station data with transaction
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function saveStationData(string $stationName, array $data)
    {
        return DB::transaction(function () use ($stationName, $data) {
            $modelClass = $this->getStationModel($stationName);

            return $modelClass::create($data);
        });
    }
}
