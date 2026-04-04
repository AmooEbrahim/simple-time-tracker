<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimeTrackingService
{
    const PER_PAGE = 50;

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
        return TimeEntry::where('user_id', auth()->id())
            ->with('project', 'tags')
            ->whereDate('started_at', $date)
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
            $nextCursor = $last->started_at->format('Y-m-d H:i:s') . '|' . $last->id;
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

        $grouped = $allEntries->groupBy(fn ($e) => $e->started_at->format('Y-m-d'));

        $days = [];
        $previousWeek = null;

        foreach ($grouped as $date => $dayEntries) {
            $carbonDate = Carbon::parse($date);
            $weekStart = $this->getWeekStart($carbonDate);
            $weekEnd = $weekStart->copy()->addDays(6);

            $showWeekHeader = $previousWeek === null || $weekStart->format('Y-m-d') !== $previousWeek;

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
            ];

            $previousWeek = $weekStart->format('Y-m-d');
        }

        return $days;
    }

    protected function getWeekStart(Carbon $date): Carbon
    {
        $dayOfWeek = $date->dayOfWeek;
        $diff = $dayOfWeek >= Carbon::SATURDAY
            ? $dayOfWeek - Carbon::SATURDAY
            : $dayOfWeek + 7 - Carbon::SATURDAY;

        return $date->copy()->subDays($diff)->startOfDay();
    }

    public function getEntriesForRange(Carbon $start, Carbon $end, ?int $projectId = null): Collection
    {
        return TimeEntry::where('user_id', auth()->id())
            ->with('project', 'tags')
            ->whereBetween('started_at', [$start->startOfDay(), $end->endOfDay()])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('started_at')
            ->get();
    }

    public function getTotalSecondsForRange(Carbon $start, Carbon $end, ?int $projectId = null): int
    {
        return TimeEntry::where('user_id', auth()->id())
            ->whereBetween('started_at', [$start->startOfDay(), $end->endOfDay()])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->sum('duration_seconds');
    }

    public function getGroupedByProject(Carbon $start, Carbon $end, ?int $projectId = null): Collection
    {
        return TimeEntry::where('user_id', auth()->id())
            ->with('project')
            ->whereBetween('started_at', [$start->startOfDay(), $end->endOfDay()])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
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

    public function getGroupedByDate(Carbon $start, Carbon $end, ?int $projectId = null): Collection
    {
        return TimeEntry::where('user_id', auth()->id())
            ->whereBetween('started_at', [$start->startOfDay(), $end->endOfDay()])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
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

    public function getGroupedByTag(Carbon $start, Carbon $end, ?int $projectId = null): Collection
    {
        $entries = TimeEntry::where('user_id', auth()->id())
            ->with('tags')
            ->whereBetween('started_at', [$start->startOfDay(), $end->endOfDay()])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->get();

        $tagMap = [];

        foreach ($entries as $entry) {
            if ($entry->tags->isEmpty()) {
                $key = 'no_tag';
                if (!isset($tagMap[$key])) {
                    $tagMap[$key] = ['tag' => null, 'total_seconds' => 0, 'entry_count' => 0];
                }
                $tagMap[$key]['total_seconds'] += $entry->duration_seconds;
                $tagMap[$key]['entry_count']++;
            } else {
                foreach ($entry->tags as $tag) {
                    $key = "tag_{$tag->id}";
                    if (!isset($tagMap[$key])) {
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
}
