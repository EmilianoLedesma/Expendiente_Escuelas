<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al revisor: con $token (cuenta nueva) el enlace le deja definir su contraseña; sin él, solo entra. */
class InvitacionRevisor extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $supervisor,
        public readonly ?string $token,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Te dieron acceso como revisor en el sistema de incorporación')
            ->markdown('correo.invitacion-revisor', [
                'nombre' => $notifiable->name,
                'supervisor' => $this->supervisor,
                'conToken' => $this->token !== null,
                'textoBoton' => $this->token === null ? 'Entrar al sistema' : 'Definir contraseña',
                'url' => $this->token === null
                    ? route('login')
                    : route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]),
            ]);
    }
}
