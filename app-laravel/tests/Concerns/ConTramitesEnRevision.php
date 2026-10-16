<?php

namespace Tests\Concerns;

use App\Application\Revision\RolesSedeq;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;

/** Fixture del HUD SEDEQ: supervisor, revisores y niveles ya enviados (en_revision). */
trait ConTramitesEnRevision
{
    use ConNivelParaResponsables;

    protected function supervisor(): User
    {
        (new RolesSeeder)->run();

        return User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);
    }

    protected function revisor(): User
    {
        (new RolesSeeder)->run();

        return User::factory()->create()->assignRole(RolesSedeq::REVISOR);
    }

    /** Cada nivel en su propia escuela: (escuela_id, nivel_educativo_id) es único. */
    protected function nivelEnRevision(string $estado = 'en_revision'): EscuelaNivel
    {
        return $this->nivelDe(Solicitante::factory()->create(), 'primaria', $estado);
    }
}
