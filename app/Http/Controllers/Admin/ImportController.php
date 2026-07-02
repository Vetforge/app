<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AlimentImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('admin/Import');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $result = AlimentImporter::import($request->file('file')->getRealPath());

        return back()->with('success', "Import terminé : {$result['created']} créés, {$result['updated']} mis à jour, {$result['errors']} erreurs.");
    }
}
