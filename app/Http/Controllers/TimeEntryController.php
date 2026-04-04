<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TimeEntryController extends Controller
{
    public function __construct(
        private readonly TimeTrackingService $timeTrackingService
    ) {}

    public function index(Request $request): Response
    {
        $cursor = $request->get('cursor');
        $projects = auth()->user()->projects()->latest()->get();
        $tags = auth()->user()->tags()->latest()->get();
        $runningTimer = $this->timeTrackingService->getRunningTimer();

        [$groupedDays, $hasMore, $nextCursor] = $this->timeTrackingService->getEntriesPaginated(
            $cursor,
            $runningTimer
        );

        $totalSeconds = collect($groupedDays)->sum(fn ($day) => $day['total_seconds']);

        return Inertia::render('Dashboard', [
            'groupedDays' => $groupedDays,
            'hasMore' => $hasMore,
            'nextCursor' => $nextCursor,
            'totalSeconds' => $totalSeconds,
            'projects' => $projects,
            'tags' => $tags,
        ]);
    }

    public function store(StoreTimeEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (isset($validated['ended_at'])) {
            $timeEntry = TimeEntry::create([
                'user_id' => auth()->id(),
                'project_id' => $validated['project_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'started_at' => $validated['started_at'],
                'ended_at' => $validated['ended_at'],
                'duration_seconds' => Carbon::parse($validated['started_at'])
                    ->diffInSeconds(Carbon::parse($validated['ended_at'])),
            ]);

            $this->syncTags($timeEntry, $validated);
        } else {
            $runningTimer = $this->timeTrackingService->getRunningTimer();

            if ($runningTimer) {
                $this->timeTrackingService->stopTimer($runningTimer);
            }

            $timeEntry = $this->timeTrackingService->startTimer(
                $validated['project_id'] ?? null,
                $validated['description'] ?? null
            );

            $this->syncTags($timeEntry, $validated);
        }

        return redirect()->route('dashboard');
    }

    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorize('update', $timeEntry);

        $validated = $request->validated();

        if (isset($validated['started_at']) || isset($validated['ended_at'])) {
            $startedAt = isset($validated['started_at'])
                ? Carbon::parse($validated['started_at'])
                : $timeEntry->started_at;

            $endedAt = isset($validated['ended_at'])
                ? Carbon::parse($validated['ended_at'])
                : $timeEntry->ended_at;

            $validated['duration_seconds'] = $endedAt
                ? $startedAt->diffInSeconds($endedAt)
                : 0;
        }

        $timeEntry->update($validated);

        if (isset($validated['tag_ids']) || isset($validated['tag_names'])) {
            $this->syncTags($timeEntry, $validated);
        }

        return redirect()->route('dashboard');
    }

    public function destroy(TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorize('delete', $timeEntry);

        $timeEntry->delete();

        return redirect()->route('dashboard');
    }

    public function stop(TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorize('update', $timeEntry);

        $this->timeTrackingService->stopTimer($timeEntry);

        return redirect()->route('dashboard');
    }

    public function restart(TimeEntry $timeEntry): RedirectResponse
    {
        $runningTimer = $this->timeTrackingService->getRunningTimer();

        if ($runningTimer) {
            $this->timeTrackingService->stopTimer($runningTimer);
        }

        $this->timeTrackingService->startTimer(
            $timeEntry->project_id,
            $timeEntry->description
        );

        return redirect()->route('dashboard');
    }

    protected function syncTags(TimeEntry $timeEntry, array $validated): void
    {
        $tagIds = $validated['tag_ids'] ?? [];

        if (!empty($validated['tag_names'])) {
            foreach ($validated['tag_names'] as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) continue;

                $tag = Tag::firstOrCreate(
                    ['user_id' => auth()->id(), 'name' => $tagName],
                    ['name' => $tagName]
                );
                $tagIds[] = $tag->id;
            }
        }

        $timeEntry->tags()->sync(array_unique($tagIds));
    }
}
