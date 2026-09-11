<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class LoginController extends Controller
{
    public function login()
    {
        return view('layout.login');
    }

    public function check_login(Request $request)
    {
        // Regenerar token de sesión para evitar problemas
        $request->session()->regenerate();

        try {
            $rules = [
                'email' => 'required|email:rfc,dns',
                'password' => 'required|min:6',
            ];
            if (config('app.dev') === '0') {
                $rules['recaptcha_token'] = 'required|string';
            }
            $validate = $request->validate($rules, [
                'email.required' => 'El usuario es necesario',
                'email.email' => 'El e-mail ingresado no es valido',
                'password.required' => 'La contraseña es necesaria',
                'password.min' => 'debe tener al menos 6 caracteres',
                'recaptcha_token.required' => 'No llego el recaptcha',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput($request->except('password'));
        } catch (\Exception $e) {
            \Log::error('Error en check_login: '.$e->getMessage());

            return redirect()->back()->with('error', 'Ha ocurrido un error. Por favor, intenta nuevamente.');
        }
        // Solo validar reCAPTCHA si DEV es 0
        if (config('app.dev') === '0') {
            $recaptchaResponse = $request->input('recaptcha_token');

            // Si no hay token de reCAPTCHA y estamos en producción
            if (empty($recaptchaResponse)) {
                return redirect()->back()->withErrors(['recaptcha' => 'No se recibió el token de reCAPTCHA.'])->withInput($request->except('password'));
            }

            try {
                // Verificar reCAPTCHA
                $recaptcha = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => config('services.recaptcha.secret'),
                    'response' => $recaptchaResponse,
                ]);

                // Decodificar la respuesta JSON
                $recaptchaResult = json_decode($recaptcha->body(), true);

                // Verificar si la validación fue exitosa
                if (! isset($recaptchaResult['success']) || ! $recaptchaResult['success'] ||
                    (isset($recaptchaResult['score']) && $recaptchaResult['score'] < 0.5)) {
                    return redirect()->back()->withErrors(['recaptcha' => 'Validación de reCAPTCHA fallida.'])->withInput($request->except('password'));
                }
            } catch (\Exception $e) {
                // Log del error para depuración
                \Log::error('Error en validación reCAPTCHA: '.$e->getMessage());

                return redirect()->back()->withErrors(['recaptcha' => 'Error al validar reCAPTCHA. Por favor, intenta nuevamente.'])->withInput($request->except('password'));
            }
        }
        // Si DEV es 1, no validamos reCAPTCHA

        // $input = $request->all();
        // dd($input);
        $remember = $request->boolean('remember');

        if (Auth::attempt(['email' => $request->input('email'), 'password' => $request->input('password')], $remember)) {

            return redirect()->route('idex_home')->with('success', 'Login exitoso!');
        } else {
            return redirect()->back()->with('error', 'Credenciales inválidas.');
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Regenera el token CSRF para evitar errores 419 (Page Expired)
     */
    public function refreshToken(Request $request)
    {
        $token = csrf_token();

        return response()->json(['token' => $token]);
    }

    /**
     * Guardar la edad del usuario
     */
    public function saveAge(Request $request)
    {
        try {
            $request->validate([
                'age' => 'required|integer|min:6|max:120',
            ]);

            $user = Auth::user();
            $user->age = $request->age;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Edad guardada correctamente',
                'age_category' => $user->getAgeCategory(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la edad: '.$e->getMessage(),
            ], 500);
        }
    }
}
