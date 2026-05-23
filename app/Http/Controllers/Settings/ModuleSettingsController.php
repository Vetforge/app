<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateModuleSettingsRequest;
use App\Models\UserModuleSetting;
use App\Support\VeterinaryModules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleSettingsController extends Controller
{
    public function edit(Request $request, string $module): Response
    {
        VeterinaryModules::assertExists($module);

        return Inertia::render('settings/ModuleSettings', [
            'module' => ['slug' => $module, ...VeterinaryModules::get($module)],
            'modules' => VeterinaryModules::navigation(),
            'settings' => VeterinaryModules::settingsForUser($request->user(), $module),
            'defaults' => VeterinaryModules::defaultSettings($module),
        ]);
    }

    public function update(UpdateModuleSettingsRequest $request, string $module): RedirectResponse
    {
        $settings = VeterinaryModules::normalizeSettings($module, $request->validated('settings'));

        UserModuleSetting::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => $module],
            ['settings' => $settings],
        );

        return back()->with('success', 'Reglages du module mis a jour.');
    }

    public function destroy(Request $request, string $module): RedirectResponse
    {
        VeterinaryModules::assertExists($module);

        UserModuleSetting::query()
            ->where('user_id', $request->user()->id)
            ->where('module', $module)
            ->delete();

        return back()->with('success', 'Reglages du module reinitialises.');
    }
}
