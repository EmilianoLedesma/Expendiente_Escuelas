<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\InvitacionResponsableNivel;
use App\Notifications\RestablecerContrasena;
use App\Notifications\VerificarCorreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorreoDisenoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: callable(): object}> */
    public static function avisos(): array
    {
        return [
            'restablecer' => [fn () => new RestablecerContrasena('tok')],
            'verificar' => [fn () => new VerificarCorreo],
            'invitacion con enlace' => [fn () => new InvitacionResponsableNivel('Ana', 'Primaria', 'tok')],
            'invitacion sin enlace' => [fn () => new InvitacionResponsableNivel('Ana', 'Primaria', null)],
        ];
    }

    #[DataProvider('avisos')]
    public function test_el_correo_usa_la_marca_de_sedeq_y_ninguna_frase_por_defecto_de_laravel(callable $crear): void
    {
        $usuario = User::factory()->create(['name' => 'María Pérez']);

        $html = (string) $crear()->toMail($usuario)->render();

        $this->assertStringContainsString('Hola, María Pérez', $html);
        $this->assertStringContainsString('Saludos', $html);
        $this->assertStringContainsString(config('sedeq.institucion'), $html);
        $this->assertStringContainsString('#242B57', strtoupper($html));
        $this->assertStringNotContainsString('Laravel', $html);
        foreach (['Hello', 'Regards', 'trouble clicking', 'All rights reserved'] as $ingles) {
            $this->assertStringNotContainsString($ingles, $html);
        }
    }

    public function test_el_enlace_alterno_va_en_espanol(): void
    {
        $usuario = User::factory()->create();

        $html = (string) (new RestablecerContrasena('tok'))->toMail($usuario)->render();

        $this->assertStringContainsString('Si tienes problemas con el botón', $html);
        $this->assertStringContainsString('copia y pega esta dirección', $html);
    }
}
