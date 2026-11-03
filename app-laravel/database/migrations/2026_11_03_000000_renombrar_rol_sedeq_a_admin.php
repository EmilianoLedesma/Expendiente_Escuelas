<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin-fundación: el rol `sedeq` (administrador de /admin) pasa a llamarse `admin`.
 * Idempotente: sin rol `sedeq` no hace nada; con ambos, mueve titulares y borra `sedeq`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sedeq = DB::table('roles')->where('name', 'sedeq')->where('guard_name', 'web')->value('id');

        if ($sedeq !== null) {
            $admin = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');

            if ($admin === null) {
                DB::unprepared("UPDATE roles SET name = 'admin' WHERE id = {$sedeq}");
            } else {
                DB::unprepared("
                    UPDATE model_has_roles m SET role_id = {$admin}
                    WHERE role_id = {$sedeq}
                      AND NOT EXISTS (
                        SELECT 1 FROM model_has_roles x
                        WHERE x.role_id = {$admin} AND x.model_id = m.model_id AND x.model_type = m.model_type
                      )
                ");
                DB::unprepared("DELETE FROM model_has_roles WHERE role_id = {$sedeq}");
                DB::unprepared("DELETE FROM role_has_permissions WHERE role_id = {$sedeq}");
                DB::unprepared("DELETE FROM roles WHERE id = {$sedeq}");
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::unprepared("UPDATE roles SET name = 'sedeq' WHERE name = 'admin' AND guard_name = 'web'");
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
