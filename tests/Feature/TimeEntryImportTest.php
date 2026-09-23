<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TimeEntryImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_page_renders_with_projects(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('import'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Import/Index')
            ->has('projects', 1)
        );
    }

    public function test_valid_csv_imports_entries_with_tags(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $csv = "description,project_id,date,start_time,end_time,tags\n"
            ."Design homepage,{$project->id},2026-09-01,09:00,11:30,design;urgent\n"
            ."Fix bug,{$project->id},2026-09-01,13:00,14:15,\n";

        $file = UploadedFile::fake()->createWithContent('entries.csv', $csv);

        $response = $this->actingAs($user)->post(route('import.store'), ['file' => $file]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('time_entries', 2);
        $this->assertDatabaseHas('time_entries', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'description' => 'Design homepage',
            'duration_seconds' => 9000,
        ]);
        $this->assertDatabaseHas('tags', ['user_id' => $user->id, 'name' => 'design']);
        $this->assertDatabaseHas('tags', ['user_id' => $user->id, 'name' => 'urgent']);
    }

    public function test_import_rejects_unknown_project_and_imports_nothing(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $csv = "description,project_id,date,start_time,end_time,tags\n"
            ."Good row,{$project->id},2026-09-01,09:00,10:00,\n"
            ."Bad row,999999,2026-09-01,09:00,10:00,\n";

        $file = UploadedFile::fake()->createWithContent('entries.csv', $csv);

        $response = $this->actingAs($user)->post(route('import.store'), ['file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_import_rejects_other_users_project(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $other->id]);

        $csv = "description,project_id,date,start_time,end_time,tags\n"
            ."Sneaky,{$otherProject->id},2026-09-01,09:00,10:00,\n";

        $file = UploadedFile::fake()->createWithContent('entries.csv', $csv);

        $response = $this->actingAs($user)->post(route('import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_import_requires_project_id_column(): void
    {
        $user = User::factory()->create();

        $csv = "description,date,start_time,end_time\n"
            ."No project,2026-09-01,09:00,10:00\n";

        $file = UploadedFile::fake()->createWithContent('entries.csv', $csv);

        $response = $this->actingAs($user)->post(route('import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_sample_downloads_csv(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('import.sample'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('project_id', $response->streamedContent());
    }
}
