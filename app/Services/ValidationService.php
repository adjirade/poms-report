<?php

namespace App\Services;

use App\Models\ValidationRule;
use Illuminate\Support\Facades\DB;

class ValidationService
{
    /**
     * Validate station data against rules
     * 
     * @param string $plantId
     * @param string $stationName
     * @param array $parameters
     * @return array ['valid' => bool, 'errors' => array, 'flagged_params' => array]
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

            if (!$rule) {
                $errors[] = "Parameter '{$paramName}' tidak memiliki aturan validasi untuk stasiun '{$stationName}'.";
                continue;
            }

            // Validate based on data type
            if ($rule->data_type === 'enum') {
                $allowedValues = explode(',', $rule->allowed_values);
                if (!in_array($value, $allowedValues)) {
                    $errors[] = $rule->getErrorMessage($value);
                    $flaggedParams[] = $paramName;
                }
            } else {
                // Numeric validation
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
     * @param \Carbon\Carbon $timestampKirim
     * @param \Carbon\Carbon $timestampServer
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
     * @param string $plantId
     * @param string $stationName
     * @param array $data
     * @param \Carbon\Carbon $messageDate Telegram message.date
     * @return array ['success' => bool, 'data' => array|null, 'errors' => array]
     */
    public function validateAndPrepare(string $plantId, string $stationName, array $data, $messageDate): array
    {
        // Validate parameters
        $validation = $this->validate($plantId, $stationName, $data);

        if (!$validation['valid']) {
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
     * @param string $stationName
     * @return string Model class name
     * @throws \Exception
     */
    public function getStationModel(string $stationName): string
    {
        $modelMap = [
            'timbang' => \App\Models\LogTimbang::class,
            'sortasi' => \App\Models\LogSortasi::class,
            'sterilizer' => \App\Models\LogSterilizer::class,
            'press' => \App\Models\LogPress::class,
            'klarifikasi' => \App\Models\LogKlarifikasi::class,
            'kernel' => \App\Models\LogKernel::class,
            'lab' => \App\Models\LogLab::class,
            'maintenance' => \App\Models\LogMaintenance::class,
        ];

        if (!isset($modelMap[$stationName])) {
            throw new \Exception("Stasiun '{$stationName}' tidak dikenal.");
        }

        return $modelMap[$stationName];
    }

    /**
     * Save station data with transaction
     * 
     * @param string $stationName
     * @param array $data
     * @return mixed
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
