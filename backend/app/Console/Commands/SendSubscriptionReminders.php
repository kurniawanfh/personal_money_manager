<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendSubscriptionReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-reminders {--date= : The target evaluation date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch idempotent H-3 and H-1 subscription renewal push reminders';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $targetDate = $this->option('date');
        $this->info('Evaluating subscription reminders'.($targetDate ? " for date: {$targetDate}" : '...'));

        $result = $notificationService->dispatchSubscriptionReminders($targetDate);

        $this->info("Completed. Dispatched: {$result['dispatched']}, Skipped (Already Sent): {$result['skipped']}");

        return Command::SUCCESS;
    }
}
