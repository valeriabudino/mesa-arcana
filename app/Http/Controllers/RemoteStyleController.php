<?php

namespace App\Http\Controllers;

use App\Events\RemoteStyleUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RemoteStyleController extends Controller
{
    public function emisor(): View
    {
        return view('emisor');
    }

    public function receptor(): View
    {
        return view('receptor');
    }

    public function emitir(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'newText' => ['required', 'string'],
            'newStyle' => ['nullable', 'string'],
        ]);

        broadcast(new RemoteStyleUpdate(
            $data['newText'],
            $data['newStyle'] ?? '',
        ));

        return back()->with('ok', 'Mensaje emitido correctamente.');
    }
}
