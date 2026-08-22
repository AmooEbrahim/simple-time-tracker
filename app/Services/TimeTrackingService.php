<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimeTrackingService
{
    const PER_PAGE = 50;

    private const USER_TZ = 'Asia/Tehran';

    public function startTimer(Project|int|null $project, ?string $description): TimeEntry
    {
        return TimeEntry::create([
            'user_id' => auth()->id(),
            'project_id' => $project instanceof Project ? $project->id : $project,
            'description' => $description,
            'started_at' => now(),
        ]);
    }

    public function stopTimer(TimeEntry $timeEntry, ?Carbon $endedAt = null): TimeEntry
    {
        $endedAt ??= now();

        $timeEntry->stop($endedAt);

        return $timeEntry->fresh();
    }

    public function getRunningTimer(): ?TimeEntry
    {
        return TimeEntry::where('user_id', auth()->id())
            ->whereNull('ended_at')
            ->with('project', 'tags')
            ->latest('started_at')
            ->first();
    }

    public function getEntriesForDate(Carbon $date): Collection
    {
        $start = $date->copy()->startOfDay()->timezone('UTC');
        $end = $date->copy()->endOfDay()->timezone('UTC');

        return TimeEntry::where('user_id', auth()->id())
            ->with('project', 'tags')
            ->whereBetween('started_at', [$start, $end])
            ->orderByDesc('started_at')
            ->get();
    }

    public function getEntriesPaginated(?string $cursor, ?TimeEntry $runningTimer): array
    {
        $query = TimeEntry::where('user_id', auth()->id())
            ->with('project', 'tags')
            ->whereNotNull('ended_at')
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        if ($cursor) {
            $cursorParts = explode('|', $cursor);
            if (count($cursorParts) === 2) {
                $query->where(function ($q) use ($cursorParts) {
                    $q->where('started_at', '<', $cursorParts[0])
                        ->orWhere(function ($q2) use ($cursorParts) {
                            $q2->where('started_at', $cursorParts[0])
                                ->where('id', '<', $cursorParts[1]);
                        });
                });
            }
        }

        $entries = $query->limit(self::PER_PAGE + 1)->get();

        $hasMore = $entries->count() > self::PER_PAGE;
        if ($hasMore) {
            $entries->pop();
        }

        $nextCursor = null;
        if ($hasMore && $entries->isNotEmpty()) {
            $last = $entries->last();
            $nextCursor = $last->started_at->format('Y-m-d H:i:s').'|'.$last->id;
        }

        $groupedDays = $this->groupEntriesByDay($entries, $runningTimer);

        return [$groupedDays, $hasMore, $nextCursor];
    }

    protected function groupEntriesByDay(Collection $entries, ?TimeEntry $runningTimer): array
    {
        $allEntries = $entries;
        if ($runningTimer) {
            $allEntries = collect([$runningTimer])->merge($entries);
        }

        $grouped = $allEntries->groupBy(fn ($e) => $e->started_at->copy()->timezone(self::USER_TZ)->format('Y-m-d'));

        $days = [];
        $previousWeek = null;
        $weekTotalsCache = [];

        foreach ($grouped as $date => $dayEntries) {
            $carbonDate = Carbon::parse($date, self::USER_TZ);
            $weekStart = $this->getWeekStart($carbonDate);
            $weekEnd = $weekStart->copy()->addDays(6);
            $weekKey = $weekStart->format('Y-m-d');

            $showWeekHeader = $previousWeek === null || $weekKey !== $previousWeek;

            $weekTotalSeconds = null;
            if ($showWeekHeader) {
                if (! isset($weekTotalsCache[$weekKey])) {
                    $weekTotalsCache[$weekKey] = $this->getTotalSecondsForRange($weekStart, $weekEnd);
                }
                $weekTotalSeconds = $weekTotalsCache[$weekKey];
            }

            $days[] = [
                'date' => $date,
                'day_label' => $carbonDate->format('l, M j, Y'),
                'is_today' => $carbonDate->isToday(),
                'is_yesterday' => $carbonDate->isYesterday(),
                'entries' => $dayEntries->sortByDesc('started_at')->values(),
                'total_seconds' => $dayEntries->sum('duration_seconds'),
                'show_week_header' => $showWeekHeader,
                'week_start' => $weekStart->format('M j'),
                'week_end' => $weekEnd->format('M j, Y'),
                'week_total_seconds' => $weekTotalSeconds,
            ];

            $previousWeek = $weekKey;
        }

        return $days;
    }

    public function getWeekStart(Carbon $date): Carbon
    {
        $dayOfWeek = $date->dayOfWeek;
        $diff = $dayOfWeek >= Carbon::SATURDAY
            ? $dayOfWeek - Carbon::SATURDAY
            : $dayOfWeek + 7 - Carbon::SATURDAY;

        return $date->copy()->subDays($diff)->startOfDay();
    }

    public function getTodayTotalSeconds(): int
    {
        $today = Carbon::now(self::USER_TZ);

        return $this->getTotalSecondsForRange($today, $today);
    }

    public function getThisWeekTotalSeconds(): int
    {
        $start = $this->getWeekStart(Carbon::now(self::USER_TZ));
        $end = $start->copy()->addDays(6);

        return $this->getTotalSecondsForRange($start, $end);
    }

    public function getEntriesForRange(Carbon $start, Carbon $end, ?array $projectIds = null): Collection
    {
        $startUtc = $start->copy()->startOfDay()->timezone('UTC');
        $endUtc = $end->copy()->endOfDay()->timezone('UTC');

        return TimeEntry::where('user_id', auth()->id())
            ->with('project', 'tags')
            ->whereBetween('started_at', [$startUtc, $endUtc])
            ->when($projectIds, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->orderByDesc('started_at')
            ->get();
    }

    public function getTotalSecondsForRange(Carbon $start, Carbon $end, ?array $projectIds = null): int
    {
        $startUtc = $start->copy()->startOfDay()->timezone('UTC');
        $endUtc = $end->copy()->endOfDay()->timezone('UTC');

        return TimeEntry::where('user_id', auth()->id())
            ->whereBetween('started_at', [$startUtc, $endUtc])
            ->when($projectIds, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->sum('duration_seconds');
    }

    public function getGroupedByProject(Carbon $start, Carbon $end, ?array $projectIds = null): Collection
    {
        $startUtc = $start->copy()->startOfDay()->timezone('UTC');
        $endUtc = $end->copy()->endOfDay()->timezone('UTC');

        return TimeEntry::where('user_id', auth()->id())
            ->with('project')
            ->whereBetween('started_at', [$startUtc, $endUtc])
            ->when($projectIds, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->get()
            ->groupBy('project_id')
            ->map(fn (Collection $entries, ?string $projectId) => [
                'project' => $entries->first()?->project,
                'total_seconds' => $entries->sum('duration_seconds'),
                'entry_count' => $entries->count(),
            ])
            ->sortByDesc('total_seconds')
            ->values();
    }

    public function getGroupedByDate(Carbon $start, Carbon $end, ?array $projectIds = null): Collection
    {
        $startUtc = $start->copy()->startOfDay()->timezone('UTC');
        $endUtc = $end->copy()->endOfDay()->timezone('UTC');

        $grouped = TimeEntry::where('user_id', auth()->id())
            ->whereBetween('started_at', [$startUtc, $endUtc])
            ->when($projectIds, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->get()
            ->groupBy(fn (TimeEntry $entry) => $entry->started_at->copy()->timezone(self::USER_TZ)->format('Y-m-d'))
            ->map(fn (Collection $entries, string $date) => [
                'date' => $date,
                'total_seconds' => $entries->sum('duration_seconds'),
                'entry_count' => $entries->count(),
            ])
            ->sortByDesc('date');

        // If empty, return empty collection
        if ($grouped->isEmpty()) {
            return $grouped->values();
        }

        $filled = collect();
        $current = Carbon::parse($grouped->keys()->last(), self::USER_TZ); // earliest date
        $last = Carbon::parse($grouped->keys()->first(), self::USER_TZ);   // latest date

        while ($current->lte($last)) {
            $dateKey = $current->format('Y-m-d');
            $filled->put($dateKey, $grouped->get($dateKey) ?? [
                'date' => $dateKey,
                'total_seconds' => 0,
                'entry_count' => 0,
            ]);
            $current->addDay();
        }

        return $filled->sortByDesc('date')->values();
    }

    public function getGroupedByDateOld(Carbon $start, Carbon $end, ?array $projectIds = null): Collection
    {
        return TimeEntry::where('user_id', auth()->id())
            ->whereBetween('started_at', [$start->startOfDay(), $end->endOfDay()])
            ->when($projectIds, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->get()
            ->groupBy(fn (TimeEntry $entry) => $entry->started_at->format('Y-m-d'))
            ->map(fn (Collection $entries, string $date) => [
                'date' => $date,
                'total_seconds' => $entries->sum('duration_seconds'),
                'entry_count' => $entries->count(),
            ])
            ->sortByDesc('date')
            ->values();
    }

    public function getGroupedByTag(Carbon $start, Carbon $end, ?array $projectIds = null): Collection
    {
        $startUtc = $start->copy()->startOfDay()->timezone('UTC');
        $endUtc = $end->copy()->endOfDay()->timezone('UTC');

        $entries = TimeEntry::where('user_id', auth()->id())
            ->with('tags')
            ->whereBetween('started_at', [$startUtc, $endUtc])
            ->when($projectIds, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->get();

        $tagMap = [];

        foreach ($entries as $entry) {
            if ($entry->tags->isEmpty()) {
                $key = 'no_tag';
                if (! isset($tagMap[$key])) {
                    $tagMap[$key] = ['tag' => null, 'total_seconds' => 0, 'entry_count' => 0];
                }
                $tagMap[$key]['total_seconds'] += $entry->duration_seconds;
                $tagMap[$key]['entry_count']++;
            } else {
                foreach ($entry->tags as $tag) {
                    $key = "tag_{$tag->id}";
                    if (! isset($tagMap[$key])) {
                        $tagMap[$key] = ['tag' => $tag, 'total_seconds' => 0, 'entry_count' => 0];
                    }
                    $tagMap[$key]['total_seconds'] += $entry->duration_seconds;
                    $tagMap[$key]['entry_count']++;
                }
            }
        }

        return collect(array_values($tagMap))
            ->sortByDesc('total_seconds')
            ->values();
    }

    public function getGroupedBySubProject(Carbon $start, Carbon $end, ?array $projectIds = null): Collection
    {
        $startUtc = $start->copy()->startOfDay()->timezone('UTC');
        $endUtc = $end->copy()->endOfDay()->timezone('UTC');

        $entries = TimeEntry::where('user_id', auth()->id())
            ->with('project.parent')
            ->whereBetween('started_at', [$startUtc, $endUtc])
            ->when($projectIds, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->get();

        $parentMap = [];

        foreach ($entries as $entry) {
            $project = $entry->project;
            if (! $project) {
                $key = 'no_project';
                if (! isset($parentMap[$key])) {
                    $parentMap[$key] = ['parent' => null, 'sub_projects' => [], 'total_seconds' => 0, 'entry_count' => 0];
                }
                $parentMap[$key]['total_seconds'] += $entry->duration_seconds;
                $parentMap[$key]['entry_count']++;

                continue;
            }

            $parentKey = $project->parent_id ? "parent_{$project->parent_id}" : "root_{$project->id}";
            $parent = $project->parent_id ? $project->parent : $project;

            if (! isset($parentMap[$parentKey])) {
                $parentMap[$parentKey] = [
                    'parent' => $parent,
                    'sub_projects' => [],
                    'total_seconds' => 0,
                    'entry_count' => 0,
                ];
            }

            $parentMap[$parentKey]['total_seconds'] += $entry->duration_seconds;
            $parentMap[$parentKey]['entry_count']++;

            $subKey = "sub_{$project->id}";
            if (! isset($parentMap[$parentKey]['sub_projects'][$subKey])) {
                $parentMap[$parentKey]['sub_projects'][$subKey] = [
                    'sub_project' => $project,
                    'total_seconds' => 0,
                    'entry_count' => 0,
                ];
            }
            $parentMap[$parentKey]['sub_projects'][$subKey]['total_seconds'] += $entry->duration_seconds;
            $parentMap[$parentKey]['sub_projects'][$subKey]['entry_count']++;
        }

        return collect(array_values($parentMap))
            ->map(function ($group) {
                $group['sub_projects'] = collect($group['sub_projects'])->values()->sortByDesc('total_seconds')->values();

                return $group;
            })
            ->sortByDesc('total_seconds')
            ->values();
    }
}
