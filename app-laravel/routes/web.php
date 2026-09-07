<?php

use App\Http\Livewire\Tramite\Paso1Preregistro;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/tramite/preregistro', Paso1Preregistro::class)->name('tramite.preregistro');

// Placeholder — Paso 2 itself is out of scope of this task.
Route::view('/tramite/paso2/{escuela}', 'tramite.paso2-placeholder')->name('tramite.paso2-placeholder');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
