<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;
use App\Support\IdGenerator;

class ReferralTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users with member_id as referral codes
        $testUsers = [
            [
                'name' => 'Test User One',
                'email' => 'test1@example.com',
                'member_id' => 'TEST123',
                'phone' => '1234567890',
            ],
            [
                'name' => 'Test User Two', 
                'email' => 'test2@example.com',
                'member_id' => 'DEMO456',
                'phone' => '0987654321',
            ],
            [
                'name' => 'Test User Three',
                'email' => 'test3@example.com', 
                'member_id' => 'SAMPLE789',
                'phone' => '5555555555',
            ],
        ];

        foreach ($testUsers as $userData) {
            $member = Member::create([
                'member_id' => $userData['member_id'],
                'full_name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'password_hash' => bcrypt('password123'),
                'status' => 'ACTIVE',
                'role' => 'MEMBER',
                'sponsor_id' => null,
                'leg' => 'LEFT',
                'placement_path' => '/',
                'depth' => 0,
            ]);

            echo "Created test user: {$userData['name']} with member_id (referral code): {$userData['member_id']}\n";
        }

        echo "\nTest Referral Links:\n";
        $base = rtrim((string) config('app.signup_url'), '/');
        foreach ($testUsers as $userData) {
            echo "{$base}?ref={$userData['member_id']}\n";
        }
    }
}
