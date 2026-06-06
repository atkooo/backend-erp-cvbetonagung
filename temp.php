<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Create Karyawan Biasa
$emp = Employee::firstOrCreate(
    ['employee_number' => 'EMP-TEST-01'],
    [
        'name' => 'Budi Santoso (Karyawan)',
        'role_name' => 'Operator',
        'department' => 'Produksi',
        'phone' => '081234567890',
        'employee_type' => 'Tetap',
        'status' => 'active',
    ]
);

$seeder = new AuthSeeder;
$seeder->run();

$u = User::find($emp->user_id);
if ($u) {
    $u->email = 'budi@betonagung.com';
    $u->save();
}

echo "Berhasil! Gunakan akun berikut untuk login sebagai Karyawan biasa:\n";
echo "Email: {$u->email}\n";
echo "Password: password\n";
