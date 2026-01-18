<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'make:admin {userId? : ID of an existing user to assign a role to}';

    protected $description = 'Create admin user or assign role to an existing user';

    public function handle(): int
    {
        // If userId is provided, assign role to existing user
        $userId = $this->argument('userId');

        if ($userId) {
            $user = User::findOrFail($userId);

            // Ensure role exists
            $roleName = $this->choice('Select role to assign', ['super_admin', 'admin', 'editor'], 0);

            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $user->assignRole($roleName);

            $this->info("✅ Role '{$roleName}' assigned successfully to user #{$user->id} ({$user->email}).");

            return self::SUCCESS;
        }

        // Otherwise, create the first super_admin user (one-time)
        if (User::role('super_admin')->exists()) {
            $this->warn('⚠️ A super_admin user already exists. This command can only be run once without userId.');
            $this->line('Tip: You can assign roles to existing users by running: php artisan make:admin {userId}');

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

        // Ensure role exists
        Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        // Assign role by name (simpler)
        $user->assignRole('super_admin');

        $this->info("✅ Admin user created (ID: {$user->id}) and assigned the 'super_admin' role successfully.");

        return self::SUCCESS;
    }
}
