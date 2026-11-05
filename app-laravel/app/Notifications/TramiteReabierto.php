<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso de que el trámite se reabrió, con enlace al resumen. Sin quién ni por qué. */
class TramiteReabierto extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $escuelaId, public readonly ?string $nombreEscuela = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu trámite fue reabierto')
            ->markdown('correo.tramite-reabierto', [
                'nombre' => $notifiable->name,
                'escuela' => $this->nombreEscuela,
                'url' => route('tramite.resumen', ['escuela' => $this->escuelaId]),
            ]);
    }
}
