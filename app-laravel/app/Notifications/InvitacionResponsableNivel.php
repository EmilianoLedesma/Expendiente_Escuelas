<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al responsable: con $token (cuenta nueva) el enlace le deja definir su contraseña; sin él, solo entra. */
class InvitacionResponsableNivel extends Notification
{
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
        $mensaje = (new MailMessage)
            ->subject('Te asignaron un nivel en el trámite de incorporación')
            ->line("{$this->solicitante} te asignó como responsable del nivel {$this->nivel}.");

        return $this->token === null
            ? $mensaje->action('Entrar al sistema', route('login'))
            : $mensaje
                ->line('Define tu contraseña para entrar y capturar la información de ese nivel.')
                ->action('Definir contraseña', route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]));
    }
}
