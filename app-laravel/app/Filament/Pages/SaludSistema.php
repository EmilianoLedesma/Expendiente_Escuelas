<?php

namespace App\Filament\Pages;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Salud\ConsultarSaludSistema;
use App\Application\Salud\OperarSistema;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

/** Estado de los servicios con botones de mantenimiento (el panel ya es solo para admin). */
class SaludSistema extends Page implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationLabel = 'Estado del sistema';

    protected static ?int $navigationSort = 99; // al final del menú

    protected static ?string $title = 'Estado del sistema';

    protected static ?string $slug = 'salud';

    protected string $view = 'filament.pages.salud-sistema';

    /** Botones que se pintan junto a cada chequeo. */
    public const ACCIONES = [
        'Cola de correos' => ['procesarCola', 'reintentarFallidos', 'descartarFallidos'],
        'Correo' => ['correoPrueba'],
    ];

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['chequeos' => app(ConsultarSaludSistema::class)->ejecutar(), 'acciones' => self::ACCIONES];
    }

    private function intentar(Closure $accion): void
    {
        try {
            $accion();
        } catch (PrecondicionIncumplida $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title('No se pudo completar la acción.')->body($e->getMessage())->send();
        }
    }

    public function procesarColaAction(): Action
    {
        return Action::make('procesarCola')->label('Procesar cola ahora')->color('gray')->size('sm')
            ->action(fn () => $this->intentar(function () {
                $n = app(OperarSistema::class)->procesarCola((int) auth()->id());
                Notification::make()->success()->title($n > 0 ? "Se procesaron {$n} trabajo(s)." : 'No había trabajos pendientes.')->send();
            }));
    }

    public function reintentarFallidosAction(): Action
    {
        return Action::make('reintentarFallidos')->label('Reintentar fallidos')->color('gray')->size('sm')
            ->action(fn () => $this->intentar(function () {
                $n = app(OperarSistema::class)->reintentarFallidos((int) auth()->id());
                Notification::make()->success()->title($n > 0 ? "Se volvieron a encolar {$n} trabajo(s)." : 'No hay trabajos fallidos.')->send();
            }));
    }

    public function descartarFallidosAction(): Action
    {
        return Action::make('descartarFallidos')->label('Descartar fallidos')->color('danger')->size('sm')
            ->requiresConfirmation()
            ->modalDescription('Se borran todos los trabajos fallidos y no se pueden recuperar. Si eran correos, no se enviarán.')
            ->action(fn () => $this->intentar(function () {
                $n = app(OperarSistema::class)->descartarFallidos((int) auth()->id());
                Notification::make()->success()->title($n > 0 ? "Se descartaron {$n} trabajo(s)." : 'No hay trabajos fallidos.')->send();
            }));
    }

    public function correoPruebaAction(): Action
    {
        return Action::make('correoPrueba')->label('Enviar correo de prueba')->color('gray')->size('sm')
            ->requiresConfirmation()
            ->modalDescription('Se envía un correo a tu propia dirección para comprobar que el envío funciona.')
            ->action(fn () => $this->intentar(function () {
                app(OperarSistema::class)->enviarCorreoDePrueba((int) auth()->id());
                Notification::make()->success()->title('Correo de prueba enviado a tu dirección.')->send();
            }));
    }
}
