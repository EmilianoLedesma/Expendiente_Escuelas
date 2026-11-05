<?php

namespace Tests\Feature\Application\Salud;

use App\Application\Salud\ConsultarSaludSistema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConsultarSaludSistemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        config(['queue.default' => 'database', 'mail.default' => 'smtp']);
    }

    /** @return array<string, string> nombre => estado */
    private function estados(): array
    {
        return array_column(app(ConsultarSaludSistema::class)->ejecutar(), 'estado', 'nombre');
    }

    private function trabajo(int $hace): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
            'available_at' => now()->subSeconds($hace)->timestamp, 'created_at' => now()->subSeconds($hace)->timestamp,
        ]);
    }

    public function test_todo_en_orden_da_ok_en_los_cinco(): void
    {
        $this->assertSame([
            'Base de datos' => 'ok', 'Cola de correos' => 'ok', 'Correo' => 'ok',
            'Disco de documentos' => 'ok', 'Migraciones' => 'ok',
        ], $this->estados());
    }

    public function test_un_trabajo_fallido_es_error_en_la_cola(): void
    {
        DB::table('failed_jobs')->insert(['uuid' => 'u-1', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x']);

        $this->assertSame('error', $this->estados()['Cola de correos']);
    }

    public function test_un_trabajo_esperando_mucho_es_aviso_y_uno_reciente_es_ok(): void
    {
        $this->trabajo(30);
        $this->assertSame('ok', $this->estados()['Cola de correos']);

        $this->trabajo(1800);
        $this->assertSame('aviso', $this->estados()['Cola de correos']);
    }

    public function test_con_cola_sync_o_correo_log_avisa(): void
    {
        config(['queue.default' => 'sync', 'mail.default' => 'log']);

        $estados = $this->estados();
        $this->assertSame('aviso', $estados['Cola de correos']);
        $this->assertSame('aviso', $estados['Correo']);
    }
}
