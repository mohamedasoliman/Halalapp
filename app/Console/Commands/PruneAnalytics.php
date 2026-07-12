<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use Illuminate\Console\Command;

class PruneAnalytics extends Command
{
    protected $signature = 'analytics:prune {--days=90 : Raw-event retention period}';

    protected $description = 'Delete raw analytics events after their daily summaries are retained';

    public function handle(): int
    {
        $days = max(30, min(365, (int) $this->option('days')));
        $deleted = AnalyticsEvent::where('occurred_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$deleted} raw analytics events older than {$days} days.");

        return self::SUCCESS;
    }
}
