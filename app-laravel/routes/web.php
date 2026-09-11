<?php

use App\Infrastructure\Pdf\FormatoSolicitudPdf;
use App\Livewire\Tramite\Paso1Preregistro;
use App\Livewire\Tramite\Paso2Documentos;
use App\Livewire\Tramite\Paso2Responsable;
use App\Livewire\Tramite\Paso3Placeholder;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\TipoDocumento;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    Route::get('/tramite/preregistro', Paso1Preregistro::class)->name('tramite.preregistro');

    Route::get('/tramite/paso2/{escuela}', Paso2Responsable::class)
        ->middleware('can:view,escuela')
        ->name('tramite.paso2');

    Route::get('/tramite/paso2/{escuela}/documentos', Paso2Documentos::class)
        ->middleware('can:view,escuela')
        ->name('tramite.paso2-documentos');

    Route::get('/tramite/paso2/{escuela}/documentos/formato-solicitud.pdf', function (Escuela $escuela, FormatoSolicitudPdf $pdf) {
        return $pdf->generar($escuela);
    })
        ->middleware('can:view,escuela')
        ->name('tramite.paso2-documentos.formato-solicitud');

    Route::get('/tramite/paso2/{escuela}/documentos/{clave}/archivo', function (Escuela $escuela, string $clave) {
        $tipo = TipoDocumento::where('clave', $clave)->firstOrFail();
        $ownerId = $tipo->ambito === 'plantel' ? $escuela->plantel_id : $escuela->id;

        $documento = $tipo->ambito === 'plantel'
            ? DocumentoPlantel::where('plantel_id', $ownerId)->where('tipo_documento_id', $tipo->id)->first()
            : DocumentoEscuela::where('escuela_id', $ownerId)->where('tipo_documento_id', $tipo->id)->first();

        abort_if($documento === null, 404);

        return Storage::disk('documentos')->response($documento->archivo_path);
    })
        ->middleware('can:view,escuela')
        ->name('tramite.paso2-documentos.descargar');

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
