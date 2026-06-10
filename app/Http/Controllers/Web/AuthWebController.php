<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\RegistroCodigoVerificacionMail;
use App\Services\BackendApiClient;
use App\Support\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthWebController extends Controller
{
    private const EMAIL_VERIFICATION_SESSION_KEY = 'web_registration_email_verification';
    private const GOOGLE_REGISTRATION_SESSION_KEY = 'web_registration_google_profile';
    private const GOOGLE_STATE_SESSION_KEY = 'web_google_oauth_state';
    private const DEFAULT_DISTRICTS = [
        ['id' => 1, 'nombre' => 'Huancayo'],
        ['id' => 2, 'nombre' => 'El Tambo'],
        ['id' => 3, 'nombre' => 'Chilca'],
        ['id' => 4, 'nombre' => 'Carhuacallanga'],
        ['id' => 5, 'nombre' => 'Cullhuas'],
        ['id' => 6, 'nombre' => 'Chacapampa'],
        ['id' => 7, 'nombre' => 'Chicche'],
        ['id' => 8, 'nombre' => 'Chongos Alto'],
        ['id' => 9, 'nombre' => 'Chupuro'],
        ['id' => 10, 'nombre' => 'Colca'],
        ['id' => 11, 'nombre' => 'Huacrapuquio'],
        ['id' => 12, 'nombre' => 'Hualhuas'],
        ['id' => 13, 'nombre' => 'Huancan'],
        ['id' => 14, 'nombre' => 'Huasicancha'],
        ['id' => 15, 'nombre' => 'Huayucachi'],
        ['id' => 16, 'nombre' => 'Ingenio'],
        ['id' => 17, 'nombre' => 'Pariahuanca'],
        ['id' => 18, 'nombre' => 'Pilcomayo'],
        ['id' => 19, 'nombre' => 'Pucara'],
        ['id' => 20, 'nombre' => 'Quichuay'],
        ['id' => 21, 'nombre' => 'Quilcas'],
        ['id' => 22, 'nombre' => 'Santo Domingo de Acobamba'],
        ['id' => 23, 'nombre' => 'Sano'],
        ['id' => 24, 'nombre' => 'Sapallanga'],
        ['id' => 25, 'nombre' => 'Sicaya'],
        ['id' => 26, 'nombre' => 'Viques'],
        ['id' => 27, 'nombre' => 'San Agustin de Cajas'],
    ];

    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('web_user')) {
            return redirect()->route('web.home');
        }

        return view('web.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'Ingresa un email valido.',
            'password.required' => 'La contrasena es obligatoria.',
        ]);

        $response = $this->api->post('auth/login', $credentials);
        if (!$response->successful()) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['email' => 'Email o contrasena incorrectos.']);
        }
        $payload = $response->json();
        $user = (array) data_get($payload, 'user', []);

        $sessionUser = [
            'id' => (int) ($user['id'] ?? 0),
            'nombre' => (string) ($user['nombre'] ?? ''),
            'apellido' => (string) ($user['apellido'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'telefono' => $user['telefono'] ?? null,
            'direccion' => $user['direccion'] ?? null,
            'distrito' => $user['distrito'] ?? null,
            'numero_casa' => $user['numero_casa'] ?? null,
        ];

        $request->session()->put([
            'web_user' => $sessionUser,
            'auth_token' => (string) data_get($payload, 'token', ''),
            'auth_tipo' => 'usuario',
        ]);

        $request->session()->regenerate();

        return redirect()->route('web.home')->with('success', 'Bienvenido de nuevo.');
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('web_user')) {
            return redirect()->route('web.home');
        }

        $districtsResponse = $this->api->get('usuarios/distritos-huancayo');
        $districts = $this->mapDistricts($this->api->okData($districtsResponse, 'distritos', []));
        if ($districts->isEmpty()) {
            $districts = $this->mapDistricts(self::DEFAULT_DISTRICTS);
        }

        return view('web.auth.register', [
            'distritos' => $districts,
            'googleProfile' => $request->session()->get(self::GOOGLE_REGISTRATION_SESSION_KEY),
            'verifiedEmail' => $this->verifiedRegistrationEmail($request),
        ]);
    }

    public function sendRegistrationCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
        ], [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'Ingresa un email valido.',
        ]);

        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(15);

        Mail::to($data['email'])->send(new RegistroCodigoVerificacionMail($code, $expiresAt));

        $request->session()->put(self::EMAIL_VERIFICATION_SESSION_KEY, [
            'email' => $data['email'],
            'code' => hash('sha256', $code),
            'expires_at' => $expiresAt->toIso8601String(),
            'verified_at' => null,
            'source' => 'email',
        ]);

        return back()
            ->withInput($request->except('_token'))
            ->with('success', 'Te enviamos un codigo de verificacion a tu correo.');
    }

    public function verifyRegistrationCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'verification_code' => ['required', 'digits:6'],
        ], [
            'verification_code.required' => 'Ingresa el codigo que recibiste por correo.',
            'verification_code.digits' => 'El codigo debe tener 6 digitos.',
        ]);

        $verification = $request->session()->get(self::EMAIL_VERIFICATION_SESSION_KEY);

        if (!is_array($verification) || ($verification['email'] ?? null) !== $data['email']) {
            return back()
                ->withInput($request->except('_token'))
                ->withErrors(['email' => 'Primero solicita un codigo para este correo.']);
        }

        $expiresAt = Carbon::parse((string) ($verification['expires_at'] ?? now()->toIso8601String()));
        if ($expiresAt->isPast()) {
            $request->session()->forget(self::EMAIL_VERIFICATION_SESSION_KEY);

            return back()
                ->withInput($request->except('_token'))
                ->withErrors(['verification_code' => 'El codigo vencio. Solicita uno nuevo.']);
        }

        if (!hash_equals((string) ($verification['code'] ?? ''), hash('sha256', $data['verification_code']))) {
            return back()
                ->withInput($request->except('_token'))
                ->withErrors(['verification_code' => 'El codigo ingresado no es correcto.']);
        }

        $verification['verified_at'] = now()->toIso8601String();
        $request->session()->put(self::EMAIL_VERIFICATION_SESSION_KEY, $verification);

        return back()
            ->withInput($request->except('_token'))
            ->with('success', 'Correo verificado correctamente. Ya puedes crear tu cuenta.');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:191'],
            'apellido' => ['required', 'string', 'min:2', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'confirmed', PasswordRules::userPassword()],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['required', 'string'],
            'distrito' => ['required', 'string', 'min:2', 'max:120'],
            'numero_casa' => ['required', 'string', 'max:20'],
        ], [
            'password.confirmed' => 'La confirmacion de contrasena no coincide.',
        ]);

        if (!$this->emailIsVerifiedForRegistration($request, $data['email'])) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Verifica primero tu correo o continua con Google antes de crear la cuenta.']);
        }

        $response = $this->api->post('auth/register', [
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido'],
            'email' => $data['email'],
            'password' => $data['password'],
            'telefono' => $data['telefono'] ?? null,
            'direccion' => $data['direccion'],
            'distrito' => $data['distrito'],
            'numero_casa' => $data['numero_casa'],
        ]);

        if (!$response->successful()) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => $this->api->errorMessage($response, 'No se pudo crear la cuenta.')]);
        }
        $payload = $response->json();
        $user = (array) data_get($payload, 'user', []);

        $sessionUser = [
            'id' => (int) ($user['id'] ?? 0),
            'nombre' => (string) ($user['nombre'] ?? $data['nombre']),
            'apellido' => (string) ($user['apellido'] ?? $data['apellido']),
            'email' => (string) ($user['email'] ?? $data['email']),
            'telefono' => $user['telefono'] ?? ($data['telefono'] ?? null),
            'direccion' => $user['direccion'] ?? $data['direccion'],
            'distrito' => $user['distrito'] ?? $data['distrito'],
            'numero_casa' => $user['numero_casa'] ?? $data['numero_casa'],
        ];

        $request->session()->put([
            'web_user' => $sessionUser,
            'auth_token' => (string) data_get($payload, 'token', ''),
            'auth_tipo' => 'usuario',
        ]);

        $request->session()->regenerate();
        $this->clearRegistrationVerificationState($request);

        return redirect()->route('web.home')->with('success', 'Tu cuenta fue creada correctamente.');
    }

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $clientId = trim((string) config('services.google.client_id'));
        $redirectUri = trim((string) config('services.google.redirect'));

        if ($clientId === '' || $redirectUri === '') {
            return redirect()
                ->route('web.register')
                ->with('error', 'Google no esta configurado todavia. Completa las variables GOOGLE_CLIENT_ID y GOOGLE_REDIRECT_URI.');
        }

        $state = Str::random(40);
        $request->session()->put(self::GOOGLE_STATE_SESSION_KEY, $state);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull(self::GOOGLE_STATE_SESSION_KEY, '');
        $receivedState = (string) $request->query('state', '');

        if ($expectedState === '' || !hash_equals($expectedState, $receivedState)) {
            return redirect()
                ->route('web.register')
                ->withErrors(['email' => 'No se pudo validar la sesion de Google. Intenta nuevamente.']);
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('web.register')
                ->withErrors(['email' => 'Google cancelo el acceso o no autorizo los permisos solicitados.']);
        }

        try {
            $tokenResponse = Http::asForm()
                ->timeout(20)
                ->post('https://oauth2.googleapis.com/token', [
                    'code' => (string) $request->query('code', ''),
                    'client_id' => (string) config('services.google.client_id'),
                    'client_secret' => (string) config('services.google.client_secret'),
                    'redirect_uri' => (string) config('services.google.redirect'),
                    'grant_type' => 'authorization_code',
                ])
                ->throw();

            $accessToken = (string) $tokenResponse->json('access_token', '');
            if ($accessToken === '') {
                return redirect()
                    ->route('web.register')
                    ->withErrors(['email' => 'Google no devolvio un token de acceso valido.']);
            }

            $profile = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(20)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')
                ->throw()
                ->json();
        } catch (RequestException) {
            return redirect()
                ->route('web.register')
                ->withErrors(['email' => 'No se pudo completar la validacion con Google en este momento.']);
        }

        $email = (string) data_get($profile, 'email', '');
        $emailVerified = (bool) data_get($profile, 'email_verified', false);

        if ($email === '' || !$emailVerified) {
            return redirect()
                ->route('web.register')
                ->withErrors(['email' => 'La cuenta de Google no devolvio un correo verificado.']);
        }

        $request->session()->put(self::GOOGLE_REGISTRATION_SESSION_KEY, [
            'email' => $email,
            'nombre' => (string) (data_get($profile, 'given_name') ?: data_get($profile, 'name', '')),
            'apellido' => (string) data_get($profile, 'family_name', ''),
            'avatar' => (string) data_get($profile, 'picture', ''),
            'verified_at' => now()->toIso8601String(),
            'source' => 'google',
        ]);

        $request->session()->put(self::EMAIL_VERIFICATION_SESSION_KEY, [
            'email' => $email,
            'code' => null,
            'expires_at' => null,
            'verified_at' => now()->toIso8601String(),
            'source' => 'google',
        ]);

        return redirect()
            ->route('web.register')
            ->with('success', 'Tu correo fue validado con Google. Completa los datos faltantes para crear tu cuenta.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['web_user', 'auth_token', 'auth_tipo']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('web.login')->with('success', 'Sesion cerrada correctamente.');
    }

    private function mapDistricts(mixed $districts): \Illuminate\Support\Collection
    {
        return collect($districts)->map(fn ($district) => is_array($district) ? (object) $district : $district)->values();
    }

    private function emailIsVerifiedForRegistration(Request $request, string $email): bool
    {
        return $this->verifiedRegistrationEmail($request) === $email;
    }

    private function verifiedRegistrationEmail(Request $request): ?string
    {
        $verification = $request->session()->get(self::EMAIL_VERIFICATION_SESSION_KEY);
        if (!is_array($verification)) {
            return null;
        }

        $verifiedAt = $verification['verified_at'] ?? null;
        $email = $verification['email'] ?? null;

        return is_string($verifiedAt) && is_string($email) && $email !== '' ? $email : null;
    }

    private function clearRegistrationVerificationState(Request $request): void
    {
        $request->session()->forget([
            self::EMAIL_VERIFICATION_SESSION_KEY,
            self::GOOGLE_REGISTRATION_SESSION_KEY,
            self::GOOGLE_STATE_SESSION_KEY,
        ]);
    }
}
