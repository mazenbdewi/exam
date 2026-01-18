<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class CreateRoles extends Command
{
    // اسم الأمر الذي سنشغله عبر Artisan
    protected $signature = 'make:roles';

    // وصف الأمر
    protected $description = 'Create specific roles like reviewer and researcher';

    public function handle(): int
    {
        // تحقق من وجود جدول roles أولاً
        if (! Schema::hasTable('roles')) {
            $this->error('❌ Roles table does not exist. Run migrations first.');

            return self::FAILURE;
        }

        // الأدوار التي تريد إنشاؤها
        $roles = ['reviewer', 'researcher'];

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $this->info("✅ Role '{$role->name}' is ready.");
        }

        $this->info('🎉 All roles have been created or already exist.');

        return self::SUCCESS;
    }
}
