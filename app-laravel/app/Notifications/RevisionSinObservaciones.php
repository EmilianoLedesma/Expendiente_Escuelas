<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso de que la revisión terminó sin observaciones, con enlace al resumen. */
class RevisionSinObservaciones extends Notification implements ShouldQueue
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
            ->subject('La revisión de tu trámite terminó sin observaciones')
            ->markdown('correo.revision-sin-observaciones', [
                'nombre' => $notifiable->name,
                'escuela' => $this->nombreEscuela,
                'url' => route('tramite.resumen', ['escuela' => $this->escuelaId]),
            ]);
    }
}
