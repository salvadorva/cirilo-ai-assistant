<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{
    /**
     * Mostrar el perfil del usuario
     */
    public function show()
    {
        $user = Auth::user();
        $progress = $user->gameProgress;

        // Estadísticas adicionales
        $stats = [
            'total_sessions' => $progress->total_sessions ?? 0,
            'total_xp' => $progress->total_xp ?? 0,
            'level' => $progress->level ?? 1,
            'current_streak' => $progress->current_streak ?? 0,
            'longest_streak' => $progress->longest_streak ?? 0,
            'member_since' => $user->created_at->diffForHumans(),
        ];

        return view('profile.show', compact('user', 'progress', 'stats'));
    }

    /**
     * Actualizar información del perfil
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'age' => 'nullable|integer|min:5|max:120',
            'bio' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->age = $request->age;

        // Si existe campo bio en la tabla users
        if ($request->has('bio')) {
            $user->bio = $request->bio;
        }

        $user->save();

        return redirect()->route('profile')
            ->with('success', '¡Perfil actualizado correctamente!');
    }

    /**
     * Actualizar avatar del usuario
     */
    public function updateAvatar(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        // Eliminar avatar anterior si existe
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Guardar nuevo avatar
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
            $user->save();
        }

        return redirect()->route('profile')
            ->with('success', '¡Avatar actualizado correctamente!');
    }
}
