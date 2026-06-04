<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create the Master Admin
        User::updateOrCreate(
            ['email' => 'admin@lms.com'],
            [
                'name' => 'System Admin',
                'role' => 'admin',
                'status' => 'active',
                'password' => Hash::make('password'), 
                'email_verified_at' => now(),
            ]
        );

        // 2. Create a Test Teacher
        User::updateOrCreate(
            ['email' => 'teacher@lms.com'],
            [
                'name' => 'Demo Teacher',
                'role' => 'teacher',
                'status' => 'active',
                'school_id' => 'TCH-1001',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // 3. Create a Test Student
        User::updateOrCreate(
            ['email' => 'student@lms.com'],
            [
                'name' => 'Demo Student',
                'role' => 'student',
                'status' => 'active',
                'school_id' => 'STU-2001',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}