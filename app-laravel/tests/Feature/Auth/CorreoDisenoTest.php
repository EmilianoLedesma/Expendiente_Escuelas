<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\InvitacionResponsableNivel;
use App\Notifications\InvitacionRevisor;
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
            'invitacion revisor con enlace' => [fn () => new InvitacionRevisor('Ana', 'tok')],
            'invitacion revisor sin enlace' => [fn () => new InvitacionRevisor('Ana', null)],
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

    public function test_el_correo_lleva_los_logos_institucionales_con_texto_alternativo(): void
    {
        $usuario = User::factory()->create();

        $html = (string) (new RestablecerContrasena('tok'))->toMail($usuario)->render();

        $this->assertStringContainsString(asset('img/layout_set_logo.png'), $html);
        $this->assertStringContainsString(asset('img/formato-logo.png'), $html);
        $this->assertStringContainsString('alt="Gobierno del Estado de Querétaro"', $html);
        $this->assertStringContainsString('alt="Secretaría de Educación"', $html);
    }

    public function test_la_invitacion_destaca_quien_asigno_y_el_nivel(): void
    {
        $usuario = User::factory()->create();

        foreach (['tok', null] as $token) {
            $html = (string) (new InvitacionResponsableNivel('Ana Gómez', 'Primaria', $token))->toMail($usuario)->render();

            $this->assertStringContainsString('class="panel"', $html);
            $this->assertMatchesRegularExpression('/Asignado por.*Ana Gómez/s', $html);
            $this->assertMatchesRegularExpression('/Nivel educativo.*Primaria/s', $html);
            $this->assertStringContainsString('Si tienes problemas con el botón', $html);
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
