<?php

namespace App\Http\Controllers;

use App\Models\UserProfileFact;
use App\Services\NextcloudCalendarService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserSettingsController extends Controller
{
    /**
     * Mostrar la página de configuración
     */
    public function show()
    {
        $user = Auth::user();
        $progress = $user->gameProgress;

        return view('settings.show', compact('user', 'progress'));
    }

    /**
     * Actualizar preferencias generales
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email_notifications_enabled' => 'nullable|boolean',
            'agenda_reminders_enabled'        => 'nullable|boolean',
            'telegram_chat_id'                => ['nullable', 'string', 'regex:/^\d{5,15}$/'],
            'telegram_notifications_enabled'  => 'nullable|boolean',
            'sound_effects' => 'nullable|boolean',
            'background_music' => 'nullable|boolean',
            'language' => 'nullable|in:es,en',
            'theme' => 'nullable|in:light,dark,auto',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        // Actualizar notificaciones en la base de datos
        $user->email_notifications_enabled      = $request->has('email_notifications_enabled');
        $user->agenda_reminders_enabled         = $request->has('agenda_reminders_enabled');
        $user->telegram_notifications_enabled   = $request->has('telegram_notifications_enabled');
        if ($request->filled('telegram_chat_id')) {
            $user->telegram_chat_id = $request->input('telegram_chat_id');
        } elseif ($request->has('telegram_chat_id') && $request->input('telegram_chat_id') === '') {
            $user->telegram_chat_id = null;
        }
        $user->save();

        // Guardar otras preferencias en sesión (sonido, música, idioma, tema)
        $settings = [
            'sound_effects' => $request->has('sound_effects'),
            'background_music' => $request->has('background_music'),
            'language' => $request->input('language', 'es'),
            'theme' => $request->input('theme', 'light'),
        ];

        session(['user_settings' => $settings]);

        return redirect()->route('settings')
            ->with('success', '¡Configuración actualizada correctamente!');
    }

    /**
     * Enviar mensaje de prueba a Telegram usando el chat_id indicado
     */
    public function testTelegram(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'telegram_chat_id' => ['required', 'string', 'regex:/^\d{5,15}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Chat ID inválido. Debe ser un número de 5 a 15 dígitos.'], 422);
        }

        $user = Auth::user();

        // Usar el chat_id del request (puede no estar guardado aún)
        $originalChatId = $user->telegram_chat_id;
        $originalEnabled = $user->telegram_notifications_enabled;

        $user->telegram_chat_id = $request->input('telegram_chat_id');
        $user->telegram_notifications_enabled = true;

        $sent = TelegramNotificationService::send(
            $user,
            "✅ *Conexión exitosa*\n\nHola, {$user->name}. Tu Telegram está correctamente vinculado con el asistente.\n\nA partir de ahora recibirás los recordatorios de agenda aquí.",
            ['type' => 'test']
        );

        // Restaurar valores originales (no guardamos aquí, solo probamos)
        $user->telegram_chat_id = $originalChatId;
        $user->telegram_notifications_enabled = $originalEnabled;

        if ($sent) {
            return response()->json(['success' => true, 'message' => '¡Mensaje enviado! Revisa tu Telegram.']);
        }

        return response()->json(['success' => false, 'message' => 'No se pudo enviar. Verifica el Chat ID o la configuración del webhook.'], 500);
    }

    /**
     * Retorna todos los hechos del perfil del usuario agrupados por categoría.
     */
    public function getMemory()
    {
        $userId = Auth::id();

        $facts = UserProfileFact::where('user_id', $userId)
            ->orderBy('category')
            ->orderBy('key')
            ->get(['id', 'category', 'key', 'value', 'confidence', 'last_mentioned_at']);

        $grouped = $facts->groupBy('category')->map(fn ($items) => $items->values());

        return response()->json([
            'success'         => true,
            'facts'           => $grouped,
            'category_labels' => UserProfileFact::CATEGORY_LABELS,
        ]);
    }

    /**
     * Elimina un hecho específico del perfil del usuario.
     */
    public function deleteFact(int $id)
    {
        $fact = UserProfileFact::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $fact->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Elimina toda la memoria del usuario (hechos del perfil).
     */
    public function clearMemory()
    {
        UserProfileFact::where('user_id', Auth::id())->delete();

        return response()->json(['success' => true, 'message' => 'Memoria eliminada correctamente.']);
    }

    /**
     * Actualizar contraseña
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        // Verificar contraseña actual
        if (! Hash::check($request->current_password, $user->password)) {
            return redirect()->back()
                ->withErrors(['current_password' => 'La contraseña actual es incorrecta']);
        }

        // Actualizar contraseña
        $user->password = Hash::make($request->new_password);
        $user->save();

        return redirect()->route('settings')
            ->with('success', '¡Contraseña actualizada correctamente!');
    }

    /**
     * Guardar configuración de Nextcloud del usuario.
     */
    public function updateNextcloud(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'nextcloud_url'      => 'nullable|url|max:255',
            'nextcloud_username' => 'nullable|string|max:100',
            'nextcloud_password' => 'nullable|string|max:255',
            'nextcloud_calendar' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user->nextcloud_url      = $request->filled('nextcloud_url') ? rtrim($request->nextcloud_url, '/') : null;
        $user->nextcloud_username = $request->filled('nextcloud_username') ? $request->nextcloud_username : null;
        $user->nextcloud_calendar = $request->filled('nextcloud_calendar') ? $request->nextcloud_calendar : 'personal';

        // Solo actualizar la contraseña si se proporcionó una nueva
        if ($request->filled('nextcloud_password')) {
            $user->nextcloud_password = $request->nextcloud_password;
        }

        $user->save();

        return redirect()->route('settings')
            ->with('success', '¡Configuración de Nextcloud guardada!');
    }

    /**
     * Probar la conexión Nextcloud del usuario autenticado.
     */
    public function testNextcloud()
    {
        $user = Auth::user();

        if (! $user->hasNextcloud()) {
            return response()->json(['success' => false, 'message' => 'Configura primero la URL, usuario y contraseña'], 422);
        }

        $result = (new NextcloudCalendarService($user))->testConnection();

        return response()->json($result);
    }
}
