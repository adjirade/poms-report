<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Operators cannot access web dashboard, developer always can
        Gate::define('access-web', function (User $user) {
            // Developer role ALWAYS has web access
            if ($user->role === 'developer') {
                return true;
            }
            // Other roles: check canAccessWeb() method
            return $user->canAccessWeb();
        });

        // Asisten can view and verify their department data
        Gate::define('view-department-data', function (User $user) {
            return in_array($user->role, ['asisten', 'askep', 'manager', 'hq_admin', 'developer']);
        });

        // Asisten can verify data
        Gate::define('verify-data', function (User $user) {
            return in_array($user->role, ['asisten', 'askep', 'manager']);
        });

        // Askep and Manager can access full dashboard
        Gate::define('access-full-dashboard', function (User $user) {
            return in_array($user->role, ['askep', 'manager', 'hq_admin', 'developer']);
        });

        // Manager can approve reports
        Gate::define('approve-reports', function (User $user) {
            return in_array($user->role, ['manager', 'developer']);
        });

        // HQ Admin can view all plants
        Gate::define('view-all-plants', function (User $user) {
            return in_array($user->role, ['hq_admin', 'developer']);
        });

        // Developer can access system settings
        Gate::define('access-settings', function (User $user) {
            return $user->role === 'developer';
        });

        // Export functionality
        Gate::define('export-data', function (User $user) {
            return in_array($user->role, ['askep', 'manager', 'hq_admin', 'developer']);
        });

        // View flagged records
        Gate::define('view-flagged-records', function (User $user) {
            return in_array($user->role, ['asisten', 'askep', 'manager', 'developer']);
        });

        // Edit validation rules
        Gate::define('edit-validation-rules', function (User $user) {
            return in_array($user->role, ['manager', 'developer']);
        });
    }
}
