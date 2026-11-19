<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al dueño de que se programó la visita de inspección de un nivel, con enlace a la página de la visita. */
class VisitaProgramadaAviso extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $escuelaId,
        public readonly ?string $nombreEscuela,
        public readonly string $nivel,
        public readonly \DateTimeInterface $fecha,
        public readonly string $domicilio,
        public readonly int $escuelaNivelId,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Se programó la visita de inspección de tu trámite')
            ->markdown('correo.visita-programada', [
                'nombre' => $notifiable->name,
                'escuela' => $this->nombreEscuela,
                'nivel' => $this->nivel,
                'fecha' => $this->fecha->format('d/m/Y H:i'),
                'domicilio' => $this->domicilio,
                'url' => $this->url(),
            ]);
    }

    private function url(): string
    {
        return route('tramite.visita', ['escuelaNivel' => $this->escuelaNivelId]);
    }
}
