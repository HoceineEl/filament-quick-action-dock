<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

Route::get('/_workbench/login', function (Request $request): RedirectResponse {
    Auth::login(User::query()->firstOrFail());
    $request->session()->regenerate();

    return redirect('/'.ltrim((string) $request->query('to', 'admin'), '/'));
});
