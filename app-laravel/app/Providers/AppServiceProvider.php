<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lets `<x-layouts.tramite>` resolve resources/views/layouts/tramite.blade.php —
        // the same physical file Livewire's #[Layout('layouts.tramite')] resolves as a
        // plain view for full-page components. One file, two call sites.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');
    }
}
