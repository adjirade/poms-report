<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone_number',
        'telegram_user_id',
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
        ];
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
        if (!$this->canAccessWeb()) {
            return [];
        }

        // Askep and above may access all stations
        if ($this->isAskepOrHigher()) {
            return null;
        }

        // Asisten is limited to their department's stations
        if ($this->role === 'asisten') {
            return match ($this->department) {
                'proses' => ['timbang', 'sortasi', 'sterilizer', 'press', 'klarifikasi', 'kernel'],
                'maintenance' => ['maintenance'],
                'lab' => ['lab'],
                default => [],
            };
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
