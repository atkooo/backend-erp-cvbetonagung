<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Roles
        $adminRole = Role::firstOrCreate(
            ['code' => 'admin'],
            ['name' => 'Super Administrator', 'description' => 'Full access to all modules']
        );

        $hrdRole = Role::firstOrCreate(
            ['code' => 'hrd'],
            ['name' => 'HR Manager', 'description' => 'Access to HR and Payroll modules']
        );

        $employeeRole = Role::firstOrCreate(
            ['code' => 'employee'],
            ['name' => 'Karyawan', 'description' => 'Access to Employee Self Service']
        );

        // 2. Create Super Admin User
        User::firstOrCreate(
            ['email' => 'admin@betonagung.co.id'],
            [
                'role_id' => $adminRole->id,
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 3. Create Users for all Employees
        $employees = Employee::all();

        foreach ($employees as $emp) {
            if (! $emp->user_id) {
                // Determine role based on role_name or default to employee
                $roleId = $employeeRole->id;
                if (stripos($emp->role_name, 'Manager') !== false || stripos($emp->role_name, 'HRD') !== false) {
                    $roleId = $hrdRole->id;
                }

                // Create user
                // Using employee_number as prefix for email if email doesn't exist
                $email = strtolower(str_replace(' ', '.', $emp->name)).'@betonagung.co.id';

                // Ensure unique email
                $baseEmail = $email;
                $counter = 1;
                while (User::where('email', $email)->exists()) {
                    $email = str_replace('@', $counter.'@', $baseEmail);
                    $counter++;
                }

                $user = User::create([
                    'role_id' => $roleId,
                    'name' => $emp->name,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'status' => 'active',
                ]);

                // Update employee with user_id
                $emp->update(['user_id' => $user->id]);
            }
        }
    }
}
