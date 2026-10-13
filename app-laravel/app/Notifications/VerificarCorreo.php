<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/** Enlace para verificar el correo, en español y por cola. */
class VerificarCorreo extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $minutos = config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Verifica tu correo electrónico')
            ->greeting("Hola, {$notifiable->name}")
            ->line('Confirma tu correo para continuar con el trámite de incorporación.')
            ->action('Verificar correo', $this->verificationUrl($notifiable))
            ->line("Este enlace caduca en {$minutos} minutos.")
            ->line('Si no creaste esta cuenta, ignora este correo.');
    }
}
