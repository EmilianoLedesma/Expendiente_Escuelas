<?php

use App\Application\Revision\RutaInicio;
use App\Http\Controllers\Tramite\AnexoInstalacionesPdfController;
use App\Http\Controllers\Tramite\DescargarDocumentoController;
use App\Http\Controllers\Tramite\DescargarDocumentoNivelController;
use App\Http\Controllers\Tramite\DescargarReporteEnviadoController;
use App\Http\Controllers\Tramite\DescargarReporteValidacionController;
use App\Http\Controllers\Tramite\EliminarTramiteController;
use App\Http\Controllers\Tramite\FormatoSolicitudPdfController;
use App\Http\Controllers\Tramite\MisTramitesController;
use App\Http\Controllers\Tramite\ReenviarCorreccionesController;
use App\Http\Controllers\Tramite\ResumenTramiteController;
use App\Livewire\Cuenta\Perfil;
use App\Livewire\Sedeq\ColaRevisor;
use App\Livewire\Sedeq\RevisionNivel;
use App\Livewire\Sedeq\Revisores;
use App\Livewire\Sedeq\TableroSupervisor;
use App\Livewire\Sedeq\TramiteLectura;
use App\Livewire\Tramite\DatosPlantel;
use App\Livewire\Tramite\Paso1Preregistro;
use App\Livewire\Tramite\Paso24DocumentosNivel;
use App\Livewire\Tramite\Paso2Documentos;
use App\Livewire\Tramite\Paso2Responsable;
use App\Livewire\Tramite\Paso3\DatosInmueble;
use App\Livewire\Tramite\Paso3\InfraestructuraNivel;
use App\Livewire\Tramite\Paso3\Matricula;
use App\Livewire\Tramite\Paso3\MobiliarioNivel;
use App\Livewire\Tramite\Paso3\PlanEstudios;
use App\Livewire\Tramite\Paso3\PlantillaDocente;
use App\Livewire\Tramite\ResponsablesNivel;
use App\Livewire\Tramite\ValidacionFinal;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', Perfil::class)->name('profile');

    Route::get('/sedeq', TableroSupervisor::class)->middleware('rol:supervisor')->name('sedeq.tablero');

    Route::get('/sedeq/cola', ColaRevisor::class)->middleware('rol:revisor')->name('sedeq.cola');

    Route::get('/sedeq/revisores', Revisores::class)->middleware('rol:supervisor')->name('sedeq.revisores');

    // Solo lectura: `view` admite a SEDEQ (AccesoSedeq) y ConsultarTramiteSedeq lo revalida.
    Route::get('/sedeq/tramite/{escuela}', TramiteLectura::class)
        ->whereNumber('escuela')
        ->middleware(['rol:supervisor|revisor', 'can:view,escuela'])
        ->name('sedeq.tramite');

    // Reporte guardado al envío (2026-10-20): cubre todos los niveles, así que exige verlos todos (verReporteEnviado).
    Route::get('/sedeq/tramite/{escuela}/reporte.pdf', DescargarReporteEnviadoController::class)
        ->whereNumber('escuela')
        ->middleware(['rol:supervisor|revisor', 'can:verReporteEnviado,escuela'])
        ->name('sedeq.tramite.reporte');

    // El acceso lo imponen los casos de uso de revisión (AuthorizationException => 403).
    Route::get('/sedeq/revision/{escuelaNivel}', RevisionNivel::class)
        ->whereNumber('escuelaNivel')
        ->middleware('rol:supervisor|revisor')
        ->name('sedeq.revision');

    Route::get('/tramite', MisTramitesController::class)->name('tramite.index');

    Route::get('/tramite/preregistro', Paso1Preregistro::class)->middleware('solicitante')->name('tramite.preregistro');

    Route::get('/tramite/responsables', ResponsablesNivel::class)->middleware('solicitante')->name('tramite.responsables');

    Route::get('/tramite/{escuela}', ResumenTramiteController::class)
        ->whereNumber('escuela')
        ->middleware('can:verResumen,escuela')
        ->name('tramite.resumen');

    // Un segundo envío (trámite ya borrado) vuelve a Mis trámites en vez de un 404.
    Route::delete('/tramite/{escuela}', EliminarTramiteController::class)
        ->whereNumber('escuela')
        ->middleware('can:delete,escuela')
        ->missing(fn () => redirect()->route('tramite.index')->with('toast', ['tipo' => 'info', 'titulo' => 'El trámite ya no existe']))
        ->name('tramite.eliminar');

    // Devolución: el dueño reenvía las correcciones de una ronda cerrada (ReenviarCorrecciones).
    Route::post('/tramite/{escuela}/reenviar', ReenviarCorreccionesController::class)
        ->whereNumber('escuela')
        ->middleware('can:reenviar,escuela')
        ->name('tramite.reenviar');

    // WS-7b-1 U1: teléfono y correo del plantel, editables hasta el envío (el domicilio no).
    Route::get('/tramite/{escuela}/plantel', DatosPlantel::class)
        ->whereNumber('escuela')
        ->middleware("can:editarPaso,escuela,'plantel'")
        ->name('tramite.plantel');

    Route::get('/tramite/paso2/{escuela}', Paso2Responsable::class)
        ->middleware("can:editarPaso,escuela,'responsable','niveles'")
        ->name('tramite.paso2');

    Route::get('/tramite/paso2/{escuela}/documentos', Paso2Documentos::class)
        ->middleware("can:editarPaso,escuela,'documentos'")
        ->name('tramite.paso2-documentos');

    Route::get('/tramite/paso2/{escuela}/documentos/{clave}/archivo', DescargarDocumentoController::class)
        ->middleware('can:view,escuela')
        ->name('tramite.paso2-documentos.descargar');

    Route::get('/tramite/paso2/nivel/{escuelaNivel}/documentos', Paso24DocumentosNivel::class)
        ->whereNumber('escuelaNivel')
        ->middleware("can:editarPaso,escuelaNivel,'documentos_nivel'")
        ->name('tramite.paso2-nivel-documentos');

    Route::get('/tramite/paso2/nivel/{escuelaNivel}/documentos/formato-solicitud.pdf', FormatoSolicitudPdfController::class)
        ->whereNumber('escuelaNivel')
        ->middleware('can:view,escuelaNivel')
        ->name('tramite.paso2-nivel-documentos.formato-solicitud');

    Route::get('/tramite/paso2/nivel/{escuelaNivel}/documentos/{clave}/archivo', DescargarDocumentoNivelController::class)
        ->whereNumber('escuelaNivel')
        ->middleware('can:view,escuelaNivel')
        ->name('tramite.paso2-nivel-documentos.descargar');

    // Último paso (ADR-007): validación documental final.
    Route::get('/tramite/{escuela}/validacion', ValidacionFinal::class)
        ->whereNumber('escuela')
        ->middleware('can:update,escuela')
        ->name('tramite.validacion');

    Route::get('/tramite/{escuela}/validacion/{evaluacion}/reporte.pdf', DescargarReporteValidacionController::class)
        ->whereNumber(['escuela', 'evaluacion'])
        ->middleware('can:verReporte,escuela')
        ->name('tramite.validacion.reporte');

    Route::get('/tramite/paso3/{escuelaNivel}', DatosInmueble::class)
        ->middleware("can:editarPaso,escuelaNivel,'inmueble'")
        ->name('tramite.paso3-inmueble');

    Route::get('/tramite/paso3/{escuelaNivel}/infraestructura', InfraestructuraNivel::class)
        ->middleware("can:editarPaso,escuelaNivel,'infraestructura'")
        ->name('tramite.paso3-infraestructura');

    Route::get('/tramite/paso3/{escuelaNivel}/infraestructura/anexo-instalaciones.pdf', AnexoInstalacionesPdfController::class)
        ->whereNumber('escuelaNivel')
        ->middleware('can:descargarAnexo,escuelaNivel')
        ->name('tramite.paso3.infraestructura.anexo-instalaciones');

    Route::get('/tramite/paso3/{escuelaNivel}/mobiliario', MobiliarioNivel::class)
        ->middleware("can:editarPaso,escuelaNivel,'mobiliario'")
        ->name('tramite.paso3-mobiliario');

    Route::get('/tramite/paso3/{escuelaNivel}/plan-estudios', PlanEstudios::class)
        ->middleware("can:editarPaso,escuelaNivel,'plan_estudios'")
        ->name('tramite.paso3-plan-estudios');

    Route::get('/tramite/paso3/{escuelaNivel}/plantilla', PlantillaDocente::class)
        ->middleware("can:editarPaso,escuelaNivel,'plantilla_docente'")
        ->name('tramite.paso3-plantilla');

    Route::get('/tramite/paso3/{escuelaNivel}/matricula', Matricula::class)
        ->middleware("can:editarPaso,escuelaNivel,'matricula'")
        ->name('tramite.paso3-matricula');

    // Ya no es una página: el hub muestra el estado de cada nivel (rediseño UI, D12). Se conserva el nombre de ruta.
    Route::get('/tramite/paso3/{escuelaNivel}/proximos-pasos', fn (EscuelaNivel $escuelaNivel) => redirect()->route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id]))
        ->middleware('can:view,escuelaNivel')
        ->name('tramite.paso3-proximos-pasos');
});

// Breeze views still link to 'dashboard'; it only routes by role (WS-1.7).
Route::get('dashboard', fn () => redirect(RutaInicio::para(auth()->user())))
    ->middleware('auth')
    ->name('dashboard');

require __DIR__.'/auth.php';
