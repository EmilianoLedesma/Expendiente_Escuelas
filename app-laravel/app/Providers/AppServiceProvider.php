<?php

namespace App\Providers;

use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Infrastructure\Documentos\AlmacenDocumentosLocal;
use App\Listeners\CrearSolicitanteAlRegistrarUsuario;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            AlmacenDocumentos::class,
            AlmacenDocumentosLocal::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Registered::class, CrearSolicitanteAlRegistrarUsuario::class);

        // Lets `<x-layouts.tramite>` resolve resources/views/layouts/tramite.blade.php —
        // the same physical file Livewire's #[Layout('layouts.tramite')] resolves as a
        // plain view for full-page components. One file, two call sites.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        // Livewire re-applies only its known persistent middleware on POST /livewire/update;
        // `verified` is not among them, so without this an unverified user could replay a snapshot.
        Livewire::addPersistentMiddleware([EnsureEmailIsVerified::class]);
    }
}
