@extends('layouts.storefront', ['title' => 'Registro'])

@section('content')
    @php
        $googleProfile = $googleProfile ?? null;
        $verifiedEmail = $verifiedEmail ?? null;
        $prefillNombre = old('nombre', data_get($googleProfile, 'nombre', ''));
        $prefillApellido = old('apellido', data_get($googleProfile, 'apellido', ''));
        $prefillEmail = old('email', data_get($googleProfile, 'email', ''));
        $isVerified = $verifiedEmail !== null && $verifiedEmail === $prefillEmail;
    @endphp

    <div class="register-shell">
        <header class="register-header">
            <div>
                <p class="eyebrow">Cuenta nueva</p>
                <h2 class="mt-2 text-3xl font-semibold text-stone-950">Crea tu cuenta</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-stone-600">
                    Verifica tu correo y completa tus datos para realizar pedidos y recibir tus comprobantes.
                </p>
            </div>
        </header>

        @if ($errors->any())
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                Revisa los campos marcados y vuelve a intentar.
            </div>
        @endif

        <section class="register-section register-section-highlight">
            <div class="register-section-heading">
                <span class="register-step-number">{{ $isVerified ? '✓' : '1' }}</span>
                <div>
                    <h3>Verifica tu correo</h3>
                    <p>Elige Google o recibe un codigo de 6 digitos.</p>
                </div>
            </div>

            <div class="register-google-row">
                <div>
                    <p class="text-sm font-semibold text-stone-900">Registro rapido con Google</p>
                    <p class="mt-1 text-xs text-stone-500">Usaremos el Gmail verificado de tu cuenta.</p>
                </div>
                <a href="{{ route('web.register.google.redirect') }}" class="register-google-button">Continuar con Google</a>
            </div>

            <div class="register-divider"><span>o usa tu correo</span></div>

            <div class="grid gap-4 lg:grid-cols-[1fr_auto_0.7fr_auto] lg:items-end">
                <form action="{{ route('web.register.email.send-code') }}" method="POST" class="contents">
                    @csrf
                    <div>
                        <label for="verification_email" class="label">Correo electronico</label>
                        <input id="verification_email" name="email" type="email" value="{{ $prefillEmail }}" required class="input" @disabled($isVerified) placeholder="tu@email.com">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="register-secondary-button @if($isVerified) opacity-60 @endif" @disabled($isVerified)>
                        {{ $isVerified ? 'Correo verificado' : 'Enviar codigo' }}
                    </button>
                </form>

                <form action="{{ route('web.register.email.verify-code') }}" method="POST" class="contents">
                    @csrf
                    <div>
                        <label for="verification_code" class="label">Codigo recibido</label>
                        <input id="verification_code" name="verification_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="input text-center tracking-[0.3em]" placeholder="123456" @disabled($isVerified)>
                        @error('verification_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <input type="hidden" name="email" value="{{ $prefillEmail }}">
                    <button type="submit" class="register-primary-button @if($isVerified) opacity-60 @endif" @disabled($isVerified)>
                        Validar
                    </button>
                </form>
            </div>

            @if ($isVerified)
                <p class="register-verified-message">
                    Correo verificado: <strong>{{ $verifiedEmail }}</strong>. Ya puedes completar el registro.
                </p>
            @else
                <p class="register-pending-message">Primero verifica el correo para habilitar el boton Crear cuenta.</p>
            @endif
        </section>

        <form action="{{ route('web.register.submit') }}" method="POST" class="space-y-6" data-password-form>
            @csrf

            <section class="register-section">
                <div class="register-section-heading">
                    <span class="register-step-number">2</span>
                    <div>
                        <h3>Datos personales</h3>
                        <p>Informacion basica para identificar tu cuenta.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="nombre" class="label">Nombre</label>
                        <input id="nombre" name="nombre" type="text" value="{{ $prefillNombre }}" required class="input" autocomplete="given-name">
                        @error('nombre')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="apellido" class="label">Apellido</label>
                        <input id="apellido" name="apellido" type="text" value="{{ $prefillApellido }}" required class="input" autocomplete="family-name">
                        @error('apellido')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="label">Correo verificado</label>
                        <input id="email" name="email" type="email" value="{{ $prefillEmail }}" required class="input" @readonly($isVerified) autocomplete="email">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="telefono" class="label">Telefono <span class="text-stone-400">(opcional)</span></label>
                        <input id="telefono" name="telefono" type="tel" value="{{ old('telefono') }}" class="input" placeholder="987654321" autocomplete="tel">
                        @error('telefono')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="register-section">
                <div class="register-section-heading">
                    <span class="register-step-number">3</span>
                    <div>
                        <h3>Seguridad</h3>
                        <p>Crea una contrasena segura para proteger tu cuenta.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_1fr_0.8fr] lg:items-start">
                    <div>
                        <label for="password" class="label">Contraseña</label>
                        <input id="password" name="password" type="password" required class="input" placeholder="Ejemplo: Panaderia1" data-password-input autocomplete="new-password">
                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="label">Confirmar contraseña</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="input" autocomplete="new-password">
                    </div>
                    <div class="register-password-rules">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-stone-500">Debe incluir</p>
                        <ul class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs lg:grid-cols-1">
                            <li data-rule="length">6 caracteres</li>
                            <li data-rule="upper">Una mayuscula</li>
                            <li data-rule="lower">Una minuscula</li>
                            <li data-rule="digit">Un numero</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="register-section">
                <div class="register-section-heading">
                    <span class="register-step-number">4</span>
                    <div>
                        <h3>Direccion de entrega</h3>
                        <p>La usaremos como direccion principal para tus pedidos.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-[1fr_0.55fr]">
                    <div>
                        <label for="direccion" class="label">Direccion</label>
                        <input id="direccion" name="direccion" type="text" value="{{ old('direccion') }}" required class="input" placeholder="Jiron, avenida o calle" autocomplete="street-address">
                        @error('direccion')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="numero_casa" class="label">Numero de casa</label>
                        <input id="numero_casa" name="numero_casa" type="text" value="{{ old('numero_casa') }}" required class="input" placeholder="123">
                        @error('numero_casa')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="distrito" class="label">Distrito</label>
                        <select id="distrito" name="distrito" required class="input">
                            <option value="">Selecciona un distrito</option>
                            @foreach ($distritos as $distrito)
                                <option value="{{ $distrito->nombre }}" @selected(old('distrito') === $distrito->nombre)>{{ $distrito->nombre }}</option>
                            @endforeach
                        </select>
                        @error('distrito')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <div class="register-submit-row">
                <p class="text-sm text-stone-500">
                    {{ $isVerified ? 'Todo listo. Revisa tus datos antes de continuar.' : 'Verifica el correo para habilitar el registro.' }}
                </p>
                <button type="submit" class="register-submit-button @if(!$isVerified) opacity-60 @endif" @disabled(!$isVerified)>
                    Crear cuenta
                </button>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-stone-600">
            Ya tienes cuenta?
            <a href="{{ route('web.login') }}" class="font-semibold text-amber-700 underline underline-offset-4">Inicia sesion</a>
        </p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const input = document.querySelector('[data-password-input]');
            if (!input) return;

            const rules = {
                length: document.querySelector('[data-rule="length"]'),
                upper: document.querySelector('[data-rule="upper"]'),
                lower: document.querySelector('[data-rule="lower"]'),
                digit: document.querySelector('[data-rule="digit"]'),
            };

            const syncRules = () => {
                const value = input.value;
                const checks = {
                    length: value.length >= 6,
                    upper: /[A-Z]/.test(value),
                    lower: /[a-z]/.test(value),
                    digit: /\d/.test(value),
                };

                Object.entries(rules).forEach(([key, element]) => {
                    if (!element) return;
                    const passed = checks[key];
                    element.classList.toggle('text-emerald-700', passed);
                    element.classList.toggle('font-semibold', passed);
                    element.classList.toggle('text-stone-700', !passed);
                });
            };

            input.addEventListener('input', syncRules);
            syncRules();
        });
    </script>
@endsection
