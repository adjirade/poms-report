<?php

// Helper audit UI: buat (default) atau hapus (--remove) 2 user audit.
// User audit = manager + developer khusus untuk menjalankan ui-audit.mjs.
//
//   php scripts/ui-audit/audit-users.php            # buat / perbarui
//   php scripts/ui-audit/audit-users.php --remove   # hapus setelah audit

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$managerPhone = '628999000900';
$developerPhone = '628999000901';
$pass = (string) ($_ENV['AUDIT_PASS'] ?? getenv('AUDIT_PASS') ?: 'AuditUi2026!');

if (in_array('--remove', $argv, true)) {
    $n = User::whereIn('phone_number', [$managerPhone, $developerPhone])->delete();
    echo "Dihapus: {$n} user audit\n";

    exit(0);
}

User::updateOrCreate(
    ['phone_number' => $managerPhone],
    ['name' => 'Audit Manager', 'role' => 'manager', 'plant_id' => 'PKS_01', 'status' => 'active', 'password' => Hash::make($pass)]
);
User::updateOrCreate(
    ['phone_number' => $developerPhone],
    ['name' => 'Audit Dev', 'role' => 'developer', 'plant_id' => 'PKS_01', 'status' => 'active', 'password' => Hash::make($pass)]
);

echo "OK — user audit siap ({$managerPhone} manager, {$developerPhone} developer)\n";
