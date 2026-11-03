<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Todo el proyecto usa hora de Ciudad de México: PHP, la sesión de Postgres y las filas escritas por cualquiera de los dos. */
class ZonaHorariaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_app_y_la_sesion_de_postgres_usan_ciudad_de_mexico(): void
    {
        $this->assertSame('America/Mexico_City', config('app.timezone'));
        $this->assertSame('America/Mexico_City', DB::selectOne('show timezone')->TimeZone);
    }

    public function test_laravel_y_los_default_now_del_esquema_escriben_el_mismo_reloj(): void
    {
        $porLaravel = Carbon::parse(now()->toDateTimeString());
        $porPostgres = Carbon::parse(DB::selectOne('select now()::timestamp as t')->t);

        $this->assertLessThan(5, abs($porLaravel->diffInSeconds($porPostgres)));
    }
}
