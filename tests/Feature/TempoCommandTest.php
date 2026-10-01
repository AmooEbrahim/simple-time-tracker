<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TempoCommandTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_smart_start_creates_project_timer_and_stops_previous(): void
    {
        $user = $this->user();
        $alpha = Project::create(['user_id' => $user->id, 'name' => 'Alpha']);
        $beta = Project::create(['user_id' => $user->id, 'name' => 'Beta']);

        $this->artisan('tempo', ['args' => ['alp'], '--user' => $user->id, '--yes' => true])
            ->assertSuccessful();

        $running = TimeEntry::where('user_id', $user->id)->whereNull('ended_at')->first();
        $this->assertNotNull($running);
        $this->assertSame($alpha->id, $running->project_id);

        // Starting a second timer auto-stops the first (single running invariant).
        $this->artisan('tempo', ['args' => ['bet'], '--user' => $user->id, '--yes' => true, '--message' => 'doing things', '--tags' => 'x,y'])
            ->assertSuccessful();

        $this->assertSame(0, TimeEntry::where('user_id', $user->id)->whereNull('ended_at')->where('project_id', $alpha->id)->count());
        $nowRunning = TimeEntry::where('user_id', $user->id)->whereNull('ended_at')->first();
        $this->assertSame($beta->id, $nowRunning->project_id);
        $this->assertSame('doing things', $nowRunning->description);
        $this->assertSame(['x', 'y'], $nowRunning->tags()->pluck('name')->sort()->values()->all());
    }

    public function test_stop_sets_description_and_end(): void
    {
        $user = $this->user();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Alpha']);

        $this->artisan('tempo', ['args' => ['Alpha'], '--user' => $user->id])->assertSuccessful();
        $this->artisan('tempo', ['--stop' => true, '--user' => $user->id, '--message' => 'finished it'])
            ->assertSuccessful();

        $entry = TimeEntry::where('user_id', $user->id)->first();
        $this->assertNotNull($entry->ended_at);
        $this->assertGreaterThan(0, $entry->duration_seconds);
        $this->assertSame('finished it', $entry->description);
        $this->assertSame($project->id, $entry->project_id);
    }

    public function test_update_entry_message_and_status_list_stats(): void
    {
        $user = $this->user();
        Project::create(['user_id' => $user->id, 'name' => 'Alpha']);
        $entry = TimeEntry::create([
            'user_id' => $user->id,
            'project_id' => null,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(30),
            'duration_seconds' => 1800,
        ]);

        $this->artisan('tempo', ['--id' => (string) $entry->id, '--user' => $user->id, '--message' => 'note here'])
            ->assertSuccessful();
        $this->assertSame('note here', $entry->fresh()->description);

        $this->artisan('tempo', ['--user' => $user->id, '--status' => true])->assertSuccessful();
        $this->artisan('tempo', ['--user' => $user->id, '--no-interaction' => true])->assertSuccessful();
        $this->artisan('tempo', ['--list' => true, '--user' => $user->id])->assertSuccessful();
        $this->artisan('tempo', ['--stats' => true, '--user' => $user->id])->assertSuccessful();
        $this->artisan('tempo', ['--projects' => true, '--user' => $user->id])->assertSuccessful();
    }

    public function test_create_and_manual_add(): void
    {
        $user = $this->user();

        $this->artisan('tempo', ['args' => ['Manual Project'], '--create' => true, '--user' => $user->id])
            ->assertSuccessful();

        $this->artisan('tempo', [
            'args' => ['Manual'],
            '--add' => true,
            '--user' => $user->id,
            '--yes' => true,
            '--start' => '2026-09-20 10:00',
            '--finish' => '2026-09-20 11:30',
            '--message' => 'manual work',
        ])->assertSuccessful();

        $entry = TimeEntry::where('user_id', $user->id)->first();
        $this->assertSame(5400, $entry->duration_seconds);
        $this->assertSame('manual work', $entry->description);
    }

    public function test_menu_start_flow_picks_project_description_and_tags(): void
    {
        $user = $this->user();
        Project::create(['user_id' => $user->id, 'name' => 'Alpha']);
        $beta = Project::create(['user_id' => $user->id, 'name' => 'Beta']);

        $this->artisan('tempo', ['--user' => $user->id])
            ->expectsQuestion('What would you like to do?', 'start')
            ->expectsQuestion('Start tracking which project?', $beta->id)
            ->expectsQuestion('What are you working on?', 'writing tests')
            ->expectsQuestion('Tags', 'cli, qa')
            ->assertSuccessful();

        $running = TimeEntry::where('user_id', $user->id)->whereNull('ended_at')->first();
        $this->assertSame($beta->id, $running->project_id);
        $this->assertSame('writing tests', $running->description);
        $this->assertSame(['cli', 'qa'], $running->tags()->pluck('name')->sort()->values()->all());
    }

    public function test_menu_stop_asks_description_prefilled_and_stops(): void
    {
        $user = $this->user();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Alpha']);
        $entry = TimeEntry::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'description' => 'old note',
            'started_at' => now()->subMinutes(20),
        ]);

        $this->artisan('tempo', ['--user' => $user->id])
            ->expectsQuestion('What would you like to do?', 'stop')
            ->expectsQuestion('What did you work on?', 'new note')
            ->assertSuccessful();

        $entry->refresh();
        $this->assertNotNull($entry->ended_at);
        $this->assertSame('new note', $entry->description);
        $this->assertGreaterThanOrEqual(1200, $entry->duration_seconds);
    }

    public function test_menu_can_create_a_project_inline_while_starting(): void
    {
        $user = $this->user();

        $this->artisan('tempo', ['--user' => $user->id])
            ->expectsQuestion('What would you like to do?', 'start')
            ->expectsQuestion('Start tracking which project?', '__new')
            ->expectsQuestion('Project name', 'Brand New')
            ->expectsQuestion('Color', '#22c55e')
            ->expectsQuestion('What are you working on?', '')
            ->expectsQuestion('Tags', '')
            ->assertSuccessful();

        $project = Project::where('user_id', $user->id)->where('name', 'Brand New')->first();
        $this->assertNotNull($project);
        $this->assertSame('#22c55e', $project->color);
        $this->assertSame($project->id, TimeEntry::where('user_id', $user->id)->whereNull('ended_at')->value('project_id'));
    }

    public function test_menu_manual_add_wizard(): void
    {
        $user = $this->user();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Alpha']);

        $this->artisan('tempo', ['--user' => $user->id])
            ->expectsQuestion('What would you like to do?', 'add')
            ->expectsQuestion('Which project was it?', $project->id)
            ->expectsQuestion('Date', '2026-09-20')
            ->expectsQuestion('Started at', '09:00')
            ->expectsQuestion('Finished at', '10:15')
            ->expectsQuestion('What did you work on?', 'hand-entered')
            ->expectsQuestion('Tags', '')
            ->assertSuccessful();

        $entry = TimeEntry::where('user_id', $user->id)->first();
        $this->assertSame(4500, $entry->duration_seconds);
        $this->assertSame('hand-entered', $entry->description);
        $this->assertSame('2026-09-20 05:30:00', $entry->started_at->format('Y-m-d H:i:s')); // 09:00 Tehran (+03:30)
    }

    public function test_menu_entry_editor_changes_description_and_times(): void
    {
        $user = $this->user();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Alpha']);
        $entry = TimeEntry::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
            'duration_seconds' => 3600,
        ]);

        $this->artisan('tempo', ['--user' => $user->id])
            ->expectsQuestion('What would you like to do?', 'entries')
            ->expectsQuestion('Which period?', 'today')
            ->expectsQuestion('Today — 1 entry, 1h00m total', $entry->id)
            ->expectsQuestion("Entry #{$entry->id}", 'times')
            ->expectsQuestion('Started at', '2026-09-20 10:00')
            ->expectsQuestion('Finished at', '2026-09-20 12:30')
            ->expectsQuestion("Entry #{$entry->id}", 'description')
            ->expectsQuestion('Description', 'retro-fixed')
            ->expectsQuestion("Entry #{$entry->id}", 'back')
            // the entry was moved to another day, so "today" is now empty and we land back on the period picker
            ->expectsQuestion('Which period?', 'back')
            ->expectsQuestion('What would you like to do?', 'quit')
            ->assertSuccessful();

        $entry->refresh();
        $this->assertSame(9000, $entry->duration_seconds);
        $this->assertSame('retro-fixed', $entry->description);
    }

    public function test_multiple_matches_use_a_select_and_can_be_cancelled(): void
    {
        $user = $this->user();
        Project::create(['user_id' => $user->id, 'name' => 'Sabt One']);
        $two = Project::create(['user_id' => $user->id, 'name' => 'Sabt Two']);

        $this->artisan('tempo', ['args' => ['sabt'], '--user' => $user->id])
            ->expectsQuestion("Multiple projects match 'sabt'", $two->id)
            ->assertSuccessful();

        $this->assertSame($two->id, TimeEntry::where('user_id', $user->id)->whereNull('ended_at')->value('project_id'));

        $this->artisan('tempo', ['args' => ['sabt'], '--user' => $user->id])
            ->expectsQuestion("Multiple projects match 'sabt'", 0)
            ->assertFailed();
    }

    public function test_big_project_lists_switch_to_type_to_filter_search(): void
    {
        $user = $this->user();
        foreach (range(1, 14) as $i) {
            Project::create(['user_id' => $user->id, 'name' => "Filler {$i}"]);
        }
        $needle = Project::create(['user_id' => $user->id, 'name' => 'Needle In Haystack']);

        $this->artisan('tempo', ['--user' => $user->id])
            ->expectsQuestion('What would you like to do?', 'start')
            ->expectsQuestion('Start tracking which project?', 'needle')   // the search box query
            ->expectsQuestion('Start tracking which project?', $needle->id) // the highlighted result
            ->expectsQuestion('What are you working on?', '')
            ->expectsQuestion('Tags', '')
            ->assertSuccessful();

        $this->assertSame($needle->id, TimeEntry::where('user_id', $user->id)->whereNull('ended_at')->value('project_id'));
    }

    public function test_status_flag_and_non_interactive_runs_skip_the_menu(): void
    {
        $user = $this->user();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Alpha']);
        TimeEntry::create(['user_id' => $user->id, 'project_id' => $project->id, 'started_at' => now()->subMinutes(5)]);

        // No prompts expected: any question would fail the test.
        $this->artisan('tempo', ['--user' => $user->id, '--status' => true])
            ->expectsOutputToContain('Alpha')
            ->assertSuccessful();
        $this->artisan('tempo', ['--user' => $user->id, '--no-interaction' => true])
            ->expectsOutputToContain('Alpha')
            ->assertSuccessful();
    }
}
