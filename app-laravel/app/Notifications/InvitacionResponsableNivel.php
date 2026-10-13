<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al responsable: con $token (cuenta nueva) el enlace le deja definir su contraseña; sin él, solo entra. */
class InvitacionResponsableNivel extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $solicitante,
        public readonly string $nivel,
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
            ->subject('Te asignaron un nivel en el trámite de incorporación')
            ->markdown('correo.invitacion-nivel', [
                'nombre' => $notifiable->name,
                'solicitante' => $this->solicitante,
                'nivel' => $this->nivel,
                'conToken' => $this->token !== null,
                'textoBoton' => $this->token === null ? 'Entrar al sistema' : 'Definir contraseña',
                'url' => $this->token === null
                    ? route('login')
                    : route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]),
            ]);
    }
}
