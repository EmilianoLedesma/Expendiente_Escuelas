<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al dueño del resultado de la visita: cumple, o con observaciones y plazo aproximado para subsanar. */
class ResultadoDeVisita extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $escuelaId,
        public readonly ?string $nombreEscuela,
        public readonly string $nivel,
        public readonly string $resultado,
        public readonly int $totalObservaciones,
        public readonly ?\DateTimeInterface $subsanarHasta,
        public readonly int $escuelaNivelId,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cumple = $this->resultado === 'cumple';

        return (new MailMessage)
            ->subject($cumple ? 'La visita de inspección de tu trámite no tuvo observaciones' : 'La visita de inspección de tu trámite dejó observaciones')
            ->markdown('correo.resultado-de-visita', [
                'nombre' => $notifiable->name,
                'escuela' => $this->nombreEscuela,
                'nivel' => $this->nivel,
                'cumple' => $cumple,
                'total' => $this->totalObservaciones,
                'plazo' => $this->subsanarHasta?->format('d/m/Y'),
                'url' => $this->url(),
            ]);
    }

    private function url(): string
    {
        return route('tramite.visita', ['escuelaNivel' => $this->escuelaNivelId]);
    }
}
