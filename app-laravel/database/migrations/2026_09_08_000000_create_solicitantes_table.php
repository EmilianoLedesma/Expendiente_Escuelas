<?php

use App\Models\Solicitante;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE solicitantes (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT NOT NULL UNIQUE REFERENCES users(id),
    created_at  TIMESTAMP NOT NULL DEFAULT now(),
    updated_at  TIMESTAMP NOT NULL DEFAULT now()
);');

        Solicitante::backfillDesdeUsers();
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS solicitantes CASCADE');
    }
};
