<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // view()->share('setting',
        //     Setting::where('setting_id', 1)->first());

        // $setting = DB::table('settings')->where('setting_id', 1)->first();

        // if ($setting) {
        //     Config::set('mail.mailers.smtp.transport', $setting->mail_mailer ?? 'smtp');
        //     Config::set('mail.mailers.smtp.host', $setting->mail_host ?? 'smtp.mailgun.org');
        //     Config::set('mail.mailers.smtp.port', $setting->mail_port ?? 2525);
        //     Config::set('mail.mailers.smtp.username', $setting->mail_username ?? null);
        //     Config::set('mail.mailers.smtp.password', $setting->mail_password ?? null);
        //     Config::set('mail.mailers.smtp.encryption', $setting->mail_encryption ?? 'tls');
        // }

    }
}
