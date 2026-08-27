<?php

namespace App\Console\Commands;

use App\Services\SubscriptionSchedulerService;
use Illuminate\Console\Command;

class ProcessSubscriptionBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process-billing {--date= : The target billing date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process due subscriptions and generate pending planned expenses idempotently';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionSchedulerService $scheduler): int
    {
        $targetDate = $this->option('date');
        $this->info('Processing subscription billing'.($targetDate ? " for date: {$targetDate}" : '...'));

        $result = $scheduler->processBilling($targetDate);

        $this->info("Completed. Processed: {$result['processed']}, Created Planned Expenses: {$result['created']}");

        return Command::SUCCESS;
    }
}
