<?php

use App\Livewire\Tramite\Paso1Preregistro;
use App\Models\Escuela;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    Route::get('/tramite/preregistro', Paso1Preregistro::class)->name('tramite.preregistro');

    // Placeholder body — replaced with Paso2Responsable::class in Task 9.
    Route::get('/tramite/paso2/{escuela}', function (Escuela $escuela) {
        return view('tramite.paso2-placeholder', ['escuela' => $escuela->id]);
    })->middleware('can:view,escuela')->name('tramite.paso2');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
