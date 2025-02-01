<?php

namespace App\Console;

use App\Jobs\PublishScheduledArticle;
use App\Models\Article;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            $articles = Article::where('is_published', false)
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<=', now())
                ->get();

            foreach ($articles as $article) {
                PublishScheduledArticle::dispatch($article);
            }
        })->everyMinute();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
