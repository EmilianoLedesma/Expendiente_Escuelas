<?php

use App\Livewire\Tramite\Paso1Preregistro;
use App\Livewire\Tramite\Paso2Responsable;
use App\Livewire\Tramite\Paso3Placeholder;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    Route::get('/tramite/preregistro', Paso1Preregistro::class)->name('tramite.preregistro');

    Route::get('/tramite/paso2/{escuela}', Paso2Responsable::class)
        ->middleware('can:view,escuela')
        ->name('tramite.paso2');

    Route::get('/tramite/paso2/{escuela}/documentos', fn () => abort(501))
        ->middleware('can:view,escuela')
        ->name('tramite.paso2-documentos');

    Route::get('/tramite/paso3/{escuelaNivel}', Paso3Placeholder::class)
        ->middleware('can:view,escuelaNivel')
        ->name('tramite.paso3-placeholder');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
