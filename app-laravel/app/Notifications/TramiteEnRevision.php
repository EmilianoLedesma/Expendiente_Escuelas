<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso de que el trámite se envió y entró a revisión, con enlace al resumen. */
class TramiteEnRevision extends Notification implements ShouldQueue
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
            ->subject('Tu trámite fue enviado a revisión')
            ->markdown('correo.tramite-en-revision', [
                'nombre' => $notifiable->name,
                'escuela' => $this->nombreEscuela,
                'url' => route('tramite.resumen', ['escuela' => $this->escuelaId]),
            ]);
    }
}
