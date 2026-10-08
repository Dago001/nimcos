<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\VoterImport;
use App\Services\Voters\VoterImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ProcessVoterImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly string $importId, public readonly string $userId) {}

    public function handle(VoterImportService $service): void
    {
        $import = VoterImport::query()->find($this->importId);
        if (! $import) {
            return;
        }

        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        $service->process($import, $user);
    }
}
