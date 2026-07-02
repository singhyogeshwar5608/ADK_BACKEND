<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Support\IdGenerator;
use App\Support\Tree;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset {--email=admin@mlm.com} {--password=Admin@123}';
    protected $description = 'Reset or create admin user with given email and password';

    public function handle(): int
    {
        $email = $this->option('email');
        $password = $this->option('password');

        $admin = Member::where('role', 'ADMIN')->first();

        if ($admin) {
            $admin->password_hash = Hash::make($password);
            $admin->email = $email;
            $admin->save();
            $this->info("Admin password reset successfully!");
            $this->info("Email: {$admin->email}");
            $this->info("Password: {$password}");
        } else {
            $serialNo = (Member::max('serial_no') ?? 0) + 1;
            $admin = Member::create([
                'member_id' => IdGenerator::memberId(),
                'serial_no' => $serialNo,
                'sponsor_id' => null,
                'leg' => null,
                'placement_path' => Tree::rootPath(),
                'depth' => 0,
                'full_name' => 'Admin',
                'email' => $email,
                'phone' => null,
                'role' => 'ADMIN',
                'password_hash' => Hash::make($password),
                'status' => 'ACTIVE',
                'referral_code' => 'ADMIN001',
            ]);
            $this->info("Admin user created successfully!");
            $this->info("Email: {$email}");
            $this->info("Password: {$password}");
            $this->info("Member ID: {$admin->member_id}");
        }

        return self::SUCCESS;
    }
}
