<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly TimeTrackingService $timeTrackingService
    ) {}

    public function __invoke(Request $request): Response
    {
        $range = $request->get('range', 'week');
        $projectId = $request->get('project_id');

        [$start, $end] = match ($range) {
            'today' => [now(), now()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            'custom' => [
                $request->has('start') ? Carbon::parse($request->start) : now()->subDays(30),
                $request->has('end') ? Carbon::parse($request->end) : now(),
            ],
        };

        $totalSeconds = $this->timeTrackingService->getTotalSecondsForRange($start, $end, $projectId);
        $groupedByProject = $this->timeTrackingService->getGroupedByProject($start, $end, $projectId);
        $groupedByDate = $this->timeTrackingService->getGroupedByDate($start, $end, $projectId);
        $groupedByTag = $this->timeTrackingService->getGroupedByTag($start, $end, $projectId);
        $projects = auth()->user()->projects()->latest()->get();

        return Inertia::render('Reports/Index', [
            'range' => $range,
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'selectedProjectId' => $projectId,
            'totalSeconds' => $totalSeconds,
            'groupedByProject' => $groupedByProject,
            'groupedByDate' => $groupedByDate,
            'groupedByTag' => $groupedByTag,
            'projects' => $projects,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $start = $request->has('start') ? Carbon::parse($request->start) : now()->startOfMonth();
        $end = $request->has('end') ? Carbon::parse($request->end) : now()->endOfMonth();
        $projectId = $request->get('project_id');

        $entries = TimeEntry::where('user_id', auth()->id())
            ->with('project')
            ->whereBetween('started_at', [$start->startOfDay(), $end->endOfDay()])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('started_at')
            ->get();

        $filename = "time-report-{$start->format('Y-m-d')}-to-{$end->format('Y-m-d')}.xlsx";

        return response()->streamDownload(function () use ($entries) {
            $fh = fopen('php://output', 'w');

            fputcsv($fh, ['Description', 'Project', 'Date', 'Start Time', 'End Time', 'Duration (minutes)']);

            foreach ($entries as $entry) {
                fputcsv($fh, [
                    $entry->description ?? '',
                    $entry->project?->name ?? 'No Project',
                    $entry->started_at->format('Y-m-d'),
                    $entry->started_at->format('H:i'),
                    $entry->ended_at?->format('H:i') ?? '',
                    round($entry->duration_seconds / 60, 1),
                ]);
            }

            fclose($fh);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
