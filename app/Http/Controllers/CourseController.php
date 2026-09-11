<?php

namespace App\Http\Controllers;

use App\Events\CourseCompleted;
use App\Models\Course;
use App\Models\CourseProgress;
use App\Models\CourseSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    /**
     * Mostrar la página principal de selección de cursos
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Obtener cursos del usuario
        $courses = Course::where('user_id', $user->id)
            ->with(['sessions', 'progress' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Si es una petición AJAX, devolver JSON
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'courses' => $courses->map(function ($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'level' => $course->level,
                        'created_at' => $course->created_at->toISOString(),
                        'sessions_count' => $course->sessions->count(),
                        'completed_sessions' => $course->progress->where('completed', true)->count(),
                    ];
                }),
            ]);
        }

        return view('courses.index', compact('courses'));
    }

    /**
     * Display a specific course
     *
     * @param  int  $courseId
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show($courseId)
    {
        try {
            // Eager load necessary relationships with proper ordering
            $course = Course::with([
                'sessions' => function ($query) {
                    $query->orderBy('session_order')
                        ->with(['progress' => function ($q) {
                            $q->where('user_id', Auth::id());
                        }]);
                },
            ])->findOrFail($courseId);

            // Verify ownership
            if ($course->user_id !== Auth::id()) {
                Log::warning('Unauthorized access attempt', [
                    'course_id' => $courseId,
                    'user_id' => Auth::id(),
                    'course_owner' => $course->user_id,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                abort(403, 'No tienes permiso para ver este curso.');
            }

            // Get user progress for all sessions in one query
            $progress = CourseProgress::where('user_id', Auth::id())
                ->where('course_id', $course->id)
                ->get()
                ->keyBy('session_id');

            // Calculate overall progress
            $totalSessions = $course->sessions->count();
            $completedSessions = $progress->where('completed', true)->count();
            $progressPercentage = $totalSessions > 0
                ? round(($completedSessions / $totalSessions) * 100)
                : 0;

            // Find next session to continue
            $nextSession = $course->sessions
                ->where('status', 'active')
                ->filter(function ($session) {
                    return ! $session->progress->first() || ! $session->progress->first()->completed;
                })
                ->sortBy('session_order')
                ->first();

            // Log successful access
            Log::info('Course details retrieved', [
                'course_id' => $courseId,
                'sessions_count' => $totalSessions,
                'completed_sessions' => $completedSessions,
                'progress_percentage' => $progressPercentage,
                'next_session_id' => $nextSession ? $nextSession->id : null,
                'user_id' => Auth::id(),
            ]);

            // Cache the response for 5 minutes
            return view('courses.show', [
                'course' => $course,
                'progress' => $progress,
                'progressPercentage' => $progressPercentage,
                'nextSession' => $nextSession,
                'breadcrumbs' => [
                    ['url' => route('tutor'), 'title' => 'Centro de Aprendizaje'],
                    ['url' => route('courses.index'), 'title' => 'Mis Cursos'],
                    ['title' => $course->title],
                ],
            ])->withHeaders([
                'Cache-Control' => 'max-age=300, public',
                'ETag' => md5($course->updated_at),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Course not found', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return redirect()
                ->route('courses.index')
                ->with('error', 'El curso solicitado no existe o fue eliminado.');

        } catch (\Exception $e) {
            Log::error('Error fetching course details', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('courses.index')
                ->with('error', 'Ocurrió un error al cargar el curso. Por favor, intenta de nuevo.');
        }
    }

    /**
     * Mark a session as completed
     *
     * @param  int  $courseId
     * @param  int  $sessionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeSession(Request $request, $courseId, $sessionId)
    {
        try {
            $request->validate([
                'score' => 'nullable|integer|min:0|max:100',
                'feedback' => 'nullable|string|max:1000',
            ]);

            $course = Course::findOrFail($courseId);
            $session = $course->sessions()->findOrFail($sessionId);

            // Verify ownership
            if ($course->user_id !== Auth::id()) {
                Log::warning('Unauthorized session completion attempt', [
                    'course_id' => $courseId,
                    'session_id' => $sessionId,
                    'user_id' => Auth::id(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para completar esta sesión.',
                ], 403);
            }

            // Update or create progress
            $progress = CourseProgress::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'course_id' => $course->id,
                    'session_id' => $session->id,
                ],
                [
                    'completed' => true,
                    'completed_at' => now(),
                    'score' => $request->input('score'),
                    'feedback' => $request->input('feedback'),
                    'metadata' => [
                        'time_spent' => $request->input('time_spent'),
                        'attempts' => $request->input('attempts', 1),
                        'device_info' => $request->header('User-Agent'),
                    ],
                ]
            );

            // Check if this was the last session
            $totalSessions = $course->sessions()->count();
            $completedSessions = CourseProgress::where('user_id', Auth::id())
                ->where('course_id', $course->id)
                ->where('completed', true)
                ->count();

            $isCourseComplete = ($completedSessions === $totalSessions);

            if ($isCourseComplete) {
                // Mark course as completed if all sessions are done
                $course->update([
                    'completed_at' => now(),
                    'status' => 'completed',
                ]);

                // Evento comentado porque la clase no existe
                // event(new CourseCompleted($course, Auth::user()));
            }

            // Get next session for navigation
            $nextSession = $course->sessions()
                ->where('session_order', '>', $session->session_order)
                ->orderBy('session_order')
                ->first();

            Log::info('Session marked as completed', [
                'course_id' => $courseId,
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'score' => $request->input('score'),
                'is_course_complete' => $isCourseComplete,
            ]);

            return response()->json([
                'success' => true,
                'message' => $isCourseComplete
                    ? '¡Felicidades! Has completado el curso exitosamente.'
                    : 'Sesión marcada como completada.',
                'next_session' => $nextSession ? [
                    'id' => $nextSession->id,
                    'title' => $nextSession->title,
                    'url' => route('courses.session', [$course->id, $nextSession->id]),
                ] : null,
                'course_complete' => $isCourseComplete,
                'progress' => [
                    'completed' => $completedSessions,
                    'total' => $totalSessions,
                    'percentage' => round(($completedSessions / $totalSessions) * 100),
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Session not found for completion', [
                'course_id' => $courseId,
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'La sesión solicitada no existe o fue eliminada.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error completing session', [
                'course_id' => $courseId,
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al marcar la sesión como completada. Por favor, intenta de nuevo.',
            ], 500);
        }
    }

    /**
     * Validate course completeness and return detailed status
     *
     * @param  int  $courseId
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateCompleteness($courseId)
    {
        try {
            $course = Course::with('sessions')->findOrFail($courseId);

            // Verify ownership
            if ($course->user_id !== Auth::id()) {
                Log::warning('Unauthorized validation attempt', [
                    'course_id' => $courseId,
                    'user_id' => Auth::id(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para validar este curso.',
                ], 403);
            }

            $validation = [
                'is_complete' => true,
                'sessions' => [],
                'missing_resources' => [
                    'images' => 0,
                    'audios' => 0,
                ],
                'warnings' => [],
            ];

            // Check each session
            foreach ($course->sessions as $session) {
                $sessionStatus = [
                    'id' => $session->id,
                    'title' => $session->title,
                    'has_content' => ! empty($session->content) && strlen(trim($session->content)) > 500,
                    'has_image' => false,
                    'has_audio' => false,
                    'has_practice' => ! empty($session->practice_activity),
                    'is_active' => $session->status === 'active',
                    'warnings' => [],
                ];

                // Check image
                $imagePath = 'courses/'.Auth::id()."/{$courseId}/img/session_{$session->id}.png";
                $sessionStatus['has_image'] = Storage::disk('public')->exists($imagePath);

                // Check audio
                $audioPath = 'courses/'.Auth::id()."/{$courseId}/audio/session_{$session->id}.mp3";
                $sessionStatus['has_audio'] = Storage::disk('public')->exists($audioPath);

                // Check for issues
                if (! $sessionStatus['has_content']) {
                    $sessionStatus['warnings'][] = 'El contenido de la sesión es demasiado corto o está vacío.';
                }

                if (! $sessionStatus['has_image']) {
                    $validation['missing_resources']['images']++;
                    $sessionStatus['warnings'][] = 'Falta la imagen para esta sesión.';
                }

                if (! $sessionStatus['has_audio']) {
                    $validation['missing_resources']['audios']++;
                    $sessionStatus['warnings'][] = 'Falta el audio para esta sesión.';
                }

                if (! $sessionStatus['has_practice']) {
                    $sessionStatus['warnings'][] = 'No hay actividad de práctica para esta sesión.';
                }

                if (! empty($sessionStatus['warnings'])) {
                    $validation['is_complete'] = false;
                }

                $validation['sessions'][] = $sessionStatus;
            }

            // Add general warnings
            if ($course->sessions->count() < 3) {
                $validation['warnings'][] = 'El curso tiene muy pocas sesiones (mínimo recomendado: 3).';
                $validation['is_complete'] = false;
            }

            if (empty($course->description)) {
                $validation['warnings'][] = 'El curso no tiene una descripción.';
                $validation['is_complete'] = false;
            }

            if (empty($course->objectives) || ! is_array($course->objectives) || count($course->objectives) < 2) {
                $validation['warnings'][] = 'El curso debe tener al menos 2 objetivos de aprendizaje definidos.';
                $validation['is_complete'] = false;
            }

            Log::info('Course validation completed', [
                'course_id' => $courseId,
                'is_complete' => $validation['is_complete'],
                'missing_resources' => $validation['missing_resources'],
            ]);

            return response()->json([
                'success' => true,
                'is_complete' => $validation['is_complete'],
                'message' => $validation['is_complete']
                    ? '¡El curso está completo y listo para publicar!'
                    : 'Se encontraron algunos problemas que necesitan atención.',
                'data' => $validation,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Course not found for validation', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'El curso solicitado no existe o fue eliminado.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error validating course', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al validar el curso. Por favor, intenta de nuevo.',
            ], 500);
        }
    }

    /**
     * Delete a course and all its associated resources
     *
     * @param  int  $courseId
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Delete a course and all its associated resources
     *
     * @param  int  $courseId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($courseId)
    {
        try {
            // Verificar si el curso existe
            $course = Course::with(['sessions', 'progress'])->find($courseId);

            // Si el curso no existe, devolver error 404
            if (! $course) {
                Log::warning('Intento de eliminar curso que no existe', [
                    'course_id' => $courseId,
                    'user_id' => Auth::id(),
                    'ip' => request()->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'El curso no existe o ya ha sido eliminado.',
                ], 404);
            }

            // Verificar propiedad del curso
            if ($course->user_id !== Auth::id()) {
                Log::warning('Intento no autorizado de eliminar curso', [
                    'course_id' => $courseId,
                    'user_id' => Auth::id(),
                    'course_owner' => $course->user_id,
                    'ip' => request()->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar este curso.',
                ], 403);
            }

            // Iniciar transacción
            DB::beginTransaction();

            try {
                // Ruta base para los archivos del curso
                $basePath = 'courses/'.Auth::id().'/'.$courseId;

                // Eliminar imágenes si existen
                if (Storage::disk('public')->exists($basePath.'/img')) {
                    try {
                        Storage::disk('public')->deleteDirectory($basePath.'/img');
                        Log::debug('Directorio de imágenes eliminado', ['path' => $basePath.'/img']);
                    } catch (\Exception $e) {
                        Log::warning('Error al eliminar directorio de imágenes', [
                            'path' => $basePath.'/img',
                            'error' => $e->getMessage(),
                        ]);
                        // Continuar a pesar del error
                    }
                }

                // Eliminar audios si existen
                if (Storage::disk('public')->exists($basePath.'/audio')) {
                    try {
                        Storage::disk('public')->deleteDirectory($basePath.'/audio');
                        Log::debug('Directorio de audios eliminado', ['path' => $basePath.'/audio']);
                    } catch (\Exception $e) {
                        Log::warning('Error al eliminar directorio de audios', [
                            'path' => $basePath.'/audio',
                            'error' => $e->getMessage(),
                        ]);
                        // Continuar a pesar del error
                    }
                }

                // Eliminar directorio del curso si está vacío
                if (Storage::disk('public')->exists($basePath)) {
                    try {
                        $files = Storage::disk('public')->allFiles($basePath);
                        if (empty($files)) {
                            Storage::disk('public')->deleteDirectory($basePath);
                            Log::debug('Directorio del curso eliminado', ['path' => $basePath]);
                        } else {
                            Log::warning('El directorio del curso no está vacío, omitiendo eliminación', [
                                'path' => $basePath,
                                'archivos' => count($files),
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error al verificar directorio del curso', [
                            'path' => $basePath,
                            'error' => $e->getMessage(),
                        ]);
                        // Continuar a pesar del error
                    }
                }

                // Eliminar registros de progreso
                try {
                    $progressCount = $course->progress()->count();
                    $course->progress()->delete();
                    Log::debug('Registros de progreso eliminados', ['count' => $progressCount]);
                } catch (\Exception $e) {
                    Log::error('Error al eliminar registros de progreso', [
                        'course_id' => $courseId,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                // Eliminar sesiones
                try {
                    $sessionsCount = $course->sessions()->count();
                    $course->sessions()->delete();
                    Log::debug('Sesiones eliminadas', ['count' => $sessionsCount]);
                } catch (\Exception $e) {
                    Log::error('Error al eliminar sesiones', [
                        'course_id' => $courseId,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                // Finalmente, eliminar el curso
                $courseTitle = $course->title; // Guardar título para el log
                $course->delete();

                // Confirmar transacción
                DB::commit();

                Log::info('Curso eliminado exitosamente', [
                    'course_id' => $courseId,
                    'user_id' => Auth::id(),
                    'course_title' => $courseTitle,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'El curso y todos sus recursos han sido eliminados correctamente.',
                    'redirect_url' => route('courses.index'),
                ]);

            } catch (\Exception $e) {
                // Revertir transacción en caso de error
                DB::rollBack();

                // Registrar el error
                Log::error('Error en la transacción de eliminación de curso', [
                    'course_id' => $courseId,
                    'user_id' => Auth::id(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Lanzar excepción para que sea manejada por el catch externo
                throw $e;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Registrar intento de eliminar un curso que no existe
            Log::warning('Intento de eliminar curso no encontrado', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'El curso ya ha sido eliminado o no existe.',
            ], 404);

        } catch (\Illuminate\Database\QueryException $e) {
            // Manejar errores específicos de la base de datos
            Log::error('Error de base de datos al eliminar curso', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'ip' => request()->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en la base de datos al intentar eliminar el curso. Por favor, inténtalo de nuevo.',
            ], 500);

        } catch (\Exception $e) {
            // Manejar cualquier otro tipo de error
            Log::error('Error inesperado al eliminar curso', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error inesperado al intentar eliminar el curso. Por favor, inténtalo de nuevo más tarde.',
                'error_code' => 'delete_course_error',
            ], 500);
        }
    }

    /**
     * Generate an image for a session using DALL-E
     *
     * @param  \App\Models\CourseSession  $session
     * @return string|false URL of the generated image or false on failure
     */
    protected function generateSessionImage($session)
    {
        try {
            $course = $session->course;
            $prompt = $this->createImagePrompt($session);

            Log::info('Generating session image', [
                'session_id' => $session->id,
                'course_id' => $course->id,
                'prompt' => $prompt,
            ]);

            $credentials = $this->getApiCredentials();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($credentials['base_url'].'/images/generations', [
                // gpt-image-1: dall-e-3 se apagó el 2026-05-12. No acepta style ni
                // response_format, y la calidad va en low/medium/high (no standard).
                'model' => 'gpt-image-1',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'medium',
            ]);

            if (! $response->successful()) {
                Log::error('OpenAI image API error', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'session_id' => $session->id,
                ]);

                return false;
            }

            // gpt-image-1 siempre responde en base64, nunca con una URL que descargar.
            $b64 = $response->json('data.0.b64_json');

            if (empty($b64)) {
                Log::error('Empty image payload from OpenAI', [
                    'response' => $response->json(),
                    'session_id' => $session->id,
                ]);

                return false;
            }

            $imageData = base64_decode($b64);
            if ($imageData === false) {
                throw new \Exception('Failed to decode generated image');
            }

            $directory = 'courses/'.Auth::id()."/{$course->id}/img";
            $filename = "session_{$session->id}.png";

            // Ensure directory exists
            if (! Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            // Save the image
            $path = "{$directory}/{$filename}";
            Storage::disk('public')->put($path, $imageData);

            // Update session with image path
            $session->update([
                'image_path' => $path,
                'image_generated_at' => now(),
            ]);

            Log::info('Session image generated successfully', [
                'session_id' => $session->id,
                'path' => $path,
            ]);

            return Storage::disk('public')->url($path);

        } catch (\Exception $e) {
            Log::error('Error generating session image', [
                'session_id' => $session->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Generate audio for a session using OpenAI TTS
     *
     * @param  \App\Models\CourseSession  $session
     * @return string|false URL of the generated audio or false on failure
     */
    protected function generateSessionAudio($session)
    {
        try {
            $course = $session->course;
            $text = $this->createAudioScript($session);

            if (empty($text)) {
                Log::error('Empty audio script', ['session_id' => $session->id]);

                return false;
            }

            Log::info('Generating session audio', [
                'session_id' => $session->id,
                'course_id' => $course->id,
                'text_length' => strlen($text),
            ]);

            $credentials = $this->getApiCredentials();

            // Split text into chunks of 4096 characters (TTS limit)
            $chunks = str_split($text, 3900);
            $audioFiles = [];
            $directory = 'courses/'.Auth::id()."/{$course->id}/audio";

            foreach ($chunks as $index => $chunk) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$credentials['api_key'],
                    'Content-Type' => 'application/json',
                ])->timeout(120)->post($credentials['base_url'].'/audio/speech', [
                    'model' => 'tts-1-hd',
                    'input' => $chunk,
                    'voice' => 'nova', // Cambiado de 'echo' a 'nova' para mejor pronunciación en español
                    'response_format' => 'mp3',
                    'speed' => 1.0,
                ]);

                if (! $response->successful()) {
                    Log::error('TTS API error', [
                        'status' => $response->status(),
                        'response' => $response->body(),
                        'session_id' => $session->id,
                        'chunk' => $index,
                    ]);

                    continue;
                }

                $audioData = $response->body();

                $directory = 'courses/'.Auth::id()."/{$course->id}/audio";
                $filename = "session_{$session->id}_part".($index + 1).'.mp3';

                // Ensure directory exists
                if (! Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }

                // Save the audio chunk
                $path = "{$directory}/{$filename}";
                Storage::disk('public')->put($path, $audioData);

                $audioFiles[] = $path;

                // Small delay between chunks to avoid rate limiting
                if (count($chunks) > 1) {
                    sleep(1);
                }
            }

            if (empty($audioFiles)) {
                throw new \Exception('No audio chunks were generated successfully');
            }

            // If only one file, rename it to the final name
            if (count($audioFiles) === 1) {
                $finalPath = str_replace('_part1', '', $audioFiles[0]);
                Storage::disk('public')->move($audioFiles[0], $finalPath);
                $audioPath = $finalPath;
            } else {
                // Combine multiple audio files using FFmpeg
                $audioPath = "{$directory}/session_{$session->id}.mp3";
                $this->combineAudioFiles($audioFiles, Storage::disk('public')->path($audioPath));

                // Clean up temporary files
                foreach ($audioFiles as $file) {
                    Storage::disk('public')->delete($file);
                }
            }

            // Update session with audio path
            $session->update([
                'audio_path' => $audioPath,
                'audio_generated_at' => now(),
            ]);

            Log::info('Session audio generated successfully', [
                'session_id' => $session->id,
                'path' => $audioPath,
                'duration' => $this->getAudioDuration(Storage::disk('public')->path($audioPath)),
            ]);

            return Storage::disk('public')->url($audioPath);

        } catch (\Exception $e) {
            Log::error('Error generating session audio', [
                'session_id' => $session->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Create a prompt for image generation
     *
     * @param  \App\Models\CourseSession  $session
     * @return string
     */
    protected function createImagePrompt($session)
    {
        $course = $session->course;

        return "Create a detailed, educational illustration for a course session titled '{$session->title}'. ".
               "Course: {$course->title}. ".
               "Level: {$course->level}. ".
               'Style: Clean, professional, and visually engaging. '.
               'Focus on the main concept: '.
               (strlen($session->content) > 200 ? substr(strip_tags($session->content), 0, 200).'...' : strip_tags($session->content)).' '.
               'Include relevant diagrams, charts, or illustrations that would help explain the topic. '.
               'Use a color scheme that is easy on the eyes and appropriate for educational content.';
    }

    /**
     * Create a script for audio generation
     *
     * @param  \App\Models\CourseSession  $session
     * @return string
     */
    protected function createAudioScript($session)
    {
        // Usar el script de audio específico en lugar de todo el contenido
        if (! empty($session->audio_script)) {
            $content = strip_tags($session->audio_script);
        } else {
            // Fallback al contenido si no hay script de audio
            $content = strip_tags($session->content);

            // Buscar la sección "Guion de Audio" en el contenido
            if (preg_match('/Guion de Audio[\s\S]*?(?=\n#|$)/i', $content, $matches)) {
                $content = $matches[0];
                // Quitar el encabezado "Guion de Audio"
                $content = preg_replace('/Guion de Audio[\s]*/', '', $content);
            }
        }

        // Limitar la longitud para TTS
        $paragraphs = preg_split('/\r\n|\r|\n/', $content);
        $script = '';
        $charCount = 0;
        $maxChars = 15000; // Limit to ~15,000 characters for TTS

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }

            if (($charCount + strlen($paragraph)) > $maxChars) {
                $script .= ' '.substr($paragraph, 0, $maxChars - $charCount).'...';
                break;
            }

            $script .= ' '.$paragraph;
            $charCount += strlen($paragraph);
        }

        // Registrar en el log para depuración
        \Illuminate\Support\Facades\Log::info('Script de audio generado', [
            'session_id' => $session->id,
            'script_length' => strlen($script),
            'from_audio_script' => ! empty($session->audio_script),
        ]);

        return trim($script);
    }

    /**
     * Combine multiple audio files into one
     *
     * @param  array  $inputFiles
     * @param  string  $outputFile
     * @return bool
     */
    protected function combineAudioFiles($inputFiles, $outputFile)
    {
        try {
            // Create a temporary file list for FFmpeg
            $listFile = tempnam(sys_get_temp_dir(), 'ffmpeg_concat_').'.txt';
            $listContent = '';

            foreach ($inputFiles as $file) {
                $path = Storage::disk('public')->path($file);
                $listContent .= "file '".str_replace("'", "'\\''", $path)."'\n";
            }

            file_put_contents($listFile, $listContent);

            // Use FFmpeg to concatenate the files
            $ffmpegPath = config('services.ffmpeg.path', 'ffmpeg');
            $command = sprintf(
                '%s -f concat -safe 0 -i %s -c copy -y %s 2>&1',
                $ffmpegPath,
                escapeshellarg($listFile),
                escapeshellarg($outputFile)
            );

            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            // Clean up the temporary file
            @unlink($listFile);

            if ($returnVar !== 0) {
                throw new \Exception('FFmpeg error: '.implode("\n", $output));
            }

            return file_exists($outputFile) && filesize($outputFile) > 0;

        } catch (\Exception $e) {
            Log::error('Error combining audio files', [
                'error' => $e->getMessage(),
                'output' => $output ?? [],
                'return_var' => $returnVar ?? null,
            ]);

            return false;
        }
    }

    /**
     * Get duration of an audio file in seconds
     *
     * @param  string  $filePath
     * @return int
     */
    protected function getAudioDuration($filePath)
    {
        try {
            $command = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg($filePath);
            $duration = exec($command);

            if ($duration === false || $duration === null || ! is_numeric($duration)) {
                Log::warning('Could not determine audio duration', [
                    'file_path' => $filePath,
                    'command' => $command,
                    'result' => $duration,
                ]);

                return 0;
            }

            return (int) $duration;
        } catch (\Exception $e) {
            Log::error('Error getting audio duration: '.$e->getMessage(), [
                'file_path' => $filePath,
                'exception' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Generate resources (image and/or audio) for a specific session
     *
     * @param  int  $session
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateSessionResources(Course $course, $session)
    {
        try {
            // Aumentar el tiempo límite de ejecución para esta operación
            set_time_limit(300);

            // Verificar que el curso pertenece al usuario
            if ($course->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para modificar este curso',
                ], 403);
            }

            // Obtener la sesión específica
            $session = CourseSession::where('id', $session)
                ->where('course_id', $course->id)
                ->firstOrFail();

            $generated = [
                'images' => 0,
                'audios' => 0,
                'errors' => [],
            ];

            // Generar imagen si no existe
            try {
                $imageUrl = $this->generateSessionImage($session);
                if ($imageUrl) {
                    $generated['images']++;
                }
            } catch (\Exception $e) {
                $generated['errors'][] = [
                    'type' => 'image',
                    'session_id' => $session->id,
                    'message' => $e->getMessage(),
                ];

                Log::error('Error generating session image', [
                    'course_id' => $course->id,
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Generar audio si no existe
            try {
                $audioUrl = $this->generateSessionAudio($session);
                if ($audioUrl) {
                    $generated['audios']++;
                }
            } catch (\Exception $e) {
                $generated['errors'][] = [
                    'type' => 'audio',
                    'session_id' => $session->id,
                    'message' => $e->getMessage(),
                ];

                Log::error('Error generating session audio', [
                    'course_id' => $course->id,
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Preparar mensaje de respuesta
            $message = 'Recursos generados: ';
            $message .= $generated['images'].' imágenes, ';
            $message .= $generated['audios'].' audios.';

            if (! empty($generated['errors'])) {
                $message .= ' Se encontraron '.count($generated['errors']).' errores.';
            }

            Log::info('Session resources generated', [
                'course_id' => $course->id,
                'session_id' => $session->id,
                'generated' => $generated,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $generated,
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating session resources: '.$e->getMessage(), [
                'course_id' => $course->id,
                'session_id' => is_object($session) ? $session->id : $session,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar recursos: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate missing resources (images/audio) for a course
     *
     * @param  int  $courseId
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeResources($courseId)
    {
        try {
            $course = Course::with('sessions')->findOrFail($courseId);

            // Verify ownership
            if ($course->user_id !== Auth::id()) {
                Log::warning('Unauthorized resource generation attempt', [
                    'course_id' => $courseId,
                    'user_id' => Auth::id(),
                    'course_owner' => $course->user_id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para generar recursos para este curso.',
                ], 403);
            }

            // Create directories if they don't exist
            $directories = $this->createCourseDirectories(Auth::id(), $courseId);

            $generated = [
                'images' => 0,
                'audios' => 0,
                'errors' => [],
            ];

            // Process each session
            foreach ($course->sessions as $session) {
                // Generate image if it doesn't exist
                $imagePath = 'courses/'.Auth::id()."/{$courseId}/img/session_{$session->id}.png";
                if (! Storage::disk('public')->exists($imagePath)) {
                    try {
                        $imageUrl = $this->generateSessionImage($session);
                        if ($imageUrl) {
                            $generated['images']++;
                        }
                    } catch (\Exception $e) {
                        $generated['errors'][] = [
                            'type' => 'image',
                            'session_id' => $session->id,
                            'error' => $e->getMessage(),
                        ];
                        Log::error('Error generating image', [
                            'session_id' => $session->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Generate audio if it doesn't exist
                $audioPath = 'courses/'.Auth::id()."/{$courseId}/audio/session_{$session->id}.mp3";
                if (! Storage::disk('public')->exists($audioPath)) {
                    try {
                        $audioUrl = $this->generateSessionAudio($session);
                        if ($audioUrl) {
                            $generated['audios']++;
                        }
                    } catch (\Exception $e) {
                        $generated['errors'][] = [
                            'type' => 'audio',
                            'session_id' => $session->id,
                            'error' => $e->getMessage(),
                        ];
                        Log::error('Error generating audio', [
                            'session_id' => $session->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Small delay to avoid rate limiting
                usleep(500000); // 0.5 seconds
            }

            $message = 'Recursos generados exitosamente: ';
            $message .= $generated['images'].' imágenes, ';
            $message .= $generated['audios'].' audios.';

            if (! empty($generated['errors'])) {
                $message .= ' Se encontraron '.count($generated['errors']).' errores.';
            }

            Log::info('Course resources generated', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'generated' => $generated,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $generated,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Course not found for resource generation', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'El curso solicitado no existe o fue eliminado.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error generating course resources', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al generar los recursos. Por favor, intenta de nuevo.',
            ], 500);
        }
    }

    /**
     * Display a specific session of a course
     *
     * @param  int  $courseId
     * @param  int  $sessionId
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showSession($courseId, $sessionId)
    {
        try {
            $course = Course::findOrFail($courseId);
            $session = $course->sessions()->findOrFail($sessionId);

            // Verify ownership
            if ($course->user_id !== Auth::id()) {
                Log::warning('Unauthorized session access attempt', [
                    'course_id' => $courseId,
                    'session_id' => $sessionId,
                    'user_id' => Auth::id(),
                    'course_owner' => $course->user_id,
                ]);
                abort(403, 'No tienes permiso para ver esta sesión.');
            }

            // Get user progress for this session
            $progress = CourseProgress::firstOrCreate(
                [
                    'user_id' => Auth::id(),
                    'course_id' => $course->id,
                    'session_id' => $session->id,
                ],
                [
                    'completed' => false,
                    'started_at' => now(),
                ]
            );

            // Get next and previous sessions for navigation
            $sessions = $course->sessions()->orderBy('session_order')->get();
            $currentIndex = $sessions->search(function ($item) use ($sessionId) {
                return $item->id == $sessionId;
            });

            $previousSession = $currentIndex > 0 ? $sessions[$currentIndex - 1] : null;
            $nextSession = $currentIndex < $sessions->count() - 1 ? $sessions[$currentIndex + 1] : null;

            // Check if resources exist
            $hasImage = Storage::disk('public')->exists('courses/'.Auth::id()."/{$courseId}/img/session_{$sessionId}.png");
            $hasAudio = Storage::disk('public')->exists('courses/'.Auth::id()."/{$courseId}/audio/session_{$sessionId}.mp3");

            // Asegurarse de que los campos JSON se procesen correctamente
            // Procesar audio_script
            $audioScript = $session->audio_script;

            // Procesar image_description
            $imageDescription = $session->image_description;

            // Format practice activity if exists
            $practiceActivity = null;
            if (! empty($session->practice_activity)) {
                $practiceActivity = is_string($session->practice_activity)
                    ? json_decode($session->practice_activity, true)
                    : $session->practice_activity;
            }

            Log::info('Session details retrieved', [
                'course_id' => $courseId,
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'has_image' => $hasImage,
                'has_audio' => $hasAudio,
            ]);

            // Usar la nueva vista modular
            return view('courses.session_modular', [
                'course' => $course,
                'session' => $session,
                'progress' => $progress,
                'previousSession' => $previousSession,
                'nextSession' => $nextSession,
                'hasImage' => $hasImage,
                'hasAudio' => $hasAudio,
                'audioScript' => $audioScript,
                'imageDescription' => $imageDescription,
                'practiceActivity' => $practiceActivity,
                'isCompleted' => (bool) $progress->completed,
                'userId' => Auth::id(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Session not found', [
                'course_id' => $courseId,
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('courses.show', $courseId)
                ->with('error', 'La sesión solicitada no existe o fue eliminada.');

        } catch (\Exception $e) {
            Log::error('Error fetching session details', [
                'course_id' => $courseId,
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('courses.show', $courseId)
                ->with('error', 'Error al cargar los detalles de la sesión. Por favor, inténtalo de nuevo.');
        }
    }

    private function getApiCredentials()
    {
        return [
            'api_key' => config('services.openai.api_key'),
            'base_url' => 'https://api.openai.com/v1',
        ];
    }

    /**
     * Generar curso con IA usando OpenAI
     *
     * @param  string  $topic  Tema del curso
     * @param  string  $level  Nivel del curso (beginner, intermediate, advanced)
     * @param  int  $sessionsCount  Número de sesiones a generar
     * @return array|null Datos del curso generado o null en caso de error
     *
     * @throws \Exception Si hay un error en la generación
     */
    private function generateCourseWithAI($topic, $level, $sessionsCount)
    {
        $prompt = $this->createCourseGenerationPrompt($topic, $level, $sessionsCount);

        $credentials = $this->getApiCredentials();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$credentials['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout(180)->post($credentials['base_url'].'/chat/completions', [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un experto en diseño curricular y educación. Tu tarea es generar EXACTAMENTE el número de sesiones solicitadas con contenido educativo completo y detallado. SIEMPRE debes generar TODAS las sesiones pedidas. Responde ÚNICAMENTE con JSON válido, sin texto adicional ni bloques de código markdown.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => 16000,
            'temperature' => 0.7,
        ]);

        if (! $response->successful()) {
            throw new \Exception('Error al generar curso con IA: '.$response->body());
        }

        $content = $response->json()['choices'][0]['message']['content'];

        Log::info('OpenAI response content:', ['content' => $content]);

        // Limpiar y extraer JSON de la respuesta
        $cleanContent = $this->extractJsonFromResponse($content);

        // Validar JSON antes del parsing
        try {
            $this->validateJsonStructure($cleanContent);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'truncado')) {
                Log::warning('JSON truncado detectado, intentando con menos sesiones');
                // Si el JSON está truncado, intentar con menos sesiones
                if ($sessionsCount > 3) {
                    return $this->generateCourseWithAI($topic, $level, 3);
                } else {
                    throw new \Exception('JSON truncado incluso con 3 sesiones: '.$e->getMessage());
                }
            }
            throw $e;
        }

        // Intentar parsear como JSON
        $courseData = json_decode($cleanContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON parsing error:', [
                'error' => json_last_error_msg(),
                'json_error_code' => json_last_error(),
                'original_content_preview' => substr($content, 0, 500),
                'cleaned_content_preview' => substr($cleanContent, 0, 500),
                'content_length' => strlen($content),
                'cleaned_length' => strlen($cleanContent),
            ]);

            // Intentar reparar JSON común
            $repairedContent = $this->repairJson($cleanContent);
            if ($repairedContent !== $cleanContent) {
                Log::info('Attempting to parse repaired JSON');
                $courseData = json_decode($repairedContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    Log::info('Successfully parsed repaired JSON');
                } else {
                    Log::error('Repaired JSON also failed:', ['error' => json_last_error_msg()]);
                    throw new \Exception('Error al parsear respuesta de IA: '.json_last_error_msg());
                }
            } else {
                throw new \Exception('Error al parsear respuesta de IA: '.json_last_error_msg());
            }
        }

        Log::info('Course data parsed successfully:', ['course_title' => $courseData['title'] ?? 'N/A']);

        return $courseData;
    }

    /**
     * Crear prompt optimizado para generación de curso
     */
    private function createCourseGenerationPrompt($topic, $level, $sessionsCount)
    {
        $levelNames = [
            'beginner' => 'Principiante',
            'intermediate' => 'Intermedio',
            'advanced' => 'Avanzado',
        ];

        $levelName = $levelNames[$level] ?? $level;

        return <<<EOT
Crea un curso sobre '{$topic}' para nivel {$levelName} con EXACTAMENTE {$sessionsCount} sesiones.

Responde ÚNICAMENTE con JSON válido:

{
  "title": "Título del curso",
  "description": "Descripción del curso (100+ palabras)",
  "sessions": [
    {
      "title": "Título de la sesión",
      "content": "Contenido educativo completo (800+ palabras) con ejemplos prácticos, código real y formato markdown. Usa ## para títulos, ### para subtítulos, **negrita**, `código` y ```bloques de código```.",
      "image_description": "Descripción para imagen educativa (80+ palabras) con elementos visuales específicos, diagramas técnicos o interfaces.",
      "audio_script": "Guión conversacional (400+ palabras) que explique los conceptos clave de forma clara y directa.",
      "practice_activity": {
        "type": "quiz",
        "question": "Pregunta que evalúe comprensión",
        "options": ["Opción A", "Opción B", "Opción C", "Opción D"],
        "correct_answer": 2,
        "explanation": "Explicación de la respuesta correcta (150+ palabras)"
      },
      "estimated_duration": 45
    }
  ]
}

REQUISITOS:
- Contenido EDUCATIVO real, no descriptivo
- Ejemplos prácticos con código funcional
- Formato markdown para legibilidad
- Profundidad apropiada para nivel {$levelName}
- JSON válido sin bloques de código markdown
EOT;
    }

    /**
     * Reparar un JSON potencialmente dañado
     *
     * @param  string  $json  JSON potencialmente dañado
     * @return string|null JSON reparado o null si no se pudo reparar
     */
    private function repairJson($json)
    {
        if (empty($json)) {
            return null;
        }

        $originalJson = $json;
        $attempts = [];

        // Intento 1: Limpieza básica y decodificación directa
        $attempts[] = 'Intento 1: Limpieza básica';
        $cleaned = trim($json);

        // Eliminar caracteres de control excepto tabulaciones y saltos de línea
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned);

        // Eliminar BOM si está presente
        if (strpos($cleaned, "\xEF\xBB\xBF") === 0) {
            $cleaned = substr($cleaned, 3);
        }

        // Verificar si ya es un JSON válido después de la limpieza
        if (json_decode($cleaned) !== null) {
            return $cleaned;
        }

        // Intento 2: Reparar comillas sin escapar
        $attempts[] = 'Intento 2: Reparar comillas sin escapar';
        $repaired = preg_replace_callback(
            '/(?<!\\\\)"([^"]*?)(?<!\\\\)"|([\w]+)(?=:)/',
            function ($matches) {
                if (isset($matches[2])) {
                    // Para claves sin comillas: "clave": valor -> "clave": valor
                    return '"'.$matches[2].'"';
                }

                return $matches[0];
            },
            $cleaned
        );

        if (json_decode($repaired) !== null) {
            return $repaired;
        }

        // Intento 3: Reparar comas finales
        $attempts[] = 'Intento 3: Reparar comas finales';
        $repaired = preg_replace('/,\s*([}\]])/m', '$1', $repaired);

        // Reparar comas faltantes
        $repaired = preg_replace('/}\s*{/', '},{', $repaired);
        $repaired = preg_replace('/"\s*{/', '":{', $repaired);

        if (json_decode($repaired) !== null) {
            return $repaired;
        }

        // Intento 4: Balancear llaves y corchetes
        $attempts[] = 'Intento 4: Balancear llaves y corchetes';
        $openBraces = substr_count($repaired, '{');
        $closeBraces = substr_count($repaired, '}');
        $openBrackets = substr_count($repaired, '[');
        $closeBrackets = substr_count($repaired, ']');

        // Añadir llaves de cierre faltantes
        while ($openBraces > $closeBraces) {
            $repaired .= '}';
            $closeBraces++;
        }

        // Añadir corchetes de cierre faltantes
        while ($openBrackets > $closeBrackets) {
            $repaired .= ']';
            $closeBrackets++;
        }

        // Intento 5: Reparar comillas escapadas incorrectamente
        $attempts[] = 'Intento 5: Reparar comillas escapadas';
        $repaired = str_replace('\\"', '\\\\"', $repaired); // Escapar barras invertidas
        $repaired = preg_replace('/(?<!\\\\)"(.*?)(?<!\\\\)"/', '"$1"', $repaired);

        // Intento 6: Reparar valores sin comillas
        $attempts[] = 'Intento 6: Reparar valores sin comillas';
        $repaired = preg_replace('/:\s*([^\[\]{}:,\s]+)([,\}])/', ':"$1"$2', $repaired);

        // Intento 7: Reparar booleanos y null
        $repaired = preg_replace('/:\s*true([,\}])/', ':true$1', $repaired);
        $repaired = preg_replace('/:\s*false([,\}])/', ':false$1', $repaired);
        $repaired = preg_replace('/:\s*null([,\}])/', ':null$1', $repaired);

        // Verificar si ahora es un JSON válido
        if (json_decode($repaired) !== null) {
            return $repaired;
        }

        // Intento 8: Extraer el objeto JSON más grande posible
        $attempts[] = 'Intento 8: Extraer objeto JSON más grande';
        $extracted = $this->extractLargestJsonObject($repaired);
        if ($extracted !== false) {
            return $extracted;
        }

        // Log detallado del fallo
        Log::warning('No se pudo reparar el JSON después de varios intentos', [
            'original_length' => strlen($originalJson),
            'original_preview' => substr($originalJson, 0, 200).(strlen($originalJson) > 200 ? '...' : ''),
            'repaired_length' => strlen($repaired),
            'repaired_preview' => substr($repaired, 0, 200).(strlen($repaired) > 200 ? '...' : ''),
            'attempts' => $attempts,
            'json_last_error' => json_last_error_msg(),
        ]);

        return null;
    }

    /**
     * Extrae el objeto JSON más grande posible del texto
     *
     * @param  string  $text  Texto que contiene el JSON
     * @return string|false JSON extraído o false si no se pudo extraer
     */
    private function extractLargestJsonObject($text)
    {
        $stack = [];
        $start = strpos($text, '{');

        if ($start === false) {
            return false;
        }

        $best = '';
        $bestLength = 0;
        $inString = false;
        $escape = false;

        for ($i = $start; $i < strlen($text); $i++) {
            $char = $text[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($char === '\\') {
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;
            }

            if (! $inString) {
                if ($char === '{') {
                    array_push($stack, $i);
                } elseif ($char === '}') {
                    if (empty($stack)) {
                        // Llave de cierre sin apertura
                        break;
                    }

                    $startPos = array_pop($stack);

                    if (empty($stack)) {
                        // Hemos cerrado el objeto raíz
                        $json = substr($text, $startPos, $i - $startPos + 1);
                        $length = strlen($json);

                        if ($length > $bestLength) {
                            $best = $json;
                            $bestLength = $length;
                        }
                    }
                }
            }
        }

        if ($bestLength > 0) {
            // Validar que el JSON extraído sea válido
            json_decode($best);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $best;
            }
        }

        return false;
    }

    /**
     * Validar estructura básica del JSON
     *
     * @param  string  $jsonContent  Contenido JSON a validar
     * @return array Datos decodificados si la validación es exitosa
     *
     * @throws \Exception Si el JSON no es válido o está mal formado
     */
    private function validateJsonStructure($jsonContent)
    {
        $trimmed = trim($jsonContent);

        // Verificar longitud mínima
        if (strlen($trimmed) < 20) {
            throw new \Exception('El JSON es demasiado corto (mínimo 20 caracteres)');
        }

        // Verificar que empiece con {
        if (! str_starts_with($trimmed, '{')) {
            throw new \Exception('El JSON debe comenzar con una llave de apertura {');
        }

        // Verificar que termine con }
        if (! str_ends_with($trimmed, '}')) {
            $trimmed = rtrim($trimmed, " \t\n\r\0\x0B,");
            if (! str_ends_with($trimmed, '}')) {
                $trimmed .= '}';
                Log::warning('Se agregó llave de cierre faltante al JSON');
            }
        }

        // Verificar balance de llaves y corchetes
        $openBraces = substr_count($trimmed, '{');
        $closeBraces = substr_count($trimmed, '}');
        $openBrackets = substr_count($trimmed, '[');
        $closeBrackets = substr_count($trimmed, ']');

        // Balancear llaves si es necesario
        while ($openBraces > $closeBraces) {
            $trimmed .= '}';
            $closeBraces++;
            Log::warning('Se agregó llave de cierre faltante');
        }

        // Balancear corchetes si es necesario
        while ($openBrackets > $closeBrackets) {
            $trimmed .= ']';
            $closeBrackets++;
            Log::warning('Se agregó corchete de cierre faltante');
        }

        // Intentar decodificar
        $data = json_decode($trimmed, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = match (json_last_error()) {
                JSON_ERROR_DEPTH => 'La profundidad máxima del stack ha sido excedida',
                JSON_ERROR_STATE_MISMATCH => 'JSON con formato inválido o incorrecto',
                JSON_ERROR_CTRL_CHAR => 'Error de carácter de control, posiblemente codificación incorrecta',
                JSON_ERROR_SYNTAX => 'Error de sintaxis, JSON mal formado',
                JSON_ERROR_UTF8 => 'Caracteres UTF-8 mal formados, posiblemente codificación incorrecta',
                default => 'Error desconocido al decodificar JSON: '.json_last_error_msg()
            };

            throw new \Exception($errorMsg);
        }

        // Validar estructura mínima esperada
        if (! is_array($data)) {
            throw new \Exception('El JSON no es un objeto válido');
        }

        // Verificar campo title
        if (! isset($data['title'])) {
            // Si no hay title, intentar extraer del contenido o crear uno genérico
            if (isset($data['description'])) {
                $data['title'] = 'Curso Personalizado: '.substr($data['description'], 0, 50).'...';
                Log::warning('Campo "title" faltante, generado desde descripción');
            } else {
                $data['title'] = 'Curso Personalizado';
                Log::warning('Campo "title" faltante, usando título genérico');
            }
        }

        // Verificar campo description
        if (! isset($data['description'])) {
            $data['description'] = 'Curso personalizado creado con inteligencia artificial sobre '.($data['title'] ?? 'tema específico');
            Log::warning('Campo "description" faltante, generado automáticamente');
        }

        // Verificar y reparar campo sessions si es necesario
        if (! isset($data['sessions']) || ! is_array($data['sessions']) || empty($data['sessions'])) {
            Log::warning('El campo "sessions" está ausente o vacío en el JSON. Intentando reparar...');

            // Crear estructura de sessions si no existe
            if (! isset($data['sessions'])) {
                $data['sessions'] = [];
            }

            // Crear al menos una sesión predeterminada si no hay ninguna
            if (empty($data['sessions'])) {
                $data['sessions'][] = [
                    'title' => 'Sesión 1: '.$data['title'],
                    'content' => isset($data['description']) ? $data['description'] : 'Contenido de la sesión 1',
                    'image_description' => 'Imagen representativa del tema '.$data['title'],
                    'audio_script' => 'Bienvenido a la sesión 1 del curso '.$data['title'],
                    'practice_activity' => [
                        'type' => 'quiz',
                        'question' => '¿Cuál es el concepto principal de esta sesión?',
                        'options' => ['Opción A', 'Opción B', 'Opción C', 'Opción D'],
                        'correct_answer' => 0,
                        'explanation' => 'Esta es una actividad generada automáticamente.',
                    ],
                    'estimated_duration' => 30,
                ];
                Log::info('Se creó una sesión predeterminada para el curso');
            }
        }

        // Validar cada sesión y completar campos faltantes
        foreach ($data['sessions'] as $index => &$session) {
            if (! isset($session['title'])) {
                $session['title'] = 'Sesión '.($index + 1);
            }
            if (! isset($session['content'])) {
                $session['content'] = 'Contenido de la sesión '.($index + 1);
            }
            if (! isset($session['image_description'])) {
                $session['image_description'] = 'Imagen educativa para la sesión '.($index + 1);
            }
            if (! isset($session['audio_script'])) {
                $session['audio_script'] = 'Guión de audio para la sesión '.($index + 1);
            }
            if (! isset($session['practice_activity'])) {
                $session['practice_activity'] = [
                    'type' => 'quiz',
                    'question' => '¿Cuál es el concepto principal de esta sesión?',
                    'options' => ['Opción A', 'Opción B', 'Opción C', 'Opción D'],
                    'correct_answer' => 0,
                    'explanation' => 'Esta es una actividad generada automáticamente.',
                ];
            }
            if (! isset($session['estimated_duration'])) {
                $session['estimated_duration'] = 30;
            }
        }

        return $data;
    }

    /**
     * Reparar problemas comunes en JSON
     */
    private function repairCommonJsonIssues($jsonContent)
    {
        try {
            // Reparaciones seguras
            $jsonContent = preg_replace('/,\s*}/', '}', $jsonContent); // Comas extra antes de }
            $jsonContent = preg_replace('/}\s*{/', '},{', $jsonContent); // Comas faltantes entre objetos
            $jsonContent = preg_replace('/]\s*\[/', '],[', $jsonContent); // Comas faltantes entre arrays
            $jsonContent = str_replace(["\r\n", "\r"], ' ', $jsonContent); // Saltos de línea
            $jsonContent = preg_replace('/\s+/', ' ', $jsonContent); // Espacios múltiples

            return trim($jsonContent);
        } catch (\Exception $e) {
            Log::error('Error en reparación de JSON: '.$e->getMessage());

            return $jsonContent;
        }
    }

    /**
     * Extrae y limpia el JSON de la respuesta de OpenAI
     *
     * @param  string  $content  Respuesta en bruto de la API de OpenAI
     * @return string JSON válido y limpio
     *
     * @throws \Exception Si no se puede extraer un JSON válido
     */
    private function extractJsonFromResponse($content)
    {
        Log::debug('Iniciando extracción de JSON de la respuesta', [
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 200).(strlen($content) > 200 ? '...' : ''),
        ]);

        // Si el contenido ya es un JSON válido, devolverlo directamente
        if (is_array(json_decode($content, true)) && json_last_error() === JSON_ERROR_NONE) {
            Log::debug('El contenido ya es un JSON válido');

            return $content;
        }

        try {
            // Limpiar el contenido de posibles marcas de formato y espacios en blanco
            $content = trim($content);

            // Intentar extraer JSON de diferentes formatos comunes
            $jsonPatterns = [
                // Formato con ```json ... ```
                '/```(?:json)?\s*([\s\S]*?)\s*```/i',
                // Formato con ``` ... ```
                '/```([\s\S]*?)```/i',
                // Objeto JSON simple - usando una expresión más segura
                '/{[^{}]*(?:{[^{}]*}[^{}]*)*}/s',
                // Objeto JSON simple alternativo
                '/{.*?}/s',
            ];

            // Asegurar que el contenido tenga llaves balanceadas
            $openBraces = substr_count($content, '{');
            $closeBraces = substr_count($content, '}');

            if ($openBraces > $closeBraces) {
                Log::warning('JSON con llaves desbalanceadas: faltan llaves de cierre');
                $content .= str_repeat('}', $openBraces - $closeBraces);
            }

            $bestJson = null;
            $bestJsonScore = 0;

            foreach ($jsonPatterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[0] as $match) {
                        $cleaned = trim($match, "`\n\r \t");
                        $cleaned = preg_replace('/^json\s*/', '', $cleaned);

                        // Verificar si es un JSON válido
                        $decoded = json_decode($cleaned);
                        if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
                            $score = strlen($cleaned);
                            if ($score > $bestJsonScore) {
                                $bestJson = $cleaned;
                                $bestJsonScore = $score;
                            }
                        }
                    }
                }
            }

            // Si encontramos un JSON válido en los patrones, devolverlo
            if ($bestJson !== null) {
                Log::debug('JSON válido encontrado en patrones');

                return $bestJson;
            }

            // Si no encontramos patrones, intentar extraer manualmente
            $startPos = strpos($content, '{');
            $endPos = strrpos($content, '}');

            if ($startPos === false || $endPos === false || $startPos >= $endPos) {
                throw new \Exception('No se pudo encontrar un objeto JSON en la respuesta');
            }

            $jsonContent = substr($content, $startPos, $endPos - $startPos + 1);

            // Verificar si el JSON es válido
            $decoded = json_decode($jsonContent);
            if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
                Log::debug('JSON válido encontrado después de extracción manual');

                return $jsonContent;
            }

            // Intentar reparar JSON común
            $repaired = $this->repairJson($jsonContent);
            if ($repaired !== null) {
                Log::debug('JSON reparado con éxito');

                return $repaired;
            }

            // Si todo falla, intentar extraer el objeto JSON más grande
            $extracted = $this->extractLargestJsonObject($content);
            if ($extracted !== false) {
                Log::debug('Se extrajo el objeto JSON más grande');

                return $extracted;
            }

            // Último intento: forzar la extracción del primer objeto JSON simple
            if (preg_match('/{[^{}]*}/s', $content, $matches)) {
                $forcedJson = $matches[0];
                $decoded = json_decode($forcedJson);
                if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
                    Log::debug('JSON forzado extraído con éxito');

                    return $forcedJson;
                }
            }

            throw new \Exception(sprintf(
                'No se pudo extraer un JSON válido de la respuesta. Error: %s',
                json_last_error_msg()
            ));

        } catch (\Exception $e) {
            Log::error('Error al extraer JSON de la respuesta', [
                'error' => $e->getMessage(),
                'content_preview' => substr($content, 0, 500).(strlen($content) > 500 ? '...' : ''),
                'trace' => $e->getTraceAsString(),
            ]);

            // Si el error es específico de JSON, agregar sugerencias
            if (strpos($e->getMessage(), 'JSON') !== false) {
                Log::error('Sugerencia: La respuesta podría estar truncada o contener caracteres no válidos');
            }

            throw new \Exception('Error procesando la respuesta de OpenAI: '.$e->getMessage());
        }
    }

    /**
     * Crear directorios necesarios para el curso
     */
    private function createCourseDirectories($userId, $courseId)
    {
        $basePath = "courses/{$userId}/{$courseId}";

        try {
            // Crear directorio principal
            Storage::disk('public')->makeDirectory($basePath);

            // Crear subdirectorios
            $directories = [
                "{$basePath}/img",
                "{$basePath}/audio",
                "{$basePath}/documents",
            ];

            foreach ($directories as $directory) {
                if (! Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                    Log::info("Directorio creado: {$directory}");
                }
            }

            return $basePath;

        } catch (\Exception $e) {
            Log::error('Error creando directorios del curso: '.$e->getMessage(), [
                'user_id' => $userId,
                'course_id' => $courseId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Limpia la respuesta de la API eliminando texto innecesario y asegurando formato JSON válido
     *
     * @param  string  $content  Respuesta en bruto de la API
     * @return string Contenido limpio y listo para procesar
     */
    private function cleanApiResponse($content)
    {
        if (! is_string($content)) {
            return $content;
        }

        // Eliminar marcas de código markdown
        $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
        $content = preg_replace('/```\s*$/m', '', $content);

        // Eliminar texto antes del primer { o después del último }
        $firstBrace = strpos($content, '{');
        $lastBrace = strrpos($content, '}');

        if ($firstBrace !== false && $lastBrace !== false) {
            $content = substr($content, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        // Reemplazar caracteres problemáticos
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/\n+/', ' ', $content);

        // Reparar JSON común
        if (method_exists($this, 'repairCommonJsonIssues')) {
            $content = $this->repairCommonJsonIssues($content);
        }

        return trim($content);
    }

    /**
     * Check if a course with the given topic exists for the current user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkCourse(Request $request)
    {
        $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $topic = $request->input('topic');

        // Search for a course with a similar topic (case insensitive, partial match)
        $course = Course::where('user_id', $user->id)
            ->where('title', 'like', '%'.$topic.'%')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($course) {
            return response()->json([
                'exists' => true,
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'created_at' => $course->created_at->toDateTimeString(),
                    'url' => route('courses.show', $course->id),
                ],
            ]);
        }

        return response()->json([
            'exists' => false,
        ]);
    }

    /**
     * Crear un nuevo curso personalizado
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        Log::info('CourseController::create called', [
            'method' => $request->method(),
            'data' => $request->all(),
            'user_id' => Auth::id(),
        ]);

        $request->validate([
            'course_topic' => 'required|string|max:255',
            'level' => 'required|in:beginner,intermediate,advanced',
            'sessions_count' => 'required|integer|min:1|max:3',
        ]);

        try {
            Log::info('Starting course generation with AI', [
                'topic' => $request->course_topic,
                'level' => $request->level,
                'sessions' => $request->sessions_count,
            ]);

            // Generar el curso con IA
            $courseData = $this->generateCourseWithAI($request->course_topic, $request->level, $request->sessions_count);

            if (! $courseData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar el contenido del curso con IA',
                ], 500);
            }

            // Crear el curso
            $course = Course::create([
                'user_id' => Auth::id(),
                'title' => $courseData['title'],
                'description' => $courseData['description'],
                'level' => $request->level,
                'topic' => $request->course_topic,
                'is_active' => true,
            ]);

            // Crear la estructura de directorios
            $this->createCourseDirectories(Auth::id(), $course->id);

            // Crear las sesiones
            foreach ($courseData['sessions'] as $index => $sessionData) {
                $session = CourseSession::create([
                    'course_id' => $course->id,
                    'title' => $sessionData['title'],
                    'content' => $sessionData['content'],
                    'session_order' => $index + 1,
                    'is_active' => true,
                    'practice_activity' => $sessionData['practice_activity'] ?? null,
                ]);

                // Crear progreso inicial para el usuario
                CourseProgress::create([
                    'user_id' => Auth::id(),
                    'course_id' => $course->id,
                    'session_id' => $session->id,
                    'completed' => false,
                ]);
            }

            Log::info('Curso creado exitosamente', [
                'course_id' => $course->id,
                'user_id' => Auth::id(),
                'sessions_count' => count($courseData['sessions']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Curso creado exitosamente. Puedes completar los recursos desde la vista del curso.',
                'course_id' => $course->id,
                'redirect_url' => route('courses.show', $course->id),
            ]);

        } catch (\Exception $e) {
            Log::error('Error creando curso: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'topic' => $request->course_topic,
                'level' => $request->level,
                'sessions' => $request->sessions_count,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: '.$e->getMessage(),
            ], 500);
        }
    }

    private function validateCourseCompleteness($course)
    {
        $validation = [
            'is_complete' => true,
            'missing_elements' => [],
            'sessions_status' => [],
        ];

        foreach ($course->sessions as $session) {
            $sessionStatus = [
                'session_id' => $session->id,
                'title' => $session->title,
                'has_content' => ! empty($session->content),
                'has_image' => false,
                'has_audio' => false,
                'has_activity' => false,
                'missing_elements' => [],
            ];

            // Validar contenido
            if (empty($session->content) || strlen($session->content) < 500) {
                $sessionStatus['missing_elements'][] = 'Contenido insuficiente (mínimo 500 caracteres)';
                $validation['is_complete'] = false;
            }

            // Validar imagen
            if (! empty($session->image_description)) {
                $userId = Auth::id();
                $imagePath = "courses/{$userId}/{$course->id}/img/session_{$session->id}.png";
                $sessionStatus['has_image'] = Storage::disk('public')->exists($imagePath);

                if (! $sessionStatus['has_image']) {
                    $sessionStatus['missing_elements'][] = 'Imagen no generada';
                    $validation['is_complete'] = false;
                }
            }

            // Validar audio
            if (! empty($session->audio_script)) {
                $userId = Auth::id();
                $audioPath = "courses/{$userId}/{$course->id}/audio/session_{$session->id}.mp3";
                $sessionStatus['has_audio'] = Storage::disk('public')->exists($audioPath);

                if (! $sessionStatus['has_audio']) {
                    $sessionStatus['missing_elements'][] = 'Audio no generado';
                    $validation['is_complete'] = false;
                }
            }

            // Validar actividad práctica
            if (! empty($session->practice_activity)) {
                $activity = json_decode($session->practice_activity, true);
                $sessionStatus['has_activity'] = isset($activity['question']) &&
                                                isset($activity['options']) &&
                                                isset($activity['correct_answer']) &&
                                                isset($activity['instructions']);

                if (! $sessionStatus['has_activity']) {
                    $sessionStatus['missing_elements'][] = 'Actividad práctica incompleta';
                    $validation['is_complete'] = false;
                }
            } else {
                $sessionStatus['missing_elements'][] = 'Sin actividad práctica';
                $validation['is_complete'] = false;
            }

            $validation['sessions_status'][] = $sessionStatus;
        }

        return $validation;
    }
}
