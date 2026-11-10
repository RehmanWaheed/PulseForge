<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class DispatchMonitorChecksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitors:dispatch-checks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch health check jobs for all monitors currently due for inspection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dispatchedCount = 0;

        /** @var Builder<Monitor> $query */
        $query = Monitor::query()
            ->withoutGlobalScope(TenantScope::class)
            ->dueForCheck();

        $query->chunkById(100, function ($monitors) use (&$dispatchedCount): void {
            foreach ($monitors as $monitor) {
                CheckMonitorJob::dispatch($monitor);
                $dispatchedCount++;
            }
        });

        $this->info(sprintf('Dispatched health checks for %d due monitor(s).', $dispatchedCount));

        return Command::SUCCESS;
    }
}
