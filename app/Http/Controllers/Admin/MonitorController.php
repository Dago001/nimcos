<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\Monitoring\ElectionStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MonitorController extends Controller
{
    public function __construct(private readonly ElectionStatistics $stats) {}

    public function show(Election $election): View
    {
        return view('admin.monitor.show', [
            'election' => $election,
            'summary' => $this->stats->summary($election),
            'hourly' => $this->stats->hourlyActivity($election),
            'byCommand' => $this->stats->turnoutByCommand($election),
            'security' => $this->stats->securitySignals(),
        ]);
    }

    public function stats(Election $election): JsonResponse
    {
        return response()->json([
            'summary' => $this->stats->summary($election),
            'hourly' => $this->stats->hourlyActivity($election),
            'byCommand' => $this->stats->turnoutByCommand($election),
            'security' => $this->stats->securitySignals(),
        ]);
    }
}
