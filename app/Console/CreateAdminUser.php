<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'make:admin';

    protected $description = 'Create the first admin user and assign super_admin role';

    public function handle(): int
    {
        // Check if a super_admin already exists
        if (User::role('super_admin')->exists()) {
            $this->warn('⚠️ A super_admin user already exists. This command can only be run once.');

            return self::FAILURE;
        }

        // Ask for user input
        $name = $this->ask('Enter name');
        $email = $this->ask('Enter email');
        $password = $this->secret('Enter password');

        // Check if the email already exists
        if (User::where('email', $email)->exists()) {
            $this->error('❌ This email is already in use.');

            return self::FAILURE;
        }

        // Create the user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
        ]);

        // Create the role if it doesn't exist
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        // Assign the role
        $user->assignRole($role);

        $this->info('✅ Admin user created and assigned the super_admin role successfully.');

        return self::SUCCESS;
    }
}
