<?php

namespace Tests\Feature\Admin;

use App\Application\Revision\RolesSedeq;
use App\Filament\Resources\Planteles\Pages\ListPlanteles;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlantelesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        $this->admin = User::factory()->create()->assignRole(RolesSedeq::ADMIN);
        $this->actingAs($this->admin);
    }

    private function plantel(): Plantel
    {
        $p = Plantel::create(['calle' => 'Hidalgo', 'numero_ext' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $s = Solicitante::factory()->create(['user_id' => User::factory()->create()->id]);
        Escuela::create(['plantel_id' => $p->id, 'solicitante_id' => $s->id]);

        return $p;
    }

    public function test_lista_planteles_con_conteo(): void
    {
        $p = $this->plantel();

        Livewire::test(ListPlanteles::class)->assertCanSeeTableRecords([$p])
            ->assertSee('Hidalgo 12, Centro')->assertSee('1 de 1')->assertSee('Activo');
    }

    public function test_archivar_y_restaurar_con_confirmacion(): void
    {
        $p = $this->plantel();

        $t = Livewire::test(ListPlanteles::class)
            ->assertActionVisible(TestAction::make('archivar')->table($p))
            ->assertActionHidden(TestAction::make('restaurar')->table($p))
            ->callAction(TestAction::make('archivar')->table($p), ['motivo' => null]);
        $this->assertSame(0, Escuela::where('plantel_id', $p->id)->count());
        $this->assertSame(1, Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->where('plantel_id', $p->id)->whereNotNull('archivado_at')->count());

        $t->assertActionHidden(TestAction::make('archivar')->table($p))
            ->assertActionVisible(TestAction::make('restaurar')->table($p))
            ->assertSee('Archivado')
            ->callAction(TestAction::make('restaurar')->table($p));
        $this->assertSame(1, Escuela::where('plantel_id', $p->id)->count());
    }
}
