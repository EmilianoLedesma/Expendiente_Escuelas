<?php

namespace Tests\Feature\Application;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesSedeqTest extends TestCase
{
    use RefreshDatabase;

    public function test_exigir_pasa_con_el_rol_y_lanza_sin_el(): void
    {
        Role::findOrCreate(RolesSedeq::SUPERVISOR, 'web');
        $sup = User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);
        $otro = User::factory()->create();

        RolesSedeq::exigir($sup, RolesSedeq::SUPERVISOR);

        $this->expectException(PrecondicionIncumplida::class);
        $this->expectExceptionMessage('No tienes permiso para esta acción.');
        RolesSedeq::exigir($otro, RolesSedeq::SUPERVISOR);
    }

    public function test_todos_contiene_exactamente_los_tres_nombres(): void
    {
        $this->assertEqualsCanonicalizing([RolesSedeq::ADMIN, 'supervisor', 'revisor'], RolesSedeq::TODOS);
        $this->assertSame('admin', RolesSedeq::ADMIN);
    }

    public function test_es_sedeq_operativo_solo_supervisor_o_revisor(): void
    {
        foreach (['admin', 'supervisor', 'revisor'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->assertTrue(RolesSedeq::esSedeqOperativo(User::factory()->create()->assignRole('supervisor')));
        $this->assertTrue(RolesSedeq::esSedeqOperativo(User::factory()->create()->assignRole('revisor')));
        $this->assertFalse(RolesSedeq::esSedeqOperativo(User::factory()->create()->assignRole('admin')));
        $this->assertFalse(RolesSedeq::esSedeqOperativo(User::factory()->create()));
    }
}
