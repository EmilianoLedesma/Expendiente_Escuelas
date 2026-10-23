<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso de que SEDEQ devolvió el trámite con observaciones: cada paso rechazado con su motivo y el enlace al resumen. */
class ObservacionesEnTramite extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  list<array{nivel: ?string, titulo: string, motivo: string}>  $pasos */
    public function __construct(
        public readonly int $escuelaId,
        public readonly array $pasos,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu trámite tiene observaciones')
            ->markdown('correo.observaciones-tramite', [
                'nombre' => $notifiable->name,
                'pasos' => $this->pasos,
                'url' => route('tramite.resumen', ['escuela' => $this->escuelaId]),
            ]);
    }
}
