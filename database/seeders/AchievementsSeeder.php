<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $achievements = [
            // Logros Generales
            [
                'key' => 'first_login',
                'name' => 'Bienvenido',
                'description' => 'Has iniciado sesión por primera vez',
                'icon' => 'fas fa-user-plus',
                'category' => 'general',
                'rarity' => 'common',
                'xp_reward' => 10,
                'conditions' => [
                    ['field' => 'login_count', 'operator' => '>=', 'value' => 1],
                ],
                'badge_color' => '#28a745',
                'is_secret' => false,
                'order' => 1,
            ],
            [
                'key' => 'daily_user',
                'name' => 'Usuario Diario',
                'description' => 'Has usado la aplicación 7 días consecutivos',
                'icon' => 'fas fa-calendar-check',
                'category' => 'general',
                'rarity' => 'rare',
                'xp_reward' => 50,
                'conditions' => [
                    ['field' => 'current_streak', 'operator' => '>=', 'value' => 7],
                ],
                'badge_color' => '#007bff',
                'is_secret' => false,
                'order' => 2,
            ],
            [
                'key' => 'dedicated_user',
                'name' => 'Usuario Dedicado',
                'description' => 'Has usado la aplicación 30 días consecutivos',
                'icon' => 'fas fa-medal',
                'category' => 'general',
                'rarity' => 'epic',
                'xp_reward' => 200,
                'conditions' => [
                    ['field' => 'current_streak', 'operator' => '>=', 'value' => 30],
                ],
                'badge_color' => '#6f42c1',
                'is_secret' => false,
                'order' => 3,
            ],

            // Logros del Tutor
            [
                'key' => 'first_evaluation',
                'name' => 'Evaluado',
                'description' => 'Has completado tu primera evaluación de inglés',
                'icon' => 'fas fa-clipboard-check',
                'category' => 'tutor',
                'rarity' => 'common',
                'xp_reward' => 25,
                'conditions' => [
                    ['field' => 'evaluations_completed', 'operator' => '>=', 'value' => 1],
                ],
                'badge_color' => '#17a2b8',
                'is_secret' => false,
                'order' => 10,
            ],
            [
                'key' => 'vocabulary_master',
                'name' => 'Maestro del Vocabulario',
                'description' => 'Has obtenido 100% en un ejercicio de vocabulario',
                'icon' => 'fas fa-book',
                'category' => 'tutor',
                'rarity' => 'rare',
                'xp_reward' => 75,
                'conditions' => [
                    ['field' => 'vocabulary_perfect_score', 'operator' => '>=', 'value' => 1],
                ],
                'badge_color' => '#fd7e14',
                'is_secret' => false,
                'order' => 11,
            ],
            [
                'key' => 'grammar_expert',
                'name' => 'Experto en Gramática',
                'description' => 'Has obtenido 95% o más en 5 ejercicios de gramática',
                'icon' => 'fas fa-language',
                'category' => 'tutor',
                'rarity' => 'epic',
                'xp_reward' => 100,
                'conditions' => [
                    ['field' => 'grammar_high_scores', 'operator' => '>=', 'value' => 5],
                ],
                'badge_color' => '#20c997',
                'is_secret' => false,
                'order' => 12,
            ],
            [
                'key' => 'speaking_star',
                'name' => 'Estrella del Speaking',
                'description' => 'Has obtenido 90% o más en 10 ejercicios de speaking',
                'icon' => 'fas fa-microphone',
                'category' => 'tutor',
                'rarity' => 'epic',
                'xp_reward' => 150,
                'conditions' => [
                    ['field' => 'speaking_high_scores', 'operator' => '>=', 'value' => 10],
                ],
                'badge_color' => '#e83e8c',
                'is_secret' => false,
                'order' => 13,
            ],
            [
                'key' => 'listening_pro',
                'name' => 'Pro del Listening',
                'description' => 'Has completado 50 ejercicios de listening',
                'icon' => 'fas fa-headphones',
                'category' => 'tutor',
                'rarity' => 'rare',
                'xp_reward' => 80,
                'conditions' => [
                    ['field' => 'listening_exercises_completed', 'operator' => '>=', 'value' => 50],
                ],
                'badge_color' => '#6610f2',
                'is_secret' => false,
                'order' => 14,
            ],

            // Logros de Typing (para el futuro juego)
            [
                'key' => 'first_typing_session',
                'name' => 'Primeros Pasos',
                'description' => 'Has completado tu primera sesión de mecanografía',
                'icon' => 'fas fa-keyboard',
                'category' => 'typing',
                'rarity' => 'common',
                'xp_reward' => 15,
                'conditions' => [
                    ['field' => 'typing_sessions_completed', 'operator' => '>=', 'value' => 1],
                ],
                'badge_color' => '#495057',
                'is_secret' => false,
                'order' => 20,
            ],
            [
                'key' => 'speed_demon',
                'name' => 'Demonio de la Velocidad',
                'description' => 'Has alcanzado 60 WPM en mecanografía',
                'icon' => 'fas fa-bolt',
                'category' => 'typing',
                'rarity' => 'rare',
                'xp_reward' => 100,
                'conditions' => [
                    ['field' => 'best_wpm', 'operator' => '>=', 'value' => 60],
                ],
                'badge_color' => '#ffc107',
                'is_secret' => false,
                'order' => 21,
            ],
            [
                'key' => 'accuracy_perfectionist',
                'name' => 'Perfeccionista',
                'description' => 'Has mantenido 99% de precisión en 10 sesiones',
                'icon' => 'fas fa-bullseye',
                'category' => 'typing',
                'rarity' => 'epic',
                'xp_reward' => 125,
                'conditions' => [
                    ['field' => 'perfect_accuracy_sessions', 'operator' => '>=', 'value' => 10],
                ],
                'badge_color' => '#dc3545',
                'is_secret' => false,
                'order' => 22,
            ],

            // Logros Secretos
            [
                'key' => 'night_owl',
                'name' => 'Búho Nocturno',
                'description' => 'Has practicado después de medianoche 5 veces',
                'icon' => 'fas fa-moon',
                'category' => 'general',
                'rarity' => 'rare',
                'xp_reward' => 60,
                'conditions' => [
                    ['field' => 'late_night_sessions', 'operator' => '>=', 'value' => 5],
                ],
                'badge_color' => '#343a40',
                'is_secret' => true,
                'order' => 100,
            ],
            [
                'key' => 'early_bird',
                'name' => 'Madrugador',
                'description' => 'Has practicado antes de las 6 AM 5 veces',
                'icon' => 'fas fa-sun',
                'category' => 'general',
                'rarity' => 'rare',
                'xp_reward' => 60,
                'conditions' => [
                    ['field' => 'early_morning_sessions', 'operator' => '>=', 'value' => 5],
                ],
                'badge_color' => '#fd7e14',
                'is_secret' => true,
                'order' => 101,
            ],
            [
                'key' => 'level_ten',
                'name' => 'Nivel Diez',
                'description' => 'Has alcanzado el nivel 10',
                'icon' => 'fas fa-crown',
                'category' => 'general',
                'rarity' => 'legendary',
                'xp_reward' => 500,
                'conditions' => [
                    ['field' => 'level', 'operator' => '>=', 'value' => 10],
                ],
                'badge_color' => '#ffd700',
                'is_secret' => false,
                'order' => 5,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::create($achievement);
        }
    }
}
