<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly TimeTrackingService $timeTrackingService
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $filterKeys = ['range', 'project_id', 'start', 'end'];
        $hasFilters = collect($filterKeys)->some(fn ($key) => $request->has($key));

        if ($hasFilters) {
            $request->session()->put('reports_filters', $request->only($filterKeys));
        } else {
            $saved = $request->session()->get('reports_filters');
            if ($saved && ! empty(array_filter($saved))) {
                return redirect()->route('reports', $saved);
            }
        }

        $range = $request->get('range', 'week');
        $projectId = $request->get('project_id');
        $tz = 'Asia/Tehran';

        $weekStart = $this->timeTrackingService->getWeekStart(Carbon::today($tz));

        [$start, $end] = match ($range) {
            'today' => [now($tz), now($tz)],
            'week' => [$weekStart, $weekStart->copy()->addDays(6)],
            'month' => [now($tz)->startOfMonth(), now($tz)->endOfMonth()],
            'year' => [now($tz)->startOfYear(), now($tz)->endOfYear()],
            'custom' => [
                $request->has('start') ? Carbon::parse($request->start, $tz) : now($tz)->subDays(30),
                $request->has('end') ? Carbon::parse($request->end, $tz) : now($tz),
            ],
        };

        $projectIds = null;
        if ($projectId) {
            $project = auth()->user()->projects()->find($projectId);
            if ($project) {
                $projectIds = $project->getAllDescendantIds();
            }
        }

        $totalSeconds = $this->timeTrackingService->getTotalSecondsForRange($start, $end, $projectIds);
        $groupedByProject = $this->timeTrackingService->getGroupedByProject($start, $end, $projectIds);
        $groupedByDate = $this->timeTrackingService->getGroupedByDate($start, $end, $projectIds);
        $groupedByTag = $this->timeTrackingService->getGroupedByTag($start, $end, $projectIds);
        $groupedBySubProject = $this->timeTrackingService->getGroupedBySubProject($start, $end, $projectIds);
        $projects = auth()->user()->projects()
            ->whereNull('parent_id')
            ->where('is_archived', false)
            ->with(['children' => fn ($q) => $q->where('is_archived', false)->orderBy('name')])
            ->latest()
            ->get();

        return Inertia::render('Reports/Index', [
            'range' => $range,
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'selectedProjectId' => $projectId,
            'totalSeconds' => $totalSeconds,
            'groupedByProject' => $groupedByProject,
            'groupedByDate' => $groupedByDate,
            'groupedByTag' => $groupedByTag,
            'groupedBySubProject' => $groupedBySubProject,
            'projects' => $projects,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $tz = 'Asia/Tehran';
        $start = $request->has('start') ? Carbon::parse($request->start, $tz) : now($tz)->startOfMonth();
        $end = $request->has('end') ? Carbon::parse($request->end, $tz) : now($tz)->endOfMonth();
        $projectId = $request->get('project_id');

        $startUtc = $start->copy()->startOfDay()->timezone('UTC');
        $endUtc = $end->copy()->endOfDay()->timezone('UTC');

        $projectIds = null;
        if ($projectId) {
            $project = auth()->user()->projects()->find($projectId);
            if ($project) {
                $projectIds = $project->getAllDescendantIds();
            }
        }

        $entries = TimeEntry::where('user_id', auth()->id())
            ->with('project', 'tags')
            ->whereBetween('started_at', [$startUtc, $endUtc])
            ->when($projectIds, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->orderByDesc('started_at')
            ->get();

        $filename = "time-report-{$start->format('Y-m-d')}-to-{$end->format('Y-m-d')}.xlsx";

        return response()->streamDownload(function () use ($entries) {
            $fh = fopen('php://output', 'w');

            fputcsv($fh, ['Description', 'Project', 'Project ID', 'Date', 'Start Time', 'End Time', 'Duration (minutes)', 'Tags']);

            foreach ($entries as $entry) {
                fputcsv($fh, [
                    $entry->description ?? '',
                    $entry->project?->name ?? 'No Project',
                    $entry->project_id ?? '',
                    $entry->started_at->format('Y-m-d'),
                    $entry->started_at->format('H:i'),
                    $entry->ended_at?->format('H:i') ?? '',
                    round($entry->duration_seconds / 60, 1),
                    $entry->tags->pluck('name')->implode(';'),
                ]);
            }

            fclose($fh);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
