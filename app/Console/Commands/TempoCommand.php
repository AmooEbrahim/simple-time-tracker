<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\TempoInteractive;
use App\Models\Project;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class TempoCommand extends Command
{
    use TempoInteractive;

    protected $signature = 'tempo
        {args?* : Project search words, range, or project name (depends on flags)}
        {--s|stop : Stop the running timer}
        {--e|end : Stop the running timer (alias)}
        {--l|list : List time entries (optional range in args)}
        {--stats : Show totals per project (optional range in args)}
        {--csv : Export entries as CSV (optional range in args)}
        {--projects : List projects}
        {--all : Include archived projects (with --projects)}
        {--list-tags : List all tags}
        {--tags= : Comma-separated tag names (with start / stop / update / add)}
        {--project= : Project id or search text (filter, assign, or start)}
        {--i|id= : Show or update one entry by ID}
        {--m|message= : Description text (with start / stop / update / add)}
        {--restart= : Restart an entry by ID (stops running timer, starts a copy)}
        {--add : Add a finished entry manually (needs --start and --finish)}
        {--start= : Start datetime for --add / --id (Tehran local, e.g. "2026-09-20 10:00")}
        {--finish= : End datetime for --add / --id (Tehran local)}
        {--create : Create a project from args}
        {--new-color= : Hex color for --create (e.g. "#22c55e")}
        {--limit=100 : Max rows for --list}
        {--status : Show the status line only (skip the interactive menu)}
        {--prompt : One-liner for shell prompt}
        {--install-completion= : Install shell completion (bash|zsh)}
        {--user= : User ID (default TEMPO_USER_ID env or 1)}
        {--y|yes : Assume yes for prompts}';

    protected $description = 'CLI companion for this time tracker: start/stop timers, list, stats, projects';

    private const TZ = 'Asia/Tehran';

    private int $userId;

    public function handle(): int
    {
        if (! $this->resolveUser()) {
            return 1;
        }

        if ($this->option('prompt')) {
            $this->printPrompt();

            return 0;
        }

        if ($this->option('install-completion') !== null) {
            return $this->installCompletion((string) $this->option('install-completion'));
        }

        if ($this->option('projects')) {
            $this->listProjects((bool) $this->option('all'));

            return 0;
        }

        if ($this->option('list-tags')) {
            $this->listTags();

            return 0;
        }

        if ($this->option('id') !== null) {
            return $this->showOrUpdateEntry((string) $this->option('id'));
        }

        if ($this->option('restart') !== null) {
            return $this->restartEntry((string) $this->option('restart'));
        }

        if ($this->option('add')) {
            return $this->addManual();
        }

        if ($this->option('create')) {
            return $this->createProjectFromArgs();
        }

        if ($this->option('stop') || $this->option('end')) {
            return $this->stopRunning();
        }

        if ($this->option('list')) {
            return $this->listEntries();
        }

        if ($this->option('stats')) {
            return $this->showStats();
        }

        if ($this->option('csv')) {
            return $this->exportCsv();
        }

        $args = $this->argument('args');

        if (empty($args)) {
            if ($this->canShowMenu() && ! $this->option('status')) {
                return $this->runMenu();
            }

            $this->showStatus();

            return 0;
        }

        if (count($args) === 1 && strtolower($args[0]) === 'help') {
            $this->printGuide();

            return 0;
        }

        return $this->smartStart(implode(' ', $args));
    }

    // ---------------- setup ----------------

    private function resolveUser(): bool
    {
        $id = $this->option('user') ?? getenv('TEMPO_USER_ID') ?: 1;
        $user = User::find($id);

        if (! $user) {
            $this->error("No user with id {$id} (pass --user=<id> or set TEMPO_USER_ID).");

            return false;
        }

        $this->userId = $user->id;

        return true;
    }

    // ---------------- formatting ----------------

    private function humanDur(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return "{$h}h".str_pad((string) $m, 2, '0', STR_PAD_LEFT).'m';
        }

        if ($m > 0) {
            return "{$m}m".str_pad((string) $s, 2, '0', STR_PAD_LEFT).'s';
        }

        return "{$s}s";
    }

    private function humanHm(int $seconds): string
    {
        return str_pad((string) intdiv($seconds, 3600), 2, '0', STR_PAD_LEFT)
            .':'.str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT);
    }

    private function tehran(Carbon $dt): Carbon
    {
        return $dt->copy()->timezone(self::TZ);
    }

    private function fmtHm(Carbon $dt): string
    {
        return $this->tehran($dt)->format('H:i');
    }

    private function fmtDate(Carbon $dt): string
    {
        return $this->tehran($dt)->format('Y-m-d');
    }

    private function effectiveSeconds(TimeEntry $e): int
    {
        if ($e->ended_at) {
            return (int) $e->duration_seconds;
        }

        return max(0, Carbon::now('UTC')->getTimestamp() - $e->started_at->getTimestamp());
    }

    private function projectLabel(?Project $p): string
    {
        if (! $p) {
            return '<fg=gray>No project</>';
        }
        $archived = $p->is_archived ? ' <fg=yellow>[archived]</>' : '';

        return "<fg=cyan>●</> <options=bold>{$this->escape($p->name)}</> <fg=gray>#{$p->id}</>{$archived}";
    }

    private function escape(?string $s): string
    {
        return str_replace(['<', '>'], ['\\<', '\\>'], (string) $s);
    }

    private function tagsLabel(TimeEntry $e): string
    {
        if ($e->tags->isEmpty()) {
            return '';
        }

        return ' <fg=gray>['.$this->escape($e->tags->pluck('name')->implode(', ')).']</>';
    }

    // ---------------- queries ----------------

    private function runningEntry(): ?TimeEntry
    {
        return TimeEntry::where('user_id', $this->userId)
            ->whereNull('ended_at')
            ->with(['project', 'tags'])
            ->latest('started_at')
            ->first();
    }

    /** Search projects by id or (fuzzy) name. Non-archived first. */
    private function searchProjects(string $query): Collection
    {
        $query = trim($query);
        $base = Project::where('user_id', $this->userId);

        if (ctype_digit($query)) {
            $byId = (clone $base)->where('id', (int) $query)->first();
            if ($byId) {
                return collect([$byId]);
            }
        }

        $exact = (clone $base)->whereRaw('LOWER(name) = ?', [mb_strtolower($query)])->get();
        if ($exact->isNotEmpty()) {
            return $exact->sortBy('is_archived')->values();
        }

        return (clone $base)->where('name', 'like', "%{$query}%")
            ->orderBy('is_archived')
            ->orderBy('name')
            ->get();
    }

    private function findEntry(string $id): ?TimeEntry
    {
        if (! ctype_digit(trim($id))) {
            $this->error("Entry id must be a number (got '{$id}'). See: tempo -l all");

            return null;
        }

        $entry = TimeEntry::where('user_id', $this->userId)
            ->where('id', (int) $id)
            ->with(['project', 'tags'])
            ->first();

        if (! $entry) {
            $this->error("No entry #{$id} (it may be deleted or belong to another user).");
        }

        return $entry;
    }

    /** Interactive/single resolver. Returns null on cancel/failure. */
    private function pickProject(Collection $matches, string $search): ?Project
    {
        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($this->option('yes')) {
            $first = $matches->first();
            $this->line("  <fg=gray>multiple matches — --yes picked #{$first->id} {$this->escape($first->name)}</>");

            return $first;
        }

        if (! $this->canPrompt()) {
            $this->error("Multiple projects match '{$search}':");
            foreach ($matches as $p) {
                $this->line("  #{$p->id}  {$p->name}".($p->is_archived ? '  [archived]' : ''));
            }
            $this->line('Re-run with an exact id: tempo --project=<id> ...');

            return null;
        }

        $options = [];
        foreach ($matches->values() as $p) {
            $options[$p->id] = $this->projectOptionLabel($p).($p->is_archived ? '  [archived]' : '');
        }
        $options[0] = '← cancel';

        $picked = (int) select(
            label: "Multiple projects match '{$search}'",
            options: $options,
            default: $matches->first()->id,
            scroll: 10,
            hint: '↑/↓ to move · Enter to select',
        );

        if ($picked === 0) {
            $this->line('  <fg=gray>cancelled</>');

            return null;
        }

        return $matches->firstWhere('id', $picked);
    }

    private function syncTags(TimeEntry $entry, string $csv): void
    {
        $ids = [];
        foreach (explode(',', $csv) as $name) {
            $name = trim($name);
            if ($name === '' || mb_strlen($name) > 50) {
                continue;
            }
            $tag = Tag::firstOrCreate(
                ['user_id' => $this->userId, 'name' => $name],
                ['name' => $name]
            );
            $ids[] = $tag->id;
        }
        $entry->tags()->sync(array_unique($ids));
    }

    // ---------------- ranges ----------------

    /** Returns [from, to] as Tehran Carbon day bounds (inclusive), or [null, null] for all. */
    private function parseRange(?string $arg, string $default): ?array
    {
        $arg = strtolower(trim((string) ($arg ?? $default)));
        $now = Carbon::now(self::TZ);

        $day = fn (Carbon $d) => [$d->copy()->startOfDay(), $d->copy()->endOfDay()];

        return match ($arg) {
            'today', 't' => [...$day($now), 'today'],
            'yesterday', 'y' => [...$day($now->copy()->subDay()), 'yesterday'],
            'week', 'w', '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), 'last 7 days'],
            'month', 'm' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'this month'],
            'all', 'a' => [null, null, 'all'],
            default => null,
        };
    }

    private function rangeFromArgs(string $default): ?array
    {
        $args = $this->argument('args');
        $parsed = $this->parseRange($args[0] ?? null, $default);

        if ($parsed === null) {
            $this->error("Unknown range '".($args[0] ?? '')."' (use today|yesterday|week|month|all).");

            return null;
        }

        return $parsed;
    }

    private function entriesForRange(?Carbon $from, ?Carbon $to, ?array $projectIds = null): Collection
    {
        $q = TimeEntry::where('user_id', $this->userId)
            ->with(['project', 'tags'])
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        if ($from && $to) {
            $q->whereBetween('started_at', [
                $from->copy()->timezone('UTC'),
                $to->copy()->timezone('UTC'),
            ]);
        }

        if ($projectIds !== null) {
            $q->whereIn('project_id', $projectIds);
        }

        return $q->get();
    }

    /** Resolve --project filter into [project, descendantIds] or null on failure. */
    private function resolveFilter(): ?array
    {
        $filter = $this->option('project');

        if ($filter === null) {
            return [null, null];
        }

        $matches = $this->searchProjects((string) $filter);

        if ($matches->isEmpty()) {
            $this->error("No project matches '{$filter}'. See: tempo --projects");

            return null;
        }

        $project = $this->pickProject($matches, (string) $filter);

        if (! $project) {
            return null;
        }

        return [$project, $project->getAllDescendantIds()];
    }

    // ---------------- status / start / stop ----------------

    private function showStatus(bool $compact = false): void
    {
        $running = $this->runningEntry();

        if (! $running) {
            $this->line($compact ? '<fg=gray>○ not tracking anything</>' : '<fg=gray>not tracking anything — start one with: tempo <project></>');
        } else {
            $secs = $this->effectiveSeconds($running);
            $this->line("<fg=green>●</> <options=bold>{$this->humanHm($secs)}</>  started {$this->fmtHm($running->started_at)} on {$this->fmtDate($running->started_at)}  →  {$this->projectLabel($running->project)}");
            if ($running->description) {
                $this->line('  <fg=gray>“</>'.$this->escape($running->description).'<fg=gray>”</>'.$this->tagsLabel($running));
            }
            if (! $compact) {
                $this->line('  <fg=gray>('.$this->humanDur($secs).' elapsed — end it with: tempo -e   or write a note: tempo -e -m "...")</>');
            }
        }

        $now = Carbon::now(self::TZ);
        $total = $this->entriesForRange($now->copy()->startOfDay(), $now->copy()->endOfDay())
            ->sum(fn (TimeEntry $e) => $this->effectiveSeconds($e));
        $this->line('  <fg=gray>today so far:</> <options=bold>'.$this->humanDur((int) $total).'</>');
    }

    private function smartStart(string $search): int
    {
        $optProject = $this->option('project');

        if ($optProject !== null && $search !== '') {
            $this->error('Pass either search words or --project=<id|name>, not both.');

            return 1;
        }

        $search = $optProject !== null ? (string) $optProject : $search;
        $matches = $this->searchProjects($search);

        if ($matches->isEmpty()) {
            $create = $this->option('yes')
                || ($this->canPrompt()
                    && confirm("No project matches '{$search}'. Create a new project with this name?", true));

            if (! $create) {
                $this->line('  <fg=gray>cancelled</>');

                return 0;
            }

            $project = $this->createProject($search, null);
            if (! $project) {
                return 1;
            }
        } else {
            $project = $this->pickProject($matches, $search);
            if (! $project) {
                return 1;
            }

            if ($project->is_archived && ! $this->option('yes')) {
                if ($this->canPrompt()
                    && ! confirm("Project '{$project->name}' is archived. Start a timer on it anyway?", false)) {
                    $this->line('  <fg=gray>cancelled</>');

                    return 0;
                }
            }
        }

        $message = $this->option('message');
        if ($message !== null && mb_strlen($message) > 500) {
            $this->error('Description is too long (max 500 characters).');

            return 1;
        }

        $this->beginTimer(
            $project,
            $message !== null && trim($message) !== '' ? $message : null,
            $this->option('tags') !== null ? (string) $this->option('tags') : null,
        );

        return 0;
    }

    /** Stop whatever is running (single-timer invariant), then start a fresh timer on $project. */
    private function beginTimer(Project $project, ?string $message, ?string $tagsCsv): TimeEntry
    {
        $old = $this->runningEntry();
        if ($old) {
            $this->stopEntry($old);
            $this->line('  <fg=gray>auto-stopped ['.$this->humanDur((int) $old->duration_seconds).']</> '.$this->escape($old->project?->name ?? 'No project'));
        }

        $entry = TimeEntry::create([
            'user_id' => $this->userId,
            'project_id' => $project->id,
            'description' => $message,
            'started_at' => Carbon::now('UTC'),
        ]);

        if ($tagsCsv !== null) {
            $this->syncTags($entry, $tagsCsv);
            $entry->load('tags');
        }

        $this->line('<fg=green>tracking  →</>  '.$this->projectLabel($project->fresh()).$this->tagsLabel($entry).'   <fg=gray>(started '.$this->fmtHm($entry->started_at).')</>');
        if ($entry->description) {
            $this->line('  <fg=gray>“</>'.$this->escape($entry->description).'<fg=gray>”</>');
        }

        return $entry;
    }

    private function stopEntry(TimeEntry $entry): void
    {
        $now = Carbon::now('UTC');
        if ($now->getTimestamp() - $entry->started_at->getTimestamp() < 1) {
            $now = $entry->started_at->copy()->addSecond();
        }
        $entry->ended_at = $now;
        $entry->duration_seconds = $now->getTimestamp() - $entry->started_at->getTimestamp();
        $entry->save();
    }

    private function stopRunning(): int
    {
        $running = $this->runningEntry();

        if (! $running) {
            $this->line('nothing is running');

            return 0;
        }

        $message = $this->option('message');
        if ($message !== null) {
            if (mb_strlen($message) > 500) {
                $this->error('Description is too long (max 500 characters).');

                return 1;
            }
            $running->description = trim($message) !== '' ? $message : null;
        } elseif ($this->canPrompt()) {
            $new = $this->askDescription('What did you work on?', $running->description ?? '', $running->project_id);
            $running->description = $new !== '' ? $new : null;
        }

        if ($this->option('project') !== null) {
            $matches = $this->searchProjects((string) $this->option('project'));
            if ($matches->isEmpty()) {
                $this->error("No project matches '{$this->option('project')}'.");

                return 1;
            }
            $project = $this->pickProject($matches, (string) $this->option('project'));
            if (! $project) {
                return 1;
            }
            $running->project_id = $project->id;
        }

        $running->save();

        if ($this->option('tags') !== null) {
            $this->syncTags($running, (string) $this->option('tags'));
            $running->load('tags');
        }

        $this->stopEntry($running->fresh());

        $running->refresh();
        $this->line('<fg=green>stopped ['.$this->humanDur((int) $running->duration_seconds).']</>  '.$this->projectLabel($running->project));
        if ($running->description) {
            $this->line('  <fg=gray>“</>'.$this->escape($running->description).'<fg=gray>”</>'.$this->tagsLabel($running));
        }

        return 0;
    }

    // ---------------- list / stats / csv ----------------

    private function printEntryLine(TimeEntry $e): void
    {
        $secs = $this->effectiveSeconds($e);
        $now = Carbon::now('UTC');
        $dot = $e->ended_at ? '<fg=gray>○</>' : '<fg=green>●</>';
        $end = $e->ended_at ? $this->fmtHm($e->ended_at) : $this->fmtHm($now);
        $tag = $e->ended_at ? '' : '  <fg=yellow>⟵ RUNNING</>';
        $desc = $e->description ? '  '.$this->escape($e->description) : '';
        $proj = $e->project ? '  '.$this->escape($e->project->name).' <fg=gray>#'.$e->project->id.'</>' : '  <fg=gray>No project</>';

        $this->line("  {$dot} <fg=gray>#{$e->id}</>  {$this->fmtHm($e->started_at)}→{$end}  <options=bold>".str_pad($this->humanDur($secs), 7, ' ', STR_PAD_LEFT).'</>'.$proj.$desc.$this->tagsLabel($e).$tag);
    }

    private function listEntries(): int
    {
        $range = $this->rangeFromArgs('today');
        if ($range === null) {
            return 1;
        }
        [$from, $to, $label] = $range;

        $filter = $this->resolveFilter();
        if ($filter === null) {
            return 1;
        }
        [$filterProject, $projectIds] = $filter;

        $limit = max(1, min(1000, (int) ($this->option('limit') ?? 100)));
        $entries = $this->entriesForRange($from, $to, $projectIds)->take($limit);

        $suffix = $filterProject ? "  <fg=gray>[project: {$this->escape($filterProject->name)}]</>" : '';
        if ($entries->isEmpty()) {
            $this->line("<fg=gray>no entries ({$label})</>{$suffix}");

            return 0;
        }

        $this->line("<options=bold>{$label} ({$entries->count()} entries):</>{$suffix}");

        $grand = 0;
        foreach ($entries->groupBy(fn (TimeEntry $e) => $this->fmtDate($e->started_at)) as $day => $dayEntries) {
            $dayTotal = $dayEntries->sum(fn (TimeEntry $e) => $this->effectiveSeconds($e));
            $grand += $dayTotal;
            $this->line("\n<options=bold>{$day}</>  <fg=gray>{$this->humanDur((int) $dayTotal)}</>");
            foreach ($dayEntries as $e) {
                $this->printEntryLine($e);
            }
        }

        $this->line("\n<fg=gray>TOTAL</> <options=bold>{$this->humanDur((int) $grand)}</>");

        return 0;
    }

    private function showStats(): int
    {
        $range = $this->rangeFromArgs('week');
        if ($range === null) {
            return 1;
        }
        [$from, $to, $label] = $range;

        $filter = $this->resolveFilter();
        if ($filter === null) {
            return 1;
        }
        [$filterProject, $projectIds] = $filter;

        return $this->renderStats($from, $to, $label, $filterProject, $projectIds);
    }

    private function renderStats(?Carbon $from, ?Carbon $to, string $label, ?Project $filterProject, ?array $projectIds): int
    {
        $entries = $this->entriesForRange($from, $to, $projectIds);

        $suffix = $filterProject ? "  <fg=gray>[project: {$this->escape($filterProject->name)}]</>" : '';
        if ($entries->isEmpty()) {
            $this->line("<fg=gray>no entries ({$label})</>{$suffix}");

            return 0;
        }

        $secs = fn (TimeEntry $e) => $this->effectiveSeconds($e);
        $total = $entries->sum($secs);
        $this->line("<options=bold>📊 {$label} — {$entries->count()} entries, {$this->humanDur((int) $total)} total</>{$suffix}");

        $perProject = [];
        foreach ($entries as $e) {
            $key = $e->project ? "#{$e->project->id} {$e->project->name}" : 'No project';
            $perProject[$key] = ($perProject[$key] ?? 0) + $secs($e);
        }
        arsort($perProject);

        $top = max($perProject) ?: 1;
        foreach ($perProject as $name => $s) {
            $filled = $s > 0 ? max(1, (int) round(20 * $s / $top)) : 0;
            $bar = '<fg=cyan>'.str_repeat('█', $filled).'</><fg=gray>'.str_repeat('░', 20 - $filled).'</>';
            $pct = 100 * $s / ($total ?: 1);
            $this->line('  '.str_pad(mb_substr($name, 0, 22), 22).' <options=bold>'.str_pad($this->humanDur((int) $s), 7, ' ', STR_PAD_LEFT).'</>  '.$bar.'  '.number_format($pct, 1).'%');
        }

        $perDay = [];
        foreach ($entries as $e) {
            $d = $this->fmtDate($e->started_at);
            $perDay[$d] = ($perDay[$d] ?? 0) + $secs($e);
        }
        if (count($perDay) > 1 && count($perDay) <= 31) {
            $this->line("\n  <fg=gray>by day</>");
            $dayTop = max($perDay) ?: 1;
            $days = $perDay;
            ksort($days);
            foreach ($days as $d => $s) {
                $filled = $s > 0 ? max(1, (int) round(20 * $s / $dayTop)) : 0;
                $this->line('  '.Carbon::parse($d)->format('D m-d').'  '.str_pad($this->humanDur((int) $s), 7, ' ', STR_PAD_LEFT).'  <fg=green>'.str_repeat('▇', $filled).'</>');
            }
        }

        arsort($perDay);
        $bestDay = array_key_first($perDay);

        $finished = $entries->filter(fn (TimeEntry $e) => $e->ended_at !== null);
        $this->line("\n  <fg=gray>best day:</>        <options=bold>{$bestDay}</> ({$this->humanDur((int) $perDay[$bestDay])})");
        if ($finished->isNotEmpty()) {
            $longest = $finished->sortByDesc($secs)->first();
            $lname = $longest->project?->name ?? 'No project';
            $this->line('  <fg=gray>longest session:</> <options=bold>'.$this->humanDur($secs($longest)).'</>  ('.$this->escape($lname).', '.$this->fmtDate($longest->started_at).', #'.$longest->id.')');
        }

        return 0;
    }

    private function exportCsv(): int
    {
        $range = $this->rangeFromArgs('all');
        if ($range === null) {
            return 1;
        }
        [$from, $to] = $range;

        $filter = $this->resolveFilter();
        if ($filter === null) {
            return 1;
        }
        [, $projectIds] = $filter;

        $entries = $this->entriesForRange($from, $to, $projectIds)->sortBy('started_at');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Description', 'Project', 'Project ID', 'Date', 'Start Time', 'End Time', 'Duration (minutes)', 'Tags']);

        $now = Carbon::now('UTC');
        foreach ($entries as $e) {
            fputcsv($out, [
                $e->description ?? '',
                $e->project?->name ?? 'No Project',
                $e->project_id ?? '',
                $this->fmtDate($e->started_at),
                $this->fmtHm($e->started_at),
                $e->ended_at ? $this->fmtHm($e->ended_at) : $this->fmtHm($now),
                round($this->effectiveSeconds($e) / 60, 1),
                $e->tags->pluck('name')->implode(';'),
            ]);
        }
        fclose($out);

        return 0;
    }

    // ---------------- projects / tags ----------------

    private function listProjects(bool $includeArchived = false): void
    {
        $q = Project::where('user_id', $this->userId)
            ->withCount('timeEntries')
            ->withSum('timeEntries', 'duration_seconds')
            ->orderBy('is_archived')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('name');

        if (! $includeArchived) {
            $q->where('is_archived', false);
        }

        $projects = $q->get();
        $running = $this->runningEntry();

        if ($projects->isEmpty()) {
            $this->line('<fg=gray>no projects yet — create one with: tempo --create "My Project"</>');

            return;
        }

        $byId = $projects->keyBy('id');
        $rows = [];
        foreach ($projects as $p) {
            $name = $p->parent_id && $byId->has($p->parent_id)
                ? $byId[$p->parent_id]->name.' / '.$p->name
                : $p->name;
            $marker = $running && $running->project_id === $p->id ? ' <fg=green>⟵ RUNNING</>' : '';
            $rows[] = [
                $p->id,
                $name.$marker,
                $p->color,
                $p->is_archived ? 'archived' : 'active',
                $p->time_entries_count,
                $this->humanDur((int) ($p->time_entries_sum_duration_seconds ?? 0)),
            ];
        }

        $this->table(['ID', 'Project', 'Color', 'Status', 'Entries', 'Total'], $rows);
        $this->line('<fg=gray>start one with: tempo <name or id>   (e.g. tempo '.$this->escape($projects->first()->name).')</>');
    }

    private function listTags(): void
    {
        $tags = Tag::where('user_id', $this->userId)
            ->withCount('timeEntries')
            ->orderByDesc('time_entries_count')
            ->orderBy('name')
            ->get();

        if ($tags->isEmpty()) {
            $this->line('<fg=gray>no tags yet — attach one with: tempo <project> --tags=foo,bar</>');

            return;
        }

        $this->table(
            ['ID', 'Tag', 'Entries'],
            $tags->map(fn (Tag $t) => [$t->id, $t->name, $t->time_entries_count])->all()
        );
    }

    private function createProject(string $name, ?string $color): ?Project
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 255) {
            $this->error('Project name must be 1–255 characters.');

            return null;
        }

        $color = $color ? ltrim(trim($color), '#') : '6366f1';
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
            $this->error('Color must be a 6-digit hex value (e.g. "#22c55e").');

            return null;
        }

        $dupe = Project::where('user_id', $this->userId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($dupe) {
            $this->error("Project '{$name}' already exists (#{$dupe->id}).");

            return null;
        }

        $project = Project::create([
            'user_id' => $this->userId,
            'name' => $name,
            'color' => '#'.strtolower($color),
        ]);

        $this->line('<fg=green>created project</>  '.$this->projectLabel($project));

        return $project;
    }

    private function createProjectFromArgs(): int
    {
        $name = implode(' ', $this->argument('args'));

        if (trim($name) === '') {
            $this->error('Give the project a name: tempo --create "My Project".');

            return 1;
        }

        return $this->createProject($name, $this->option('new-color')) ? 0 : 1;
    }

    // ---------------- entries: show / update / restart / add ----------------

    private function printEntryDetail(TimeEntry $e): void
    {
        $state = $e->ended_at ? 'finished' : 'RUNNING';
        $this->line("<options=bold>Entry #{$e->id}</>  <fg=gray>[{$state}]</>");
        $this->line("  project:     {$this->projectLabel($e->project)}");
        $this->line('  description: '.($e->description ? $this->escape($e->description) : '<fg=gray>—</>'));
        $this->line('  tags:        '.($e->tags->isEmpty() ? '<fg=gray>—</>' : $this->escape($e->tags->pluck('name')->implode(', '))));
        $this->line("  started:     {$this->tehran($e->started_at)->format('Y-m-d H:i')}");
        $this->line('  ended:       '.($e->ended_at ? $this->tehran($e->ended_at)->format('Y-m-d H:i') : '<fg=yellow>running</>'));
        $this->line("  duration:    <options=bold>{$this->humanDur($this->effectiveSeconds($e))}</>");
    }

    private function parseInputTime(string $input): ?Carbon
    {
        $input = trim($input);
        $now = Carbon::now(self::TZ);

        try {
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $input)) {
                $dt = $now->copy()->setTimeFromTimeString($input);
            } else {
                $dt = Carbon::parse($input, self::TZ);
            }
        } catch (\Throwable) {
            return null;
        }

        return $dt->timezone('UTC');
    }

    private function showOrUpdateEntry(string $id): int
    {
        $entry = $this->findEntry($id);
        if (! $entry) {
            return 1;
        }

        $message = $this->option('message');
        $projectOpt = $this->option('project');
        $tagsOpt = $this->option('tags');
        $startOpt = $this->option('start');
        $finishOpt = $this->option('finish');

        if ($message === null && $projectOpt === null && $tagsOpt === null && $startOpt === null && $finishOpt === null) {
            $this->printEntryDetail($entry);

            return 0;
        }

        if ($message !== null) {
            if (mb_strlen($message) > 500) {
                $this->error('Description is too long (max 500 characters).');

                return 1;
            }
            $entry->description = trim($message) !== '' ? $message : null;
        }

        if ($projectOpt !== null) {
            $matches = $this->searchProjects((string) $projectOpt);
            if ($matches->isEmpty()) {
                $this->error("No project matches '{$projectOpt}'.");

                return 1;
            }
            $project = $this->pickProject($matches, (string) $projectOpt);
            if (! $project) {
                return 1;
            }
            $entry->project_id = $project->id;
        }

        $start = $entry->started_at;
        $end = $entry->ended_at;

        if ($startOpt !== null) {
            $parsed = $this->parseInputTime((string) $startOpt);
            if (! $parsed) {
                $this->error("Could not parse --start value '{$startOpt}' (try \"2026-09-20 10:00\").");

                return 1;
            }
            $start = $parsed;
        }

        if ($finishOpt !== null) {
            $parsed = $this->parseInputTime((string) $finishOpt);
            if (! $parsed) {
                $this->error("Could not parse --finish value '{$finishOpt}' (try \"2026-09-20 11:00\").");

                return 1;
            }
            $end = $parsed;
        }

        if ($startOpt !== null || $finishOpt !== null) {
            if ($error = $this->applyTimes($entry, $start, $end)) {
                $this->error($error);

                return 1;
            }
        }

        $entry->save();

        if ($tagsOpt !== null) {
            $this->syncTags($entry, (string) $tagsOpt);
            $entry->load('tags');
        }

        $this->line('<fg=green>updated</>');
        $this->printEntryDetail($entry->fresh(['project', 'tags']));

        return 0;
    }

    /** Set start/end (+ recomputed duration) on an entry. Returns an error message, or null when applied. */
    private function applyTimes(TimeEntry $entry, Carbon $start, ?Carbon $end): ?string
    {
        if ($end && $end->lte($start)) {
            return 'End time must be after start time.';
        }

        $entry->started_at = $start;
        $entry->ended_at = $end;
        $entry->duration_seconds = $end ? $end->getTimestamp() - $start->getTimestamp() : 0;

        return null;
    }

    private function restartEntry(string $id): int
    {
        $entry = $this->findEntry($id);
        if (! $entry) {
            return 1;
        }

        $old = $this->runningEntry();
        if ($old) {
            $this->stopEntry($old);
            $this->line('  <fg=gray>auto-stopped ['.$this->humanDur((int) $old->duration_seconds).']</> '.$this->escape($old->project?->name ?? 'No project'));
        }

        $copy = TimeEntry::create([
            'user_id' => $this->userId,
            'project_id' => $entry->project_id,
            'description' => $entry->description,
            'started_at' => Carbon::now('UTC'),
        ]);
        $copy->tags()->sync($entry->tags->pluck('id')->all());
        $copy->load(['project', 'tags']);

        $this->line('<fg=green>tracking  →</>  '.$this->projectLabel($copy->project).$this->tagsLabel($copy).'   <fg=gray>(restarted from #'.$entry->id.')</>');

        return 0;
    }

    private function addManual(): int
    {
        $args = implode(' ', $this->argument('args'));
        $optProject = $this->option('project');

        if ($optProject !== null && $args !== '') {
            $this->error('Pass either project words or --project=<id|name>, not both.');

            return 1;
        }

        $search = $optProject !== null ? (string) $optProject : $args;

        if (trim($search) === '') {
            $this->error('Give a project: tempo --add <project> --start="2026-09-20 10:00" --finish="2026-09-20 11:00".');

            return 1;
        }

        $startOpt = $this->option('start');
        $finishOpt = $this->option('finish');

        if ($startOpt === null || $finishOpt === null) {
            $this->error('--add needs both --start and --finish (Tehran local, e.g. --start="2026-09-20 10:00").');

            return 1;
        }

        $start = $this->parseInputTime((string) $startOpt);
        $finish = $this->parseInputTime((string) $finishOpt);

        if (! $start) {
            $this->error("Could not parse --start value '{$startOpt}'.");

            return 1;
        }
        if (! $finish) {
            $this->error("Could not parse --finish value '{$finishOpt}'.");

            return 1;
        }
        if ($finish->lte($start)) {
            $this->error('End time must be after start time.');

            return 1;
        }

        $matches = $this->searchProjects($search);
        if ($matches->isEmpty()) {
            $create = $this->option('yes')
                || ($this->canPrompt()
                    && confirm("No project matches '{$search}'. Create it?", true));
            if (! $create) {
                return 0;
            }
            $project = $this->createProject($search, null);
            if (! $project) {
                return 1;
            }
        } else {
            $project = $this->pickProject($matches, $search);
            if (! $project) {
                return 1;
            }
        }

        $message = $this->option('message');
        if ($message !== null && mb_strlen($message) > 500) {
            $this->error('Description is too long (max 500 characters).');

            return 1;
        }

        $this->storeFinished(
            $project,
            $start,
            $finish,
            $message !== null && trim($message) !== '' ? $message : null,
            $this->option('tags') !== null ? (string) $this->option('tags') : null,
        );

        return 0;
    }

    private function storeFinished(Project $project, Carbon $start, Carbon $finish, ?string $message, ?string $tagsCsv): TimeEntry
    {
        $entry = TimeEntry::create([
            'user_id' => $this->userId,
            'project_id' => $project->id,
            'description' => $message,
            'started_at' => $start,
            'ended_at' => $finish,
            'duration_seconds' => $finish->getTimestamp() - $start->getTimestamp(),
        ]);

        if ($tagsCsv !== null) {
            $this->syncTags($entry, $tagsCsv);
            $entry->load('tags');
        }

        $this->line('<fg=green>added</>  '.$this->projectLabel($project->fresh()).$this->tagsLabel($entry).'  <fg=gray>'.$this->tehran($start)->format('Y-m-d H:i').' → '.$this->tehran($finish)->format('H:i').' ['.$this->humanDur((int) $entry->duration_seconds).']</>');

        return $entry;
    }

    // ---------------- prompt / completion / guide ----------------

    private function printPrompt(): void
    {
        $running = $this->runningEntry();

        if (! $running) {
            $this->output->write('⏱ idle');

            return;
        }

        $name = $running->project?->name ?? 'no project';
        $this->output->write("⏱ {$name} ".$this->humanHm($this->effectiveSeconds($running)));
    }

    private function installCompletion(string $shell): int
    {
        $shell = strtolower(trim($shell));

        if ($shell === 'bash') {
            $target = PathHelper::home().'/.bash_completion.d/tempo';
            @mkdir(dirname($target), 0777, true);
            file_put_contents($target, $this->bashCompletionScript());

            $line = 'source ~/.bash_completion.d/tempo  # tempo tab-completion';
            $bashrc = PathHelper::home().'/.bashrc';
            $has = is_file($bashrc) && str_contains((string) file_get_contents($bashrc), $line);
            if (! $has) {
                file_put_contents($bashrc, "\n{$line}\n", FILE_APPEND);
                $this->line("<fg=green>wrote {$target}</> and hooked it into ~/.bashrc");
            } else {
                $this->line("<fg=green>wrote {$target}</> (~/.bashrc already hooked)");
            }
            $this->line('<fg=gray>restart your shell (or run: source ~/.bash_completion.d/tempo) and try: tempo --<TAB></>');

            return 0;
        }

        if ($shell === 'zsh') {
            $target = PathHelper::home().'/.zfunc/_tempo';
            @mkdir(dirname($target), 0777, true);
            file_put_contents($target, $this->zshCompletionScript());
            $this->line("<fg=green>wrote {$target}</>");
            $this->line('then make sure your ~/.zshrc contains, BEFORE compinit:');
            $this->line('  fpath=(~/.zfunc $fpath)');
            $this->line('<fg=gray>restart your shell and try: tempo --<TAB></>');

            return 0;
        }

        $this->error("Unknown shell '{$shell}' (use bash or zsh).");

        return 1;
    }

    private function bashCompletionScript(): string
    {
        return <<<'BASH'
# tempo bash completion — installed by `tempo --install-completion bash`
_tempo_complete() {
    local cur prev
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"
    local flags="--stop --end --list --stats --csv --projects --all --list-tags --status --tags --project --id --message --restart --add --start --finish --create --new-color --limit --prompt --install-completion --user --yes --help"
    local ranges="today yesterday week month all"
    case "$prev" in
        -l|--list|--stats|--csv)
            COMPREPLY=($(compgen -W "$ranges" -- "$cur")); return ;;
        --install-completion)
            COMPREPLY=($(compgen -W "bash zsh" -- "$cur")); return ;;
    esac
    COMPREPLY=($(compgen -W "$flags" -- "$cur"))
}
complete -F _tempo_complete tempo
BASH;
    }

    private function zshCompletionScript(): string
    {
        return <<<'ZSH'
#compdef tempo
# tempo zsh completion — installed by `tempo --install-completion zsh`
_tempo() {
    local -a flags ranges shells
    flags=(--stop --end --list --stats --csv --projects --all --list-tags --status --tags --project --id --message --restart --add --start --finish --create --new-color --limit --prompt --install-completion --user --yes --help)
    ranges=(today yesterday week month all)
    shells=(bash zsh)
    if (( CURRENT > 2 )); then
        case "${words[CURRENT-1]}" in
            -l|--list|--stats|--csv) _describe 'range' ranges; return ;;
            --install-completion) _describe 'shell' shells; return ;;
        esac
    fi
    _describe 'command' flags
}
_tempo "$@"
ZSH;
    }

    private function printGuide(): void
    {
        $this->line('<options=bold>tempo</> — CLI companion for this time tracker (same MySQL data as the web UI)');
        $this->line('');
        $this->line('  <fg=cyan>tempo</>                      interactive menu (arrow keys): start/stop, entries, add, stats, projects');
        $this->line('  <fg=cyan>tempo --status</>             just show what is running + today total (what a pipe/script gets)');
        $this->line('  <fg=cyan>tempo sabtivan</>             fuzzy-find project, start its timer (asks if 0 or many match)');
        $this->line('  <fg=cyan>tempo sabtivan -m "..."</>    start with a description, <fg=cyan>--tags=a,b</> to tag it');
        $this->line('  <fg=cyan>tempo -e</>                   stop running (asks for a description you can keep or rewrite)');
        $this->line('  <fg=cyan>tempo -e -m "..."</>          stop and set the description in one go');
        $this->line('  <fg=cyan>tempo -l [RANGE]</>           list entries (today|yesterday|week|month|all), with entry IDs');
        $this->line('  <fg=cyan>tempo --stats [RANGE]</>      totals per project + best day + longest session');
        $this->line('  <fg=cyan>tempo --csv [RANGE]</>        export CSV (same columns as Reports → Export)');
        $this->line('  <fg=cyan>tempo --projects</>           list projects (<fg=cyan>--all</> includes archived)');
        $this->line('  <fg=cyan>tempo --create "Name"</>      create a project');
        $this->line('  <fg=cyan>tempo -i 12</>                show entry #12; add <fg=cyan>-m / --project / --tags / --start / --finish</> to update it');
        $this->line('  <fg=cyan>tempo --restart 12</>         start a new timer copying entry #12');
        $this->line('  <fg=cyan>tempo --add <proj></>         manual entry (needs <fg=cyan>--start</> + <fg=cyan>--finish</>, Tehran local)');
        $this->line('  <fg=cyan>tempo --list-tags</>          list tags');
        $this->line('  <fg=cyan>tempo --prompt</>             one-liner for your shell prompt');
        $this->line('');
        $this->line('  <fg=gray>RANGE defaults: list → today, stats → week, csv → all. Add --project="name|id" to filter.</>');
        $this->line('  <fg=gray>Times you type are Tehran local. tempo never deletes anything — use the web UI to delete.</>');
    }
}

class PathHelper
{
    public static function home(): string
    {
        return getenv('HOME') ?: (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['dir'] ?? '/root') : '/root');
    }
}
