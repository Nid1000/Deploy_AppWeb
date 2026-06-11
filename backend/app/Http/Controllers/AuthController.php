<?php

namespace App\Http\Controllers;

use App\Services\JwtService;
use App\Support\PasswordRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function passwordLooksHashed(string $hash): bool
    {
        $h = trim($hash);
        return str_starts_with($h, '$2y$') || str_starts_with($h, '$2a$') || str_starts_with($h, '$2b$');
    }

    private function verifyPassword(string $plain, string $stored): bool
    {
        $stored = (string) $stored;
        if ($stored === '') {
            return false;
        }

        // Primero intenta con el hasher de Laravel (si el hash es compatible).
        try {
            return Hash::check($plain, $stored);
        } catch (\Throwable) {
            // Compatibilidad: evita 500 si el hash no coincide con el algoritmo esperado.
        }

        // Fallback: verifica hashes bcrypt legacy ($2a$, $2b$) con password_verify.
        if ($this->passwordLooksHashed($stored)) {
            return password_verify($plain, $stored);
        }

        // Último recurso (migración): si en la BD quedó texto plano, permite login y luego se debería rehashear.
        return hash_equals($stored, $plain);
    }

    public function register(Request $request)
    {
        try {
            $data = $request->validate([
                'nombre' => ['required', 'string', 'min:2', 'max:191'],
                'apellido' => ['required', 'string', 'min:2', 'max:191'],
                'email' => ['required', 'email', 'max:191'],
                'password' => [
                    'required',
                    'string',
                    'min:6',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                ],
                'telefono' => ['nullable', 'string', 'max:20'],
                'direccion' => ['nullable', 'string'],
                'distrito' => ['nullable', 'string', 'min:2', 'max:120'],
                'numero_casa' => ['nullable', 'string', 'max:20'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $existing = DB::table('usuarios')->where('email', $data['email'])->first();
        if ($existing) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Email ya registrado',
                'message' => 'Ya existe una cuenta con este email',
            ], 400);
        }

        $id = DB::table('usuarios')->insertGetId([
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'telefono' => $data['telefono'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'distrito' => $data['distrito'] ?? null,
            'numero_casa' => $data['numero_casa'] ?? null,
            'activo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('usuarios')->select([
            'id', 'nombre', 'apellido', 'email', 'telefono', 'direccion', 'distrito', 'numero_casa',
        ])->where('id', $id)->first();

        $token = app(JwtService::class)->sign([
            'id' => $id,
            'email' => $data['email'],
            'tipo' => 'usuario',
        ]);

        return response()->json([
            'statusCode' => 201,
            'message' => 'Usuario registrado exitosamente',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => ['required', 'email', 'max:191'],
                'password' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $user = DB::table('usuarios')->where('email', $data['email'])->first();
        if (!$user) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Credenciales inválidas',
                'message' => 'Email o contraseña incorrectos',
            ], 401);
        }
        if (!(bool) $user->activo) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Cuenta inactiva',
                'message' => 'Tu cuenta ha sido desactivada',
            ], 401);
        }
        if (!$this->verifyPassword((string) $data['password'], (string) $user->password)) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Credenciales inválidas',
                'message' => 'Email o contraseña incorrectos',
            ], 401);
        }

        $token = app(JwtService::class)->sign([
            'id' => (int) $user->id,
            'email' => (string) $user->email,
            'tipo' => 'usuario',
        ]);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Login exitoso',
            'user' => [
                'id' => (int) $user->id,
                'nombre' => (string) $user->nombre,
                'apellido' => (string) $user->apellido,
                'email' => (string) $user->email,
                'telefono' => $user->telefono,
                'direccion' => $user->direccion,
                'distrito' => $user->distrito,
                'numero_casa' => $user->numero_casa,
            ],
            'token' => $token,
        ], 200);
    }

    public function googleLogin(Request $request)
    {
        try {
            $data = $request->validate([
                'id_token' => ['required', 'string', 'max:4096'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos invalidos',
                'message' => 'El token de Google es obligatorio',
                'details' => $e->errors(),
            ], 400);
        }

        $clientId = trim((string) config('services.google.client_id'));
        if ($clientId === '') {
            return response()->json([
                'statusCode' => 503,
                'error' => 'Google no configurado',
                'message' => 'GOOGLE_CLIENT_ID no esta configurado en el backend',
            ], 503);
        }

        try {
            $googleResponse = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $data['id_token'],
                ]);
        } catch (\Throwable) {
            return response()->json([
                'statusCode' => 503,
                'error' => 'Google no disponible',
                'message' => 'No se pudo validar el token con Google',
            ], 503);
        }

        if (!$googleResponse->successful()) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Token invalido',
                'message' => 'El token de Google es invalido o expiro',
            ], 401);
        }

        $profile = $googleResponse->json();
        $audience = (string) data_get($profile, 'aud', '');
        $issuer = (string) data_get($profile, 'iss', '');
        $email = mb_strtolower(trim((string) data_get($profile, 'email', '')));
        $emailVerified = filter_var(
            data_get($profile, 'email_verified', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (
            !hash_equals($clientId, $audience)
            || !in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)
            || !$emailVerified
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Token invalido',
                'message' => 'La identidad de Google no pudo ser verificada',
            ], 401);
        }

        $user = DB::table('usuarios')->whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Cuenta no encontrada',
                'message' => 'No existe una cuenta registrada con este correo. Registrate primero.',
            ], 404);
        }

        if (!(bool) $user->activo) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Cuenta inactiva',
                'message' => 'Tu cuenta ha sido desactivada',
            ], 401);
        }

        $token = app(JwtService::class)->sign([
            'id' => (int) $user->id,
            'email' => (string) $user->email,
            'tipo' => 'usuario',
        ]);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Login con Google exitoso',
            'user' => [
                'id' => (int) $user->id,
                'nombre' => (string) $user->nombre,
                'apellido' => (string) $user->apellido,
                'email' => (string) $user->email,
                'telefono' => $user->telefono,
                'direccion' => $user->direccion,
                'distrito' => $user->distrito,
                'numero_casa' => $user->numero_casa,
            ],
            'token' => $token,
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => ['required', 'email', 'max:191'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos invalidos',
                'message' => 'Ingresa un correo valido',
                'details' => $e->errors(),
            ], 400);
        }

        $email = mb_strtolower(trim((string) $data['email']));
        $user = DB::table('usuarios')
            ->select(['id', 'nombre', 'email', 'password', 'activo'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user && (bool) $user->activo) {
            $ttlMinutes = max(5, (int) config('services.password_reset.ttl_minutes', 30));
            $token = app(JwtService::class)->sign([
                'purpose' => 'password_reset',
                'user_id' => (int) $user->id,
                'password_fingerprint' => hash('sha256', (string) $user->password),
            ], $ttlMinutes * 60);

            $frontendUrl = rtrim((string) config('services.frontend.url'), '/');
            $resetUrl = $frontendUrl . '/password/reset?' . http_build_query(['token' => $token]);

            $this->sendPasswordResetEmail(
                (string) $user->email,
                (string) $user->nombre,
                $resetUrl,
                $ttlMinutes
            );
        }

        return response()->json([
            'statusCode' => 200,
            'message' => 'Si el correo pertenece a una cuenta activa, recibiras un enlace para restablecer tu contrasena.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        try {
            $data = $request->validate([
                'token' => ['required', 'string', 'max:4096'],
                'password' => ['required', 'confirmed', PasswordRules::userPassword()],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 422,
                'error' => 'Datos invalidos',
                'message' => 'Revisa la nueva contrasena',
                'details' => $e->errors(),
            ], 422);
        }

        try {
            $payload = app(JwtService::class)->verify($data['token']);
        } catch (\Throwable) {
            return $this->invalidPasswordResetToken();
        }

        if (($payload['purpose'] ?? null) !== 'password_reset') {
            return $this->invalidPasswordResetToken();
        }

        $user = DB::table('usuarios')
            ->select(['id', 'password', 'activo'])
            ->where('id', (int) ($payload['user_id'] ?? 0))
            ->first();

        $expectedFingerprint = hash('sha256', (string) ($user->password ?? ''));
        $receivedFingerprint = (string) ($payload['password_fingerprint'] ?? '');

        if (
            !$user
            || !(bool) $user->activo
            || $receivedFingerprint === ''
            || !hash_equals($expectedFingerprint, $receivedFingerprint)
        ) {
            return $this->invalidPasswordResetToken();
        }

        DB::table('usuarios')
            ->where('id', (int) $user->id)
            ->update([
                'password' => Hash::make($data['password']),
                'updated_at' => now(),
            ]);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Tu contrasena fue actualizada. Ya puedes iniciar sesion.',
        ]);
    }

    public function adminLogin(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => ['required', 'email', 'max:191'],
                'password' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $admin = DB::table('administradores')->where('email', $data['email'])->first();
        if (!$admin) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Credenciales inválidas',
                'message' => 'Email o contraseña incorrectos',
            ], 401);
        }
        if (!(bool) $admin->activo) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Cuenta inactiva',
                'message' => 'Tu cuenta de administrador ha sido desactivada',
            ], 401);
        }
        if (!$this->verifyPassword((string) $data['password'], (string) $admin->password)) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Credenciales inválidas',
                'message' => 'Email o contraseña incorrectos',
            ], 401);
        }

        $token = app(JwtService::class)->sign([
            'id' => (int) $admin->id,
            'email' => (string) $admin->email,
            'tipo' => 'admin',
        ]);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Login de administrador exitoso',
            'admin' => [
                'id' => (int) $admin->id,
                'nombre' => (string) $admin->nombre,
                'email' => (string) $admin->email,
                'rol' => (string) $admin->rol,
            ],
            'token' => $token,
        ], 200);
    }

    public function verify(Request $request)
    {
        $payload = $request->attributes->get('user');
        if (!is_array($payload)) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Token inválido',
                'message' => 'Token inválido o expirado',
            ], 401);
        }

        $tipo = $payload['tipo'] ?? null;
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        if ($tipo === 'admin') {
            $admin = DB::table('administradores')
                ->select(['id', 'nombre', 'email', 'rol', 'activo'])
                ->where('id', $id)
                ->first();

            if (!$admin || !(bool) $admin->activo) {
                return response()->json([
                    'statusCode' => 401,
                    'error' => 'Token inválido',
                    'message' => 'Administrador no encontrado o inactivo',
                ], 401);
            }

            return response()->json([
                'statusCode' => 200,
                'message' => 'Token válido',
                'tipo' => 'admin',
                'admin' => [
                    'id' => (int) $admin->id,
                    'nombre' => (string) $admin->nombre,
                    'email' => (string) $admin->email,
                    'rol' => (string) $admin->rol,
                ],
            ], 200);
        }

        $user = DB::table('usuarios')
            ->select([
                'id', 'nombre', 'apellido', 'email', 'telefono', 'direccion', 'distrito', 'numero_casa', 'activo',
            ])
            ->where('id', $id)
            ->first();

        if (!$user || !(bool) $user->activo) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Token inválido',
                'message' => 'Usuario no encontrado o inactivo',
            ], 401);
        }

        return response()->json([
            'statusCode' => 200,
            'message' => 'Token válido',
            'tipo' => 'usuario',
            'user' => [
                'id' => (int) $user->id,
                'nombre' => (string) $user->nombre,
                'apellido' => (string) $user->apellido,
                'email' => (string) $user->email,
                'telefono' => $user->telefono,
                'direccion' => $user->direccion,
                'distrito' => $user->distrito,
                'numero_casa' => $user->numero_casa,
            ],
        ], 200);
    }

    private function invalidPasswordResetToken()
    {
        return response()->json([
            'statusCode' => 422,
            'error' => 'Enlace invalido',
            'message' => 'El enlace es invalido, ya fue utilizado o ha vencido.',
        ], 422);
    }

    private function sendPasswordResetEmail(
        string $email,
        string $name,
        string $resetUrl,
        int $ttlMinutes
    ): void {
        $apiKey = trim((string) config('services.resend.key'));
        $fromAddress = trim((string) config('mail.from.address'));
        $fromName = trim((string) config('mail.from.name', 'Delicias del centro'));

        if ($apiKey === '' || $fromAddress === '' || str_contains($fromAddress, 'example.com')) {
            Log::error('Password reset email is not configured.', [
                'has_resend_key' => $apiKey !== '',
                'from_address' => $fromAddress,
            ]);

            return;
        }

        $safeName = htmlspecialchars($name !== '' ? $name : 'cliente', ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
            <div style="font-family:Arial,sans-serif;color:#292524;line-height:1.6">
                <h2>Restablece tu contrasena</h2>
                <p>Hola {$safeName}, recibimos una solicitud para cambiar la contrasena de tu cuenta.</p>
                <p><a href="{$safeUrl}" style="display:inline-block;padding:12px 20px;background:#1c1917;color:#fff;text-decoration:none;border-radius:10px">Crear nueva contrasena</a></p>
                <p>Este enlace vence en {$ttlMinutes} minutos y solo puede utilizarse una vez.</p>
                <p>Si no solicitaste el cambio, ignora este correo.</p>
            </div>
            HTML;

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->post('https://api.resend.com/emails', [
                    'from' => $fromName . ' <' . $fromAddress . '>',
                    'to' => [$email],
                    'subject' => 'Restablece tu contrasena',
                    'html' => $html,
                ]);

            if (!$response->successful()) {
                Log::error('Resend rejected the password reset email.', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Could not send password reset email.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
