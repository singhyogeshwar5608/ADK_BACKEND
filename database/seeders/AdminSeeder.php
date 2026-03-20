<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Support\IdGenerator;
use App\Support\Tree;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@mlm.com';

        if (Member::where('email', $email)->exists()) {
            return;
        }

        Member::create([
            'member_id' => IdGenerator::memberId(),
            'sponsor_id' => null,
            'leg' => null,
            'placement_path' => Tree::rootPath(),
            'depth' => 0,
            'full_name' => 'Admin',
            'email' => $email,
            'phone' => null,
            'role' => 'ADMIN',
            'password_hash' => Hash::make('Admin@123'),
            'status' => 'ACTIVE',
        ]);
    }
}
