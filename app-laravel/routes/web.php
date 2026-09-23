<?php

use App\Http\Controllers\Tramite\DescargarDocumentoController;
use App\Http\Controllers\Tramite\FormatoSolicitudPdfController;
use App\Livewire\Tramite\Paso1Preregistro;
use App\Livewire\Tramite\Paso2Documentos;
use App\Livewire\Tramite\Paso2Responsable;
use App\Livewire\Tramite\Paso3\DatosInmueble;
use App\Livewire\Tramite\Paso3\InfraestructuraNivel;
use App\Livewire\Tramite\Paso3\MobiliarioNivel;
use App\Livewire\Tramite\Paso3ProximosPasos;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    Route::get('/tramite/preregistro', Paso1Preregistro::class)->name('tramite.preregistro');

    Route::get('/tramite/paso2/{escuela}', Paso2Responsable::class)
        ->middleware('can:update,escuela')
        ->name('tramite.paso2');

    Route::get('/tramite/paso2/{escuela}/documentos', Paso2Documentos::class)
        ->middleware('can:update,escuela')
        ->name('tramite.paso2-documentos');

    Route::get('/tramite/paso2/{escuela}/documentos/formato-solicitud.pdf', FormatoSolicitudPdfController::class)
        ->middleware('can:view,escuela')
        ->name('tramite.paso2-documentos.formato-solicitud');

    Route::get('/tramite/paso2/{escuela}/documentos/{clave}/archivo', DescargarDocumentoController::class)
        ->middleware('can:view,escuela')
        ->name('tramite.paso2-documentos.descargar');

    Route::get('/tramite/paso3/{escuelaNivel}', DatosInmueble::class)
        ->middleware('can:update,escuelaNivel')
        ->name('tramite.paso3-inmueble');

    Route::get('/tramite/paso3/{escuelaNivel}/infraestructura', InfraestructuraNivel::class)
        ->middleware('can:update,escuelaNivel')
        ->name('tramite.paso3-infraestructura');

    Route::get('/tramite/paso3/{escuelaNivel}/mobiliario', MobiliarioNivel::class)
        ->middleware('can:update,escuelaNivel')
        ->name('tramite.paso3-mobiliario');

    Route::get('/tramite/paso3/{escuelaNivel}/proximos-pasos', Paso3ProximosPasos::class)
        ->middleware('can:view,escuelaNivel')
        ->name('tramite.paso3-proximos-pasos');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
