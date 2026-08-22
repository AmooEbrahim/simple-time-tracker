<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $projects = auth()->user()
            ->projects()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount('timeEntries')->orderBy('is_archived')->orderByDesc('created_at')])
            ->withCount('timeEntries')
            ->orderBy('is_archived')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        auth()->user()->projects()->create($request->validated());

        return redirect()->route('projects.index');
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return redirect()->route('projects.index');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        if ($project->isSubProject()) {
            TimeEntry::where('project_id', $project->id)
                ->update(['project_id' => $project->parent_id]);
        } else {
            foreach ($project->children as $child) {
                TimeEntry::where('project_id', $child->id)
                    ->update(['project_id' => null]);
                $child->delete();
            }
        }

        $project->delete();

        return redirect()->route('projects.index');
    }

    public function archive(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update(['is_archived' => true]);

        return redirect()->route('projects.index');
    }

    public function unarchive(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update(['is_archived' => false]);

        return redirect()->route('projects.index');
    }
}
