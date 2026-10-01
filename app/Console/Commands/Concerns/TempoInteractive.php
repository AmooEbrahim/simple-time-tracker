<?php

namespace App\Console\Commands\Concerns;

use App\Console\Support\EscapeBackTerminal;
use App\Models\Project;
use App\Models\Tag;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Laravel\Prompts\Exceptions\FormRevertedException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;

/**
 * Arrow-key / type-to-filter UI for `tempo`, built on laravel/prompts.
 *
 * Mixed into TempoCommand (it relies on the command's private helpers such as
 * runningEntry(), beginTimer(), applyTimes()). Only entered when the input is
 * interactive — piped/scripted runs and every flag keep their old behavior.
 */
trait TempoInteractive
{
    private const NEW_PROJECT = '__new';

    /** Up to this many choices (incl. "create") the project picker is a plain list instead of a search box. */
    private const PICK_LIST_MAX = 12;

    /** True while the menu runs and Esc is wired up as "back" (see EscapeBackTerminal). */
    private bool $escBack = false;

    private const PALETTE = [
        '#6366f1' => 'Indigo',
        '#22c55e' => 'Green',
        '#0ea5e9' => 'Sky',
        '#f59e0b' => 'Amber',
        '#ef4444' => 'Red',
        '#ec4899' => 'Pink',
        '#a855f7' => 'Purple',
        '#14b8a6' => 'Teal',
        '#64748b' => 'Slate',
    ];

    /**
     * May we show prompts? Symfony says "interactive" even when stdin is a pipe, but prompts
     * need a real terminal — scripts/cron must keep getting plain output instead of a crash.
     */
    private function canPrompt(): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->laravel->runningUnitTests() || (defined('STDIN') && stream_isatty(STDIN));
    }

    /** The full-screen-ish menu additionally needs stdout on a terminal (`tempo | cat` should just print status). */
    private function canShowMenu(): bool
    {
        return $this->canPrompt() && ($this->laravel->runningUnitTests() || stream_isatty(STDOUT));
    }

    /** Join hint fragments, appending the Esc reminder only while Esc actually works. */
    private function hint(string ...$parts): string
    {
        if ($this->escBack) {
            $parts[] = 'Esc to go back';
        }

        return implode(' · ', array_filter($parts, fn ($p) => $p !== ''));
    }

    // ---------------- main menu ----------------

    private function runMenu(): int
    {
        $this->escBack = EscapeBackTerminal::enable();

        try {
            return $this->menuLoop();
        } finally {
            EscapeBackTerminal::disable();
            $this->escBack = false;
        }
    }

    /** Esc anywhere inside a flow lands back here (the main menu); Esc on the menu itself quits. */
    private function menuLoop(): int
    {
        intro(' tempo ');

        while (true) {
            $this->showStatus(compact: true);
            $this->line('');

            $running = $this->runningEntry();
            $last = $running ? null : TimeEntry::where('user_id', $this->userId)
                ->whereNotNull('ended_at')
                ->with('project')
                ->latest('ended_at')
                ->first();

            $choices = [];
            if ($running) {
                $choices['stop'] = 'Stop the timer';
                $choices['start'] = 'Switch to another project';
                $choices['edit'] = 'Edit the running entry';
            } else {
                $choices['start'] = 'Start a timer';
                if ($last) {
                    $choices['resume'] = 'Resume: '.$this->entrySummary($last);
                }
            }
            $choices['entries'] = 'Browse & edit entries';
            $choices['add'] = 'Add a finished entry by hand';
            $choices['stats'] = 'Stats';
            $choices['projects'] = 'Projects';
            $choices['quit'] = 'Quit';

            try {
                $action = select(
                    label: 'What would you like to do?',
                    options: $choices,
                    scroll: 10,
                    hint: $this->escBack ? '↑/↓ to move · Enter to select · Esc to quit' : '↑/↓ to move · Enter to select · Ctrl+C to leave',
                );
            } catch (FormRevertedException) {
                return 0;
            }

            try {
                switch ($action) {
                    case 'start':
                        $done = $this->interactiveStart();
                        if ($done !== null) {
                            return $done;
                        }
                        break;
                    case 'stop':
                        return $this->stopRunning();
                    case 'resume':
                        return $this->restartEntry((string) $last->id);
                    case 'edit':
                        if ($this->entryActions($running)) {
                            return 0;
                        }
                        break;
                    case 'entries':
                        if ($this->browseEntries()) {
                            return 0;
                        }
                        break;
                    case 'add':
                        $done = $this->interactiveAdd();
                        if ($done !== null) {
                            return $done;
                        }
                        break;
                    case 'stats':
                        $this->interactiveStats();
                        break;
                    case 'projects':
                        $this->projectsMenu();
                        break;
                    default:
                        return 0;
                }
            } catch (FormRevertedException) {
                // Esc inside a flow nobody handled more precisely: back to the menu.
            }

            $this->line('');
        }
    }

    // ---------------- reusable prompts ----------------

    /** Truecolor dot in the project's color (plain dot when the terminal can't do truecolor). */
    private function colorDot(?string $hex): string
    {
        $truecolor = in_array(getenv('COLORTERM'), ['truecolor', '24bit'], true);

        if (! $truecolor || ! $hex || ! preg_match('/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            return '■';
        }

        return sprintf("\e[38;2;%d;%d;%dm■\e[39m", hexdec($m[1]), hexdec($m[2]), hexdec($m[3]));
    }

    /** Plain-text (no Symfony tags) project label for prompt options. */
    private function projectOptionLabel(Project $p): string
    {
        return $this->colorDot($p->color).' '.$p->name;
    }

    private function entrySummary(TimeEntry $e): string
    {
        $text = $e->project?->name ?? 'No project';
        if ($e->description) {
            $text .= ' — '.mb_strimwidth($e->description, 0, 40, '…');
        }

        return $text;
    }

    /** 0 = best; null = no match. Substring beats subsequence ("tt" matches "TimeTracker"). */
    private function fuzzyScore(string $needle, string $hay): ?int
    {
        $needle = mb_strtolower(trim($needle));
        $hay = mb_strtolower($hay);

        if ($needle === '') {
            return 0;
        }

        $pos = mb_strpos($hay, $needle);
        if ($pos !== false) {
            return $pos;
        }

        $from = 0;
        $score = 1000;
        foreach (mb_str_split($needle) as $ch) {
            $found = mb_strpos($hay, $ch, $from);
            if ($found === false) {
                return null;
            }
            $score += $found - $from;
            $from = $found + 1;
        }

        return $score;
    }

    /** @return array<int|string, string> project id => label, "create new" last */
    private function projectOptions(string $query): array
    {
        $running = $this->runningEntry();
        $all = Project::where('user_id', $this->userId)
            ->withMax('timeEntries', 'started_at')
            ->get();
        $byId = $all->keyBy('id');

        $rows = [];
        foreach ($all as $p) {
            if ($query === '' && $p->is_archived) {
                continue;
            }
            $full = $p->parent_id && $byId->has($p->parent_id) ? $byId[$p->parent_id]->name.' / '.$p->name : $p->name;
            $score = $this->fuzzyScore($query, $full);
            if ($score === null) {
                continue;
            }
            $rows[] = [$p, $full, $score];
        }

        usort($rows, fn ($a, $b) => [$a[0]->is_archived, $a[2], $b[0]->time_entries_max_started_at ?? '', $a[1]]
            <=> [$b[0]->is_archived, $b[2], $a[0]->time_entries_max_started_at ?? '', $b[1]]);

        $options = [];
        foreach ($rows as [$p, $full]) {
            $note = [];
            if ($running && $running->project_id === $p->id) {
                $note[] = 'running now';
            } elseif ($p->time_entries_max_started_at) {
                $note[] = Carbon::parse($p->time_entries_max_started_at, 'UTC')->diffForHumans();
            }
            if ($p->is_archived) {
                $note[] = 'archived';
            }
            $options[$p->id] = $this->colorDot($p->color).' '.$full.($note ? '   '.implode(' · ', $note) : '');
        }

        $options[self::NEW_PROJECT] = '+ Create '.(trim($query) !== '' ? '“'.trim($query).'”' : 'a new project');

        return $options;
    }

    /**
     * Project picker, most recently used first. A handful of projects get a plain arrow-key list
     * (first one pre-selected, so Enter = "the one I just used"); big lists switch to type-to-filter.
     * Null when creating a new project was cancelled/failed.
     */
    private function askProject(string $label = 'Which project?'): ?Project
    {
        $query = '';
        $options = $this->projectOptions('');

        if (count($options) <= self::PICK_LIST_MAX) {
            $choice = select(
                label: $label,
                options: $options,
                default: array_key_first($options),
                scroll: self::PICK_LIST_MAX,
                hint: $this->hint('↑/↓ to move', 'Enter to select'),
            );
        } else {
            $choice = $this->searchProject($label, $query);
        }

        if ($choice === self::NEW_PROJECT) {
            return $this->promptNewProject(trim($query));
        }

        return Project::where('user_id', $this->userId)->find((int) $choice);
    }

    private function searchProject(string $label, string &$query): int|string
    {
        return search(
            label: $label,
            options: function (string $q) use (&$query) {
                $query = $q;

                return $this->projectOptions($q);
            },
            placeholder: 'type to filter…',
            scroll: 9,
            hint: $this->hint('type to filter', '↓ to pick', 'Enter to select'),
        );
    }

    private function promptNewProject(string $prefill = ''): ?Project
    {
        $step = 0;
        $name = $prefill;
        $options = [];
        foreach (self::PALETTE as $hex => $colorName) {
            $options[$hex] = $this->colorDot($hex).' '.$colorName;
        }

        while (true) {
            try {
                if ($step === 0) {
                    $name = trim(text(
                        label: 'Project name',
                        default: $name,
                        required: 'Give the project a name.',
                        validate: function (string $v) {
                            $v = trim($v);
                            if (mb_strlen($v) > 255) {
                                return 'Max 255 characters.';
                            }
                            $taken = Project::where('user_id', $this->userId)->whereRaw('LOWER(name) = ?', [mb_strtolower($v)])->exists();

                            return $taken ? 'You already have a project with this name.' : null;
                        },
                        hint: $this->hint(),
                    ));
                    $step = 1;
                }

                $color = (string) select('Color', $options, default: '#6366f1', scroll: 9, hint: $this->hint());

                return $this->createProject($name, $color);
            } catch (FormRevertedException $e) {
                if ($step === 0) {
                    throw $e; // Esc on the first question: let the caller step back
                }
                $step = 0;
            }
        }
    }

    private function askDescription(string $label, string $default = '', ?int $projectId = null): string
    {
        $recent = TimeEntry::where('user_id', $this->userId)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->latest('started_at')
            ->limit(80)
            ->pluck('description')
            ->unique()
            ->take(20)
            ->values()
            ->all();

        return trim(suggest(
            label: $label,
            options: fn (string $v) => array_values(array_filter(
                $recent,
                fn (string $d) => trim($v) === '' || mb_stripos($d, trim($v)) !== false,
            )),
            placeholder: 'optional — Enter to skip',
            default: $default,
            validate: fn (string $v) => mb_strlen($v) > 500 ? 'Max 500 characters.' : null,
            hint: $this->hint($recent ? '↓ shows your recent descriptions' : ''),
        ));
    }

    private function askTags(string $default = ''): string
    {
        $mine = Tag::where('user_id', $this->userId)
            ->withCount('timeEntries')
            ->orderByDesc('time_entries_count')
            ->orderBy('name')
            ->limit(8)
            ->pluck('name')
            ->all();

        return trim(text(
            label: 'Tags',
            placeholder: 'optional — comma separated',
            default: $default,
            validate: fn (string $v) => collect(explode(',', $v))->contains(fn ($t) => mb_strlen(trim($t)) > 50)
                ? 'Each tag can be at most 50 characters.'
                : null,
            hint: $this->hint($mine ? 'yours: '.implode(', ', $mine) : ''),
        ));
    }

    private function timeValidator(bool $allowEmpty = false): \Closure
    {
        return function (string $v) use ($allowEmpty) {
            if (trim($v) === '') {
                return $allowEmpty ? null : 'Required.';
            }

            return $this->parseInputTime($v) ? null : 'Try "2026-09-20 10:00", or just "10:30" for today.';
        };
    }

    // ---------------- start / add ----------------

    /**
     * Esc steps back one question; Esc on the first one returns null (= back to the menu).
     * Otherwise returns the exit code.
     */
    private function interactiveStart(): ?int
    {
        $step = 0;
        $project = null;
        $description = '';
        $tags = '';

        while ($step < 3) {
            try {
                if ($step === 0) {
                    $project = $this->askProject($this->runningEntry() ? 'Switch to which project?' : 'Start tracking which project?');
                    if (! $project) {
                        return 1;
                    }

                    if ($project->is_archived && ! confirm("'{$project->name}' is archived. Start a timer on it anyway?", false)) {
                        $this->line('  <fg=gray>cancelled</>');

                        return 0;
                    }
                } elseif ($step === 1) {
                    $description = $this->askDescription('What are you working on?', $description, $project->id);
                } else {
                    $tags = $this->askTags($tags);
                }
                $step++;
            } catch (FormRevertedException) {
                if ($step === 0) {
                    return null;
                }
                $step--;
            }
        }

        $this->beginTimer($project, $description !== '' ? $description : null, $tags !== '' ? $tags : null);

        return 0;
    }

    /** Same back-stepping contract as interactiveStart(). */
    private function interactiveAdd(): ?int
    {
        $clock = fn (string $v) => preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', trim($v)) ? null : 'Use HH:MM (24h), e.g. 09:30.';

        $step = 0;
        $project = null;
        $date = Carbon::now(self::TZ)->format('Y-m-d');
        $from = '';
        $to = '';
        $description = '';
        $tags = '';

        while ($step < 6) {
            try {
                switch ($step) {
                    case 0:
                        $project = $this->askProject('Which project was it?');
                        if (! $project) {
                            return 1;
                        }
                        break;
                    case 1:
                        $date = trim(text(
                            label: 'Date',
                            default: $date,
                            required: true,
                            validate: fn (string $v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($v)) && $this->parseInputTime($v) ? null : 'Use YYYY-MM-DD.',
                            hint: $this->hint('Tehran time'),
                        ));
                        break;
                    case 2:
                        $from = trim(text('Started at', placeholder: '09:00', default: $from, required: true, validate: $clock, hint: $this->hint()));
                        break;
                    case 3:
                        $to = trim(text(
                            label: 'Finished at',
                            placeholder: '10:30',
                            default: $to,
                            required: true,
                            validate: fn (string $v) => $clock($v) ?? (
                                $this->parseInputTime("{$date} {$v}")->lte($this->parseInputTime("{$date} {$from}"))
                                    ? 'Must be after the start time.'
                                    : null
                            ),
                            hint: $this->hint(),
                        ));
                        break;
                    case 4:
                        $description = $this->askDescription('What did you work on?', $description, $project->id);
                        break;
                    case 5:
                        $tags = $this->askTags($tags);
                        break;
                }
                $step++;
            } catch (FormRevertedException) {
                if ($step === 0) {
                    return null;
                }
                $step--;
            }
        }

        $this->storeFinished(
            $project,
            $this->parseInputTime("{$date} {$from}"),
            $this->parseInputTime("{$date} {$to}"),
            $description !== '' ? $description : null,
            $tags !== '' ? $tags : null,
        );

        return 0;
    }

    // ---------------- entries ----------------

    private function entryOptionLabel(TimeEntry $e): string
    {
        $end = $e->ended_at ? $this->fmtHm($e->ended_at) : 'now  ';
        $dur = str_pad($this->humanDur($this->effectiveSeconds($e)), 7, ' ', STR_PAD_LEFT);
        $tags = $e->tags->isEmpty() ? '' : '  ['.$e->tags->pluck('name')->implode(', ').']';

        return $this->tehran($e->started_at)->format('D m-d').'  '.$this->fmtHm($e->started_at).'→'.$end.'  '.$dur.'  '
            .$this->entrySummary($e).$tags.($e->ended_at ? '' : '  ● running');
    }

    /** @return bool true when the session should end (a new timer was started) */
    private function browseEntries(): bool
    {
        while (true) {
            try {
                $key = select(
                    label: 'Which period?',
                    options: [
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'week' => 'Last 7 days',
                        'month' => 'This month',
                        'all' => 'All time (latest 100)',
                        'back' => '← Back',
                    ],
                    default: 'today',
                    hint: $this->hint(),
                );
            } catch (FormRevertedException) {
                return false;
            }

            if ($key === 'back') {
                return false;
            }

            [$from, $to, $label] = $this->parseRange((string) $key, 'today');

            if ($this->browseEntryList($from, $to, $label)) {
                return true;
            }
        }
    }

    /** @return bool true when the session should end (a new timer was started) */
    private function browseEntryList(?Carbon $from, ?Carbon $to, string $label): bool
    {
        while (true) {
            $entries = $this->entriesForRange($from, $to)->take(100);

            if ($entries->isEmpty()) {
                info("No entries ({$label}).");

                return false;
            }

            $total = $entries->sum(fn (TimeEntry $e) => $this->effectiveSeconds($e));
            $options = [];
            foreach ($entries as $e) {
                $options[$e->id] = $this->entryOptionLabel($e);
            }
            $options['back'] = '← Back';

            try {
                $picked = select(
                    label: ucfirst($label).' — '.$entries->count().($entries->count() === 1 ? ' entry' : ' entries').', '.$this->humanDur((int) $total).' total',
                    options: $options,
                    scroll: 12,
                    hint: $this->hint('Enter on an entry to edit it or start a new timer from it'),
                );
            } catch (FormRevertedException) {
                return false;
            }

            if ($picked === 'back') {
                return false;
            }

            $entry = $entries->firstWhere('id', (int) $picked);
            if ($entry && $this->entryActions($entry)) {
                return true;
            }
        }
    }

    /** @return bool true when the session should end (a new timer was started) */
    private function entryActions(TimeEntry $entry): bool
    {
        while (true) {
            $entry = $entry->fresh(['project', 'tags']);
            if (! $entry) {
                return false;
            }

            $this->line('');
            $this->printEntryDetail($entry);
            $this->line('');

            try {
                $action = select(
                    label: "Entry #{$entry->id}",
                    options: [
                        'restart' => 'Start a new timer from this entry',
                        'description' => 'Edit description',
                        'project' => 'Change project',
                        'tags' => 'Edit tags',
                        'times' => 'Edit start / end time',
                        'back' => '← Back',
                    ],
                    scroll: 8,
                    hint: $this->hint(),
                );
            } catch (FormRevertedException) {
                return false;
            }

            try {
                switch ($action) {
                    case 'restart':
                        $this->restartEntry((string) $entry->id);

                        return true;

                    case 'description':
                        $new = $this->askDescription('Description', $entry->description ?? '', $entry->project_id);
                        $entry->description = $new !== '' ? $new : null;
                        $entry->save();
                        info('Description updated.');
                        break;

                    case 'project':
                        $project = $this->askProject('Move this entry to which project?');
                        if ($project) {
                            $entry->project_id = $project->id;
                            $entry->save();
                            info("Moved to {$project->name}.");
                        }
                        break;

                    case 'tags':
                        $this->syncTags($entry, $this->askTags($entry->tags->pluck('name')->implode(', ')));
                        info('Tags updated.');
                        break;

                    case 'times':
                        $this->editEntryTimes($entry);
                        break;

                    default:
                        return false;
                }
            } catch (FormRevertedException) {
                // Esc while editing a field: nothing was saved, back to the action list.
            }
        }
    }

    private function editEntryTimes(TimeEntry $entry): void
    {
        $running = $entry->ended_at === null;
        $hint = $this->hint('Tehran time', 'YYYY-MM-DD HH:MM, or HH:MM for today');
        $startInput = $this->tehran($entry->started_at)->format('Y-m-d H:i');
        $endInput = $running ? '' : $this->tehran($entry->ended_at)->format('Y-m-d H:i');

        $step = 0;
        while ($step < 2) {
            try {
                if ($step === 0) {
                    $startInput = trim(text(
                        label: 'Started at',
                        default: $startInput,
                        required: true,
                        validate: $this->timeValidator(),
                        hint: $hint,
                    ));
                } else {
                    $endInput = trim(text(
                        label: 'Finished at',
                        default: $endInput,
                        placeholder: $running ? 'leave empty to keep it running' : '',
                        required: ! $running,
                        validate: $this->timeValidator($running),
                        hint: $hint,
                    ));
                }
                $step++;
            } catch (FormRevertedException $e) {
                if ($step === 0) {
                    throw $e;
                }
                $step--;
            }
        }

        $end = $endInput !== '' ? $this->parseInputTime($endInput) : null;

        if ($error = $this->applyTimes($entry, $this->parseInputTime($startInput), $end)) {
            $this->error($error);

            return;
        }

        $entry->save();
        info('Times updated.');
    }

    // ---------------- stats / projects ----------------

    private function interactiveStats(): void
    {
        $key = 'week';
        $project = null;
        $ids = null;
        $step = 0;

        while ($step < 3) {
            try {
                if ($step === 0) {
                    $key = (string) select(
                        label: 'Which period?',
                        options: [
                            'today' => 'Today',
                            'yesterday' => 'Yesterday',
                            'week' => 'Last 7 days',
                            'month' => 'This month',
                            'all' => 'All time',
                        ],
                        default: $key,
                        hint: $this->hint(),
                    );
                    $step = 1;
                } elseif ($step === 1) {
                    $which = select('Which projects?', ['all' => 'All projects', 'pick' => 'Pick a project…'], default: 'all', hint: $this->hint());
                    $project = null;
                    $ids = null;
                    $step = $which === 'pick' ? 2 : 3;
                } else {
                    $project = $this->askProject('Stats for which project?');
                    $ids = $project?->getAllDescendantIds();
                    $step = 3;
                }
            } catch (FormRevertedException) {
                if ($step === 0) {
                    return;
                }
                $step = $step === 2 ? 1 : 0;
            }
        }

        [$from, $to, $label] = $this->parseRange($key, 'week');

        $this->line('');
        $this->renderStats($from, $to, $label, $project, $ids);
    }

    private function projectsMenu(): void
    {
        while (true) {
            try {
                $action = select(
                    label: 'Projects',
                    options: [
                        'list' => 'List active projects',
                        'all' => 'List all projects (including archived)',
                        'create' => 'Create a project',
                        'back' => '← Back',
                    ],
                    hint: $this->hint(),
                );
            } catch (FormRevertedException) {
                return;
            }

            try {
                switch ($action) {
                    case 'list':
                        $this->listProjects(false);
                        break;
                    case 'all':
                        $this->listProjects(true);
                        break;
                    case 'create':
                        $this->promptNewProject();
                        break;
                    default:
                        return;
                }
            } catch (FormRevertedException) {
                // Esc while creating a project: back to this menu.
            }

            $this->line('');
        }
    }
}
