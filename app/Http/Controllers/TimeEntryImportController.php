<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeEntryImportController extends Controller
{
    private const USER_TZ = 'Asia/Tehran';

    private const MAX_ROWS = 1000;

    public function create(): Response
    {
        $projects = auth()->user()
            ->projects()
            ->orderBy('is_archived')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'color', 'is_archived']);

        $parentNames = auth()->user()
            ->projects()
            ->pluck('name', 'id');

        $projects = $projects->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'parent_id' => $p->parent_id,
            'parent_name' => $p->parent_id ? ($parentNames[$p->parent_id] ?? null) : null,
            'color' => $p->color,
            'is_archived' => (bool) $p->is_archived,
        ])->values();

        return Inertia::render('Import/Index', [
            'projects' => $projects,
        ]);
    }

    public function sample(): StreamedResponse
    {
        $filename = 'time-entries-import-sample.csv';

        return response()->streamDownload(function () {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['description', 'project_id', 'date', 'start_time', 'end_time', 'tags']);
            fputcsv($fh, ['Design homepage', 1, '2026-09-01', '09:00', '11:30', 'design;urgent']);
            fputcsv($fh, ['Fix login bug', 2, '2026-09-01', '13:00', '14:15', '']);
            fputcsv($fh, ['Team meeting', 1, '2026-09-02', '10:00:00', '10:30:00', 'meeting']);
            fclose($fh);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('file')->getRealPath();

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return back()->withErrors(['file' => 'Could not read the uploaded file.']);
        }

        // Strip UTF-8 BOM from first header cell if present.
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return back()->withErrors(['file' => 'The file is empty.']);
        }
        $header = array_map(fn ($h) => trim((string) $h), $header);
        if (isset($header[0])) {
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        }

        $columnMap = $this->mapColumns($header);

        $missing = [];
        foreach (['project_id', 'date', 'start_time', 'end_time'] as $required) {
            if (! isset($columnMap[$required])) {
                $missing[] = $required;
            }
        }

        if (! empty($missing)) {
            fclose($handle);

            return back()->withErrors([
                'file' => 'Missing required column(s): '.implode(', ', $missing).'. Expected header: description,project_id,date,start_time,end_time,tags',
            ]);
        }

        $ownedProjectIds = auth()->user()->projects()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ownedProjectLookup = array_flip($ownedProjectIds);

        $rows = [];
        $rowErrors = [];
        $lineNumber = 1; // header is line 1

        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;

            // Skip fully empty lines.
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                return back()->withErrors([
                    'file' => 'Too many rows. Maximum '.self::MAX_ROWS.' entries per import.',
                ]);
            }

            $row = [];
            foreach ($columnMap as $canonical => $index) {
                $row[$canonical] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }

            $error = $this->validateRow($row, $lineNumber, $ownedProjectLookup);
            if ($error !== null) {
                $rowErrors[] = $error;

                continue;
            }

            $rows[] = $this->buildAttributes($row);
        }

        fclose($handle);

        if (empty($rows) && empty($rowErrors)) {
            return back()->withErrors(['file' => 'No data rows found in the file.']);
        }

        if (! empty($rowErrors)) {
            return back()
                ->withErrors(['file' => count($rowErrors).' row(s) have errors. Nothing was imported — fix the file and try again.'])
                ->with('importErrors', array_slice($rowErrors, 0, 20))
                ->with('importErrorCount', count($rowErrors));
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $entry = TimeEntry::create([
                    'user_id' => auth()->id(),
                    'project_id' => $row['project_id'],
                    'description' => $row['description'] !== '' ? $row['description'] : null,
                    'started_at' => $row['started_at'],
                    'ended_at' => $row['ended_at'],
                    'duration_seconds' => $row['started_at']->diffInSeconds($row['ended_at']),
                ]);

                if (! empty($row['tags'])) {
                    $tagIds = [];
                    foreach ($row['tags'] as $tagName) {
                        $tag = Tag::firstOrCreate(
                            ['user_id' => auth()->id(), 'name' => $tagName],
                            ['name' => $tagName]
                        );
                        $tagIds[] = $tag->id;
                    }
                    $entry->tags()->sync(array_unique($tagIds));
                }
            }
        });

        return to_route('dashboard')->with('success', 'Imported '.count($rows).' time '.(count($rows) === 1 ? 'entry' : 'entries').'.');
    }

    /**
     * Map normalized header names to canonical column keys.
     *
     * @return array<string, int>
     */
    protected function mapColumns(array $header): array
    {
        $map = [];

        foreach ($header as $index => $name) {
            $key = strtolower($name);
            $key = str_replace([' ', '-', '_'], '', $key);

            $canonical = match ($key) {
                'description', 'desc', 'notes', 'note', 'title', 'what' => 'description',
                'projectid', 'project' => 'project_id',
                'date', 'day' => 'date',
                'starttime', 'start', 'from' => 'start_time',
                'endtime', 'end', 'to' => 'end_time',
                'tags', 'tag', 'labels', 'label' => 'tags',
                default => null,
            };

            if ($canonical !== null && ! isset($map[$canonical])) {
                $map[$canonical] = $index;
            }
        }

        return $map;
    }

    protected function validateRow(array $row, int $lineNumber, array $ownedProjectLookup): ?string
    {
        $prefix = "Row {$lineNumber}: ";

        if ($row['project_id'] === '') {
            return $prefix.'project_id is required.';
        }
        if (! ctype_digit($row['project_id'])) {
            return $prefix.'project_id must be a number (your project ID from the Projects page).';
        }
        if (! isset($ownedProjectLookup[(int) $row['project_id']])) {
            return $prefix."project_id {$row['project_id']} does not belong to you.";
        }

        if ($row['date'] === '') {
            return $prefix.'date is required (YYYY-MM-DD).';
        }
        try {
            $date = Carbon::createFromFormat('Y-m-d', $row['date']);
            if ($date === false || $date->format('Y-m-d') !== $row['date']) {
                return $prefix."date '{$row['date']}' must be YYYY-MM-DD (e.g. 2026-09-01).";
            }
        } catch (\Throwable) {
            return $prefix."date '{$row['date']}' must be YYYY-MM-DD (e.g. 2026-09-01).";
        }

        foreach (['start_time' => 'start_time', 'end_time' => 'end_time'] as $field => $label) {
            if ($row[$field] === '') {
                return $prefix."{$label} is required (HH:MM, 24-hour).";
            }
            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $row[$field])) {
                return $prefix."{$label} '{$row[$field]}' must be HH:MM 24-hour (e.g. 09:30).";
            }
        }

        try {
            $start = Carbon::createFromFormat('Y-m-d H:i:s', $row['date'].' '.$this->normalizeTime($row['start_time']), self::USER_TZ);
            $end = Carbon::createFromFormat('Y-m-d H:i:s', $row['date'].' '.$this->normalizeTime($row['end_time']), self::USER_TZ);
        } catch (\Throwable) {
            return $prefix.'could not parse date/time combination.';
        }

        if ($start === false || $end === false) {
            return $prefix.'could not parse date/time combination.';
        }

        if (! $end->greaterThan($start)) {
            return $prefix.'end_time must be after start_time.';
        }

        if (isset($row['description']) && mb_strlen($row['description']) > 500) {
            return $prefix.'description must be 500 characters or less.';
        }

        foreach ($this->parseTags($row['tags'] ?? '') as $tagName) {
            if (mb_strlen($tagName) > 50) {
                return $prefix."tag '{$tagName}' must be 50 characters or less.";
            }
        }

        return null;
    }

    /**
     * @return array{project_id: int, description: string, started_at: Carbon, ended_at: Carbon, tags: string[]}
     */
    protected function buildAttributes(array $row): array
    {
        $startedAt = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $row['date'].' '.$this->normalizeTime($row['start_time']),
            self::USER_TZ
        );
        $endedAt = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $row['date'].' '.$this->normalizeTime($row['end_time']),
            self::USER_TZ
        );

        return [
            'project_id' => (int) $row['project_id'],
            'description' => $row['description'] ?? '',
            'started_at' => $startedAt->copy()->timezone('UTC'),
            'ended_at' => $endedAt->copy()->timezone('UTC'),
            'tags' => $this->parseTags($row['tags'] ?? ''),
        ];
    }

    /** @return string[] */
    protected function parseTags(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return collect(preg_split('/[;|]/', $raw))
            ->map(fn ($t) => trim((string) $t))
            ->filter(fn ($t) => $t !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeTime(string $time): string
    {
        return substr_count($time, ':') === 1 ? $time.':00' : $time;
    }
}
