<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Member;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Check if admin already exists
        $existingAdmin = Member::where('email', 'admin@adk.com')->first();
        
        if (!$existingAdmin) {
            Member::create([
                'name' => 'Admin User',
                'email' => 'admin@adk.com',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
                'member_id' => 'ADMIN001',
                'phone' => '1234567890',
                'status' => 'active',
                'wallet_balance' => 0,
                'wallet_total_earned' => 0,
                'bv_total' => 0,
                'bv_left_leg' => 0,
                'bv_right_leg' => 0,
                'bv_carry_forward_left' => 0,
                'bv_carry_forward_right' => 0,
                'total_matched_bv' => 0,
                'sponsor_id' => null,
                'placement_id' => null,
                'position' => null,
                'level' => 0,
            ]);
            
            $this->command->info('Admin user created successfully!');
            $this->command->info('Email: admin@adk.com');
            $this->command->info('Password: admin123');
        } else {
            $this->command->info('Admin user already exists!');
        }
    }
}
