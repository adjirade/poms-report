<?php

namespace App\Models;

use App\Support\StationLogDepartmentTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, StationLogDepartmentTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone_number',
        'telegram_user_id',
        'telegram_notif_enabled',
        'telegram_notif_flagged',
        'telegram_notif_verified',
        'password',
        'role',
        'department',
        'plant_id',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'telegram_notif_enabled' => 'boolean',
            'telegram_notif_flagged' => 'boolean',
            'telegram_notif_verified' => 'boolean',
        ];
    }

    /**
     * Apakah user ini ingin menerima notifikasi Telegram untuk jenis event
     * tertentu ('flagged' | 'verified').
     */
    public function wantsTelegramNotification(string $event = 'flagged'): bool
    {
        if (config('telegram.notifications.enabled', true) === false) {
            return false;
        }

        if (! $this->telegram_notif_enabled) {
            return false;
        }

        return match ($event) {
            'flagged' => (bool) $this->telegram_notif_flagged,
            'verified' => (bool) $this->telegram_notif_verified,
            default => true,
        };
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is operator
     */
    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    /**
     * Check if user is asisten
     */
    public function isAsisten(): bool
    {
        return $this->role === 'asisten';
    }

    /**
     * Check if user is askep or higher
     */
    public function isAskepOrHigher(): bool
    {
        return in_array($this->role, ['askep', 'manager', 'hq_admin', 'developer']);
    }

    /**
     * Check if user can access web dashboard
     */
    public function canAccessWeb(): bool
    {
        return $this->role !== 'operator';
    }

    /**
     * Get the station keys this user may access on the web dashboard.
     * Returns null when the user may access all stations.
     */
    public function allowedStations(): ?array
    {
        if (! $this->canAccessWeb()) {
            return [];
        }

        // Askep and above may access all stations
        if ($this->isAskepOrHigher()) {
            return null;
        }

        // Asisten is limited to their department's stations (satu sumber kebenaran:
        // StationLogDepartmentTrait, dipakai juga oleh Livewire & Telegram job)
        if ($this->role === 'asisten') {
            return $this->getDepartmentStations((string) $this->department);
        }

        return null;
    }

    /**
     * Get all station logs created by this user
     */
    public function logTimbang()
    {
        return $this->hasMany(LogTimbang::class);
    }

    public function logSortasi()
    {
        return $this->hasMany(LogSortasi::class);
    }

    public function logSterilizer()
    {
        return $this->hasMany(LogSterilizer::class);
    }

    public function logPress()
    {
        return $this->hasMany(LogPress::class);
    }

    public function logKlarifikasi()
    {
        return $this->hasMany(LogKlarifikasi::class);
    }

    public function logKernel()
    {
        return $this->hasMany(LogKernel::class);
    }

    public function logLab()
    {
        return $this->hasMany(LogLab::class);
    }

    public function logMaintenance()
    {
        return $this->hasMany(LogMaintenance::class);
    }

    /**
     * Count of logs verified by this user across all stations.
     */
    public function verifiedLogsCount(): int
    {
        $count = 0;

        foreach ([
            LogTimbang::class,
            LogSortasi::class,
            LogSterilizer::class,
            LogPress::class,
            LogKlarifikasi::class,
            LogKernel::class,
            LogLab::class,
            LogMaintenance::class,
        ] as $modelClass) {
            $count += $modelClass::where('verified_by', $this->id)->count();
        }

        return $count;
    }
}
