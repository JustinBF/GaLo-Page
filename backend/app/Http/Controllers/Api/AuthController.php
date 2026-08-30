<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 8;

    private const DECAY_SECONDS = 60;

    /**
     * Login con usuario/contraseña de cuenta compartida. Devuelve un token
     * Sanctum por dispositivo, de modo que varias personas pueden estar
     * dentro de la misma cuenta simultaneamente.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string'],
            // Etiqueta libre para saber quién actúa bajo la cuenta compartida.
            'actor_label' => ['nullable', 'string', 'max:60'],
        ]);

        $throttleKey = 'login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'username' => 'Demasiados intentos. Espera '
                    .RateLimiter::availableIn($throttleKey).' segundos.',
            ])->status(429);
        }

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'username' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $label = $data['actor_label'] ?? null;
        $token = $user->createToken($label ?? 'sesion', ['*'])->plainTextToken;

        if ($user->isAdmin()) {
            AuditLog::create([
                'user_id' => $user->id,
                'actor_label' => $label,
                'action' => 'login',
                'ip' => $request->ip(),
            ]);
        }

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user, $label),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user, $user->currentAccessToken()->name ?? null),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    /**
     * @return array{id:int, username:string, role:string, is_admin:bool, actor_label:?string}
     */
    private function userPayload(User $user, ?string $label): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'actor_label' => $label,
        ];
    }
}
