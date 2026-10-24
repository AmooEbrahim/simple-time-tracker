<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => $request->user()->calendarSettings(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Settings saved.');
    }
}
