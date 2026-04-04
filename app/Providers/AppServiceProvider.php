<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Policies\ProjectPolicy;
use App\Policies\TimeEntryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->registerPolicies();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(TimeEntry::class, TimeEntryPolicy::class);
    }
}
