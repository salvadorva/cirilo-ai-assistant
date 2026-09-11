<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('role')->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = \App\Models\Role::all();

        return view('admin.users.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'age' => 'nullable|integer|min:6|max:120',
            'role_id' => 'required|exists:roles,id',
            'prompt' => 'nullable|string',
            'ai_provider' => 'required|in:openai,grok',
            'daily_image_limit' => 'nullable|integer|min:0|max:100',
        ]);
        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);
        $user->role_id = $validated['role_id'];
        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        // Opcional: podrías mostrar detalles del usuario o redirigir al index
        return redirect()->route('admin.users.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $roles = Role::all();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:6',
            'age' => 'nullable|integer|min:6|max:120',
            'role_id' => 'required|exists:roles,id',
            'prompt' => 'nullable|string',
            'ai_provider' => 'required|in:openai,grok',
            'daily_image_limit' => 'nullable|integer|min:0|max:100',
        ]);

        // Manejo de contraseña
        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // El email no se actualiza (es readonly en la vista)

        $user->update($validated);
        $user->role_id = $validated['role_id'];
        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Usuario eliminado correctamente');
    }

    /**
     * Toggle email notifications for a user.
     */
    public function toggleEmailNotifications(User $user)
    {
        $user->email_notifications_enabled = ! $user->email_notifications_enabled;
        $user->save();

        $status = $user->email_notifications_enabled ? 'activadas' : 'desactivadas';

        return redirect()->route('admin.users.index')
            ->with('success', "Notificaciones por correo {$status} para {$user->name}");
    }
}
