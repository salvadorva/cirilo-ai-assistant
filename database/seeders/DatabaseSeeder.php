<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Primero ejecutamos el seeder de roles
        $this->call(RolesTableSeeder::class);

        // Después ejecutamos el seeder de usuarios
        $this->call(UsersTableSeeder::class);

        // Seeder de lecciones de mecanografía
        $this->call(TypingLessonsSeeder::class);

        // Si necesitas crear usuarios de prueba adicionales, puedes descomentarlo
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
