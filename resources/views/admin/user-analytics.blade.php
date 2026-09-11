@extends('layout.app')

@section('title', 'Analytics - ' . $user->name)

@section('content')
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 2rem;">
    <div style="max-width: 1600px; margin: 0 auto;">
        
        <!-- Header -->
        <div style="background: white; border-radius: 24px; padding: 2.5rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 6px; background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 2rem; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 2rem; flex: 1;">
                    <div style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 2.5rem; font-weight: 700; box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div style="flex: 1;">
                        <h1 style="font-size: 2rem; font-weight: 800; color: #1a202c; margin: 0 0 0.5rem 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            {{ $user->name }}
                        </h1>
                        <p style="color: #718096; font-size: 1rem; margin: 0 0 1rem 0;">{{ $user->email }}</p>
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            @if($progress)
                                <span style="padding: 0.625rem 1.25rem; border-radius: 50px; font-size: 0.875rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <span>⭐</span> Nivel {{ $progress->level }}
                                </span>
                                <span style="padding: 0.625rem 1.25rem; border-radius: 50px; font-size: 0.875rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); color: white;">
                                    <span>✨</span> {{ number_format($progress->total_xp) }} XP
                                </span>
                                <span style="padding: 0.625rem 1.25rem; border-radius: 50px; font-size: 0.875rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                                    <span>🔥</span> {{ $progress->current_streak }} días
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.dashboard') }}" style="padding: 1rem 2rem; background: white; color: #667eea; border: 2px solid #667eea; border-radius: 50px; cursor: pointer; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);">
                    <span>←</span> Volver
                </a>
            </div>
        </div>

        <!-- TypeMaster Section -->
        <div style="background: white; border-radius: 24px; padding: 2.5rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 3px solid #f7fafc;">
                <div style="width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">⌨️</div>
                <div>
                    <h2 style="font-size: 1.75rem; font-weight: 800; color: #1a202c; margin: 0;">TypeMaster Analytics</h2>
                    <p style="color: #718096; font-size: 0.875rem; margin: 0.25rem 0 0 0;">Rendimiento en mecanografía</p>
                </div>
            </div>

            @if($typingStats['total_sessions'] > 0)
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
                    <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">📊</div>
                        <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $typingStats['total_sessions'] }}</p>
                        <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Sesiones Totales</p>
                    </div>
                    <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">⚡</div>
                        <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $typingStats['avg_wpm'] }}</p>
                        <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">WPM Promedio</p>
                    </div>
                    <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">🏆</div>
                        <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $typingStats['best_wpm'] }}</p>
                        <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Mejor WPM</p>
                    </div>
                    <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">🎯</div>
                        <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $typingStats['avg_accuracy'] }}%</p>
                        <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Precisión Promedio</p>
                    </div>
                    <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">⏱️</div>
                        <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $typingStats['total_time'] }}</p>
                        <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Minutos Practicados</p>
                    </div>
                </div>

                <!-- Gráficos -->
                <div style="background: white; border-radius: 20px; padding: 2rem; margin-bottom: 2rem; border: 2px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #2d3748; margin: 0;">📈 Evolución de Velocidad (WPM)</h3>
                        <span style="padding: 0.375rem 0.875rem; background: #edf2f7; color: #4a5568; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">Últimas sesiones</span>
                    </div>
                    <div style="position: relative; height: 350px;">
                        <canvas id="wpmChart"></canvas>
                    </div>
                </div>

                <div style="background: white; border-radius: 20px; padding: 2rem; margin-bottom: 2rem; border: 2px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #2d3748; margin: 0;">🎯 Evolución de Precisión</h3>
                        <span style="padding: 0.375rem 0.875rem; background: #edf2f7; color: #4a5568; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">Últimas sesiones</span>
                    </div>
                    <div style="position: relative; height: 350px;">
                        <canvas id="accuracyChart"></canvas>
                    </div>
                </div>

                <!-- Tablas y resto del contenido seguirían aquí... -->

            @else
                <div style="text-align: center; padding: 4rem 2rem; background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border-radius: 20px; border: 2px dashed #cbd5e0;">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">⌨️</div>
                    <h3 style="font-size: 1.5rem; font-weight: 700; color: #4a5568; margin: 0 0 0.5rem 0;">Sin Datos de TypeMaster</h3>
                    <p style="color: #a0aec0; font-size: 1rem;">Este usuario aún no ha practicado mecanografía</p>
                </div>
            @endif
        </div>

        <!-- English Games Section -->
        <div style="background: white; border-radius: 24px; padding: 2.5rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 3px solid #f7fafc;">
                <div style="width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">🎮</div>
                <div>
                    <h2 style="font-size: 1.75rem; font-weight: 800; color: #1a202c; margin: 0;">English Games Analytics</h2>
                    <p style="color: #718096; font-size: 0.875rem; margin: 0.25rem 0 0 0;">Rendimiento en juegos de inglés</p>
                </div>
            </div>

            @if($englishStats['total_sessions'] > 0)
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
                    <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">🎮</div>
                        <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $englishStats['total_sessions'] }}</p>
                        <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Sesiones Totales</p>
                    </div>
                    <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">🏅</div>
                        <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $englishStats['avg_score'] }}</p>
                        <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Score Promedio</p>
                    </div>
                    <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">🎯</div>
                        <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $englishStats['avg_accuracy'] }}%</p>
                        <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Precisión Promedio</p>
                    </div>
                    <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">⏱️</div>
                        <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $englishStats['total_time'] }}</p>
                        <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Minutos Jugados</p>
                    </div>
                </div>

                <div style="background: white; border-radius: 20px; padding: 2rem; margin-bottom: 2rem; border: 2px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #2d3748; margin: 0;">📈 Evolución de Scores</h3>
                        <span style="padding: 0.375rem 0.875rem; background: #edf2f7; color: #4a5568; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">Progreso en el tiempo</span>
                    </div>
                    <div style="position: relative; height: 350px;">
                        <canvas id="englishScoresChart"></canvas>
                    </div>
                </div>

                @if(count($englishStats['by_game_type']) > 0)
                <div style="background: white; border-radius: 20px; overflow: hidden; margin-bottom: 2rem; border: 2px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 1.5rem 2rem;">
                        <h3 style="color: white; margin: 0; font-size: 1.125rem; font-weight: 700;">🎲 Rendimiento por Tipo de Juego</h3>
                    </div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="background: #f7fafc; padding: 1.25rem 1.5rem; text-align: left; font-weight: 700; color: #4a5568; font-size: 0.813rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0;">Juego</th>
                                <th style="background: #f7fafc; padding: 1.25rem 1.5rem; text-align: left; font-weight: 700; color: #4a5568; font-size: 0.813rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0;">Sesiones</th>
                                <th style="background: #f7fafc; padding: 1.25rem 1.5rem; text-align: left; font-weight: 700; color: #4a5568; font-size: 0.813rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0;">Score Promedio</th>
                                <th style="background: #f7fafc; padding: 1.25rem 1.5rem; text-align: left; font-weight: 700; color: #4a5568; font-size: 0.813rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0;">Mejor Score</th>
                                <th style="background: #f7fafc; padding: 1.25rem 1.5rem; text-align: left; font-weight: 700; color: #4a5568; font-size: 0.813rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0;">Precisión</th>
                                <th style="background: #f7fafc; padding: 1.25rem 1.5rem; text-align: left; font-weight: 700; color: #4a5568; font-size: 0.813rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0;">Correctas / Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($englishStats['by_game_type'] as $game)
                                <tr>
                                    <td style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; color: #f5576c; font-weight: 700; text-transform: capitalize;">{{ ucwords(str_replace('_', ' ', $game->game_type)) }}</td>
                                    <td style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;"><strong>{{ $game->sessions }}</strong></td>
                                    <td style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;">{{ round($game->avg_score, 1) }}</td>
                                    <td style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;"><strong>{{ $game->best_score }}</strong></td>
                                    <td style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;">{{ round($game->avg_accuracy, 1) }}%</td>
                                    <td style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;">{{ $game->total_correct }} / {{ $game->total_questions }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

            @else
                <div style="text-align: center; padding: 4rem 2rem; background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border-radius: 20px; border: 2px dashed #cbd5e0;">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">🎮</div>
                    <h3 style="font-size: 1.5rem; font-weight: 700; color: #4a5568; margin: 0 0 0.5rem 0;">Sin Datos de Juegos de Inglés</h3>
                    <p style="color: #a0aec0; font-size: 1rem;">Este usuario aún no ha jugado juegos de inglés</p>
                </div>
            @endif
        </div>

        <!-- Engagement Section -->
        <div style="background: white; border-radius: 24px; padding: 2.5rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 3px solid #f7fafc;">
                <div style="width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">📈</div>
                <div>
                    <h2 style="font-size: 1.75rem; font-weight: 800; color: #1a202c; margin: 0;">Engagement Analytics</h2>
                    <p style="color: #718096; font-size: 0.875rem; margin: 0.25rem 0 0 0;">Compromiso y actividad del usuario</p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
                <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">🔥</div>
                    <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $engagementStats['current_streak'] }}</p>
                    <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Racha Actual</p>
                </div>
                <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">🏆</div>
                    <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $engagementStats['longest_streak'] }}</p>
                    <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Racha Más Larga</p>
                </div>
                <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">📅</div>
                    <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $engagementStats['total_login_days'] }}</p>
                    <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Días Activo Total</p>
                </div>
                <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">💯</div>
                    <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $engagementStats['engagement_score'] }}</p>
                    <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Score Engagement</p>
                </div>
                <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 20px; padding: 2rem; text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.8;">📊</div>
                    <p style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0.5rem 0; line-height: 1;">{{ $engagementStats['sessions_this_week'] }}</p>
                    <p style="font-size: 0.875rem; color: #718096; margin: 0.75rem 0 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Sesiones Esta Semana</p>
                </div>
            </div>

            @if(count($engagementStats['activity_by_day']) > 0)
            <div style="background: white; border-radius: 20px; padding: 2rem; border: 2px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #2d3748; margin: 0;">📅 Actividad por Día de la Semana</h3>
                    <span style="padding: 0.375rem 0.875rem; background: #edf2f7; color: #4a5568; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">Patrón de uso</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem;">
                    @foreach($engagementStats['activity_by_day'] as $day)
                        <div style="background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; text-align: center;">
                            <div style="font-size: 0.875rem; color: #718096; font-weight: 600; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">{{ $day['day'] }}</div>
                            <div style="font-size: 2rem; font-weight: 900; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">{{ $day['count'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        }
    };

    @if($typingStats['total_sessions'] > 0)
    const wpmData = @json($typingStats['wpm_evolution']);
    new Chart(document.getElementById('wpmChart'), {
        type: 'line',
        data: {
            labels: wpmData.map(d => d.date),
            datasets: [{
                label: 'WPM',
                data: wpmData.map(d => d.wpm),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: '#667eea',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: chartOptions
    });

    const accuracyData = @json($typingStats['accuracy_evolution']);
    new Chart(document.getElementById('accuracyChart'), {
        type: 'line',
        data: {
            labels: accuracyData.map(d => d.date),
            datasets: [{
                label: 'Precisión (%)',
                data: accuracyData.map(d => d.accuracy),
                borderColor: '#48bb78',
                backgroundColor: 'rgba(72, 187, 120, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: '#48bb78',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: chartOptions
    });
    @endif

    @if($englishStats['total_sessions'] > 0)
    const englishScoresData = @json($englishStats['score_evolution']);
    new Chart(document.getElementById('englishScoresChart'), {
        type: 'line',
        data: {
            labels: englishScoresData.map(d => d.date),
            datasets: [{
                label: 'Score',
                data: englishScoresData.map(d => d.score),
                borderColor: '#f5576c',
                backgroundColor: 'rgba(245, 87, 108, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: '#f5576c',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: chartOptions
    });
    @endif
});
</script>
@endsection
