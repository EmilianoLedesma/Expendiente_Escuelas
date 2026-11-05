<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso de que el trámite quedó cerrado. Solo el estado: sin quién ni por qué, y sin enlace (el solicitante ya no lo ve). */
class TramiteCerrado extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $nombreEscuela = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu trámite está cerrado')
            ->markdown('correo.tramite-cerrado', ['nombre' => $notifiable->name, 'escuela' => $this->nombreEscuela]);
    }
}
