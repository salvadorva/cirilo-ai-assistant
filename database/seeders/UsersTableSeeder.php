<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UsersTableSeeder extends Seeder
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

        // Limpiar la tabla de usuarios antes de insertar nuevos registros
        DB::table('users')->truncate();

        // Obtener el id del rol admin
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        DB::table('users')->insert([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'change-me-on-first-login')),
            'role_id' => $adminRole ? $adminRole->id : null,
            'ai_provider' => 'openai', // Valor predeterminado
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Obtener el id del rol user
        $userRole = DB::table('roles')->where('name', 'user')->first();

        // Usuario de demostración A
        DB::table('users')->insert([
            'name' => 'Demo Kid A',
            'email' => 'demo-a@example.com',
            'password' => Hash::make(env('SEED_USER_PASSWORD', 'change-me-on-first-login')),
            'role_id' => $userRole ? $userRole->id : null,
            'ai_provider' => 'openai', // Puede cambiarse a 'grok' si se prefiere
            'prompt' => 'Eres un asistente virtual llamado Cirilo. Acompañas a un usuario infantil: usa un tono amistoso y claro, respuestas cortas y contenido apropiado para su edad. Personaliza este prompt según el perfil real del usuario.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Usuario de demostración B (también con rol user)
        DB::table('users')->insert([
            'name' => 'Demo Kid B',
            'email' => 'demo-b@example.com',
            'password' => Hash::make(env('SEED_USER_PASSWORD', 'change-me-on-first-login')),
            'role_id' => $userRole ? $userRole->id : null,
            'ai_provider' => 'openai', // Puede cambiarse a 'grok' si se prefiere
            'prompt' => 'Eres un asistente virtual llamado Cirilo. Acompañas a un usuario infantil: usa un tono amistoso y claro, respuestas cortas y contenido apropiado para su edad. Personaliza este prompt según el perfil real del usuario.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reactivar las restricciones de clave foránea
        Schema::enableForeignKeyConstraints();
    }
}
