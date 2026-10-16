<?php

namespace Database\Seeders;

use App\Application\Revision\RolesSedeq;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'sedeq', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'responsable_nivel', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RolesSedeq::SUPERVISOR, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RolesSedeq::REVISOR, 'guard_name' => 'web']);
    }
}
