<?php

use App\Http\Controllers\Tramite\DescargarDocumentoController;
use App\Http\Controllers\Tramite\FormatoSolicitudPdfController;
use App\Http\Controllers\Tramite\MisTramitesController;
use App\Http\Controllers\Tramite\ResumenTramiteController;
use App\Livewire\Tramite\Paso1Preregistro;
use App\Livewire\Tramite\Paso2Documentos;
use App\Livewire\Tramite\Paso2Responsable;
use App\Livewire\Tramite\Paso3\DatosInmueble;
use App\Livewire\Tramite\Paso3\InfraestructuraNivel;
use App\Livewire\Tramite\Paso3\MobiliarioNivel;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/tramite', MisTramitesController::class)->name('tramite.index');

    Route::get('/tramite/preregistro', Paso1Preregistro::class)->name('tramite.preregistro');

    Route::get('/tramite/{escuela}', ResumenTramiteController::class)
        ->whereNumber('escuela')
        ->middleware('can:view,escuela')
        ->name('tramite.resumen');

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

    // Ya no es una página: el hub muestra el estado de cada nivel (rediseño UI, D12). Se conserva el nombre de ruta.
    Route::get('/tramite/paso3/{escuelaNivel}/proximos-pasos', fn (EscuelaNivel $escuelaNivel) => redirect()->route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id]))
        ->middleware('can:view,escuelaNivel')
        ->name('tramite.paso3-proximos-pasos');
});

// Breeze views still link to 'dashboard'; it only routes by role (WS-1.7).
Route::get('dashboard', fn () => redirect(auth()->user()->hasRole('sedeq') ? '/admin' : route('tramite.index')))
    ->middleware('auth')
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
