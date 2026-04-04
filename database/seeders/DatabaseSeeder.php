<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $projects = [
            ['name' => 'Website Redesign', 'color' => '#6366f1', 'description' => 'Redesigning the company website'],
            ['name' => 'Mobile App', 'color' => '#10b981', 'description' => 'iOS and Android app development'],
            ['name' => 'API Development', 'color' => '#f59e0b', 'description' => 'REST API for third-party integrations'],
            ['name' => 'Bug Fixes', 'color' => '#ef4444', 'description' => 'General bug fixing and maintenance'],
            ['name' => 'Documentation', 'color' => '#8b5cf6', 'description' => 'Writing technical documentation'],
        ];

        $createdProjects = [];
        foreach ($projects as $projectData) {
            $project = Project::factory()->for($user)->create($projectData);
            $createdProjects[] = $project;
        }

        $descriptions = [
            'Implemented login functionality',
            'Fixed responsive layout issues',
            'Code review for pull request #42',
            'Database optimization and indexing',
            'Meeting with client about requirements',
            'Writing unit tests for auth module',
            'Deployed staging environment',
            'Refactored user service class',
            'Updated dependencies and security patches',
            'Created wireframes for dashboard',
            'Investigated production error logs',
            'Setup CI/CD pipeline',
        ];

        for ($daysAgo = 0; $daysAgo < 14; $daysAgo++) {
            $date = Carbon::now()->subDays($daysAgo);
            $entriesPerDay = rand(2, 5);

            for ($i = 0; $i < $entriesPerDay; $i++) {
                $hour = rand(8, 17);
                $minute = rand(0, 5) * 10;
                $startedAt = $date->copy()->setTime($hour, $minute);
                $duration = rand(900, 7200);
                $endedAt = (clone $startedAt)->addSeconds($duration);

                TimeEntry::factory()
                    ->for($user)
                    ->for($createdProjects[array_rand($createdProjects)])
                    ->create([
                        'description' => $descriptions[array_rand($descriptions)],
                        'started_at' => $startedAt,
                        'ended_at' => $endedAt,
                        'duration_seconds' => $duration,
                    ]);
            }
        }

        $today = Carbon::today();
        TimeEntry::factory()
            ->for($user)
            ->for($createdProjects[0])
            ->create([
                'description' => 'Working on current task',
                'started_at' => $today->copy()->setTime(9, 0),
                'ended_at' => null,
                'duration_seconds' => 0,
            ]);
    }
}
