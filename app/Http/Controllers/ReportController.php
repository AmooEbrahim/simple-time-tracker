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

        [$start, $end] = $this->resolveRange($request, $range, $tz);

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

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request, string $range, string $tz): array
    {
        $explicitStart = $this->parseIsoDate($request->query('start'), $tz);
        $explicitEnd = $this->parseIsoDate($request->query('end'), $tz);

        if ($range !== 'custom' && $explicitStart && $explicitEnd) {
            return [$explicitStart, $explicitEnd];
        }

        $calendar = $request->user()?->calendarSettings()['calendar'] ?? 'jalali';
        $weekStart = $this->timeTrackingService->getWeekStart(Carbon::today($tz));

        return match ($range) {
            'today' => [now($tz), now($tz)],
            'week' => [$weekStart, $weekStart->copy()->addDays(6)],
            'month' => $this->calendarRange($calendar, false, $tz),
            'year' => $this->calendarRange($calendar, true, $tz),
            'custom' => [
                $explicitStart ?? now($tz)->copy()->subDays(30),
                $explicitEnd ?? now($tz),
            ],
        };
    }

    /**
     * Resolve month or year boundaries in the user's display calendar.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function calendarRange(string $calendar, bool $fullYear, string $tz): array
    {
        $calendarType = match ($calendar) {
            'jalali' => 'persian',
            'hijri' => 'islamic-umalqura',
            default => null,
        };

        if ($calendarType === null || ! class_exists(\IntlCalendar::class)) {
            return $this->gregorianRange($fullYear, $tz);
        }

        try {
            $intl = \IntlCalendar::createInstance(
                new \DateTimeZone($tz),
                "en_US@calendar={$calendarType}"
            );

            if (! $intl) {
                return $this->gregorianRange($fullYear, $tz);
            }

            $intl->setTime((float) now($tz)->getTimestampMs());

            $year = $intl->get(\IntlCalendar::FIELD_YEAR);
            $startMonth = $fullYear ? 0 : $intl->get(\IntlCalendar::FIELD_MONTH);
            $endMonth = $fullYear ? 11 : $startMonth;

            $intl->set($year, $startMonth, 1, 0, 0, 0);
            $intl->set(\IntlCalendar::FIELD_MILLISECOND, 0);
            $start = Carbon::createFromTimestampMs((int) $intl->getTime(), $tz);

            $intl->set($year, $endMonth, 1, 0, 0, 0);
            $lastDay = $intl->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_MONTH);
            $intl->set($year, $endMonth, $lastDay, 23, 59, 59);
            $intl->set(\IntlCalendar::FIELD_MILLISECOND, 0);
            $end = Carbon::createFromTimestampMs((int) $intl->getTime(), $tz);

            return [$start, $end];
        } catch (\Throwable) {
            return $this->gregorianRange($fullYear, $tz);
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function gregorianRange(bool $fullYear, string $tz): array
    {
        return $fullYear
            ? [now($tz)->startOfYear(), now($tz)->endOfYear()]
            : [now($tz)->startOfMonth(), now($tz)->endOfMonth()];
    }

    private function parseIsoDate(mixed $value, string $tz): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $year = (int) substr($value, 0, 4);
        if ($year < 1900 || $year > 2100) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, $tz);
        } catch (\Throwable) {
            return null;
        }

        return $date instanceof Carbon && $date->format('Y-m-d') === $value ? $date : null;
    }

    public function export(Request $request): StreamedResponse
    {
        $tz = 'Asia/Tehran';
        $start = $this->parseIsoDate($request->query('start'), $tz) ?? now($tz)->startOfMonth();
        $end = $this->parseIsoDate($request->query('end'), $tz) ?? now($tz)->endOfMonth();
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

        return response()->streamDownload(function () use ($entries, $tz) {
            $fh = fopen('php://output', 'w');

            fputcsv($fh, ['Description', 'Project', 'Project ID', 'Date', 'Start Time', 'End Time', 'Duration (minutes)', 'Tags']);

            foreach ($entries as $entry) {
                $startedAt = $entry->started_at->copy()->timezone($tz);
                $endedAt = $entry->ended_at?->copy()->timezone($tz);

                fputcsv($fh, [
                    $entry->description ?? '',
                    $entry->project?->name ?? 'No Project',
                    $entry->project_id ?? '',
                    $startedAt->format('Y-m-d'),
                    $startedAt->format('H:i'),
                    $endedAt?->format('H:i') ?? '',
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
