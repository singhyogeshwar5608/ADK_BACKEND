<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Support\IdGenerator;
use App\Support\Tree;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Limited admin-panel staff user (sign in with "Login as admin" unchecked).
 */
class MemberPortalSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'member@adk.com';

        if (Member::where('email', $email)->exists()) {
            return;
        }

        Member::create([
            'member_id' => IdGenerator::memberId(),
            'sponsor_id' => null,
            'leg' => null,
            'placement_path' => Tree::rootPath(),
            'depth' => 0,
            'full_name' => 'Panel Staff',
            'email' => $email,
            'phone' => null,
            'role' => 'MEMBER',
            'password_hash' => Hash::make('member123'),
            'status' => 'ACTIVE',
        ]);
    }
}
