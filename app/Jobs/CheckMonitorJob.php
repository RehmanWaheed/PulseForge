<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Incidents\ProcessMonitorFailureAction;
use App\Actions\Incidents\ProcessMonitorRecoveryAction;
use App\Actions\Monitors\PerformMonitorCheckAction;
use App\Events\MonitorCheckedEvent;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Monitor $monitor
    ) {
        $this->onQueue('monitoring');
    }

    /**
     * Execute the monitor health check and coordinate state transitions.
     */
    public function handle(
        PerformMonitorCheckAction $checkAction,
        ProcessMonitorFailureAction $failureAction,
        ProcessMonitorRecoveryAction $recoveryAction
    ): void {
        $resultDTO = $checkAction($this->monitor);

        MonitorCheckedEvent::dispatch($this->monitor, $resultDTO);

        if ($resultDTO->isSuccessful) {
            $recoveryAction($this->monitor, $resultDTO);
        } else {
            $failureAction($this->monitor, $resultDTO);
        }
    }
}
