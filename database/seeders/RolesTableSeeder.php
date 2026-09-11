<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Desactivar temporalmente las restricciones de clave foránea
        Schema::disableForeignKeyConstraints();

        // Limpiar la tabla de roles antes de insertar nuevos registros
        DB::table('roles')->truncate();

        // Insertar roles
        DB::table('roles')->insert([
            ['name' => 'admin'],
            ['name' => 'user'],
        ]);

        // Reactivar las restricciones de clave foránea
        Schema::enableForeignKeyConstraints();
    }
}
