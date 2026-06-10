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

    <div class="mx-auto mt-8 max-w-3xl rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-xl shadow-amber-100/40">
        <div class="mb-6">
            <p class="text-sm uppercase tracking-[0.25em] text-amber-700">Cuenta nueva</p>
            <h2 class="mt-2 text-2xl font-semibold text-stone-900">Registrarse</h2>
            <p class="mt-2 text-sm text-stone-600">Primero validamos que el correo sea real y luego terminas el alta de la cuenta.</p>
        </div>

        @if ($errors->any())
            <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                Revisa los campos marcados y vuelve a intentar.
            </div>
        @endif

        <div class="mb-6 rounded-2xl border border-stone-200 bg-stone-50 p-4">
            <p class="text-sm font-semibold text-stone-900">Crear cuenta con correo real</p>
            <p class="mt-1 text-sm text-stone-600">Puedes validar tu email con un codigo enviado por correo o traer un Gmail verificado desde Google.</p>
            <a href="{{ route('web.register.google.redirect') }}" class="mt-4 inline-flex w-full items-center justify-center rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm font-semibold text-stone-800 transition hover:border-stone-400 hover:bg-stone-100 md:w-auto">
                Continuar con Google
            </a>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 md:grid-cols-2">
            <form action="{{ route('web.register.email.send-code') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label for="verification_email" class="mb-1 block text-sm font-medium text-stone-700">Tu correo real</label>
                    <input id="verification_email" name="email" type="email" value="{{ $prefillEmail }}" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500" @disabled($isVerified) placeholder="tu@email.com">
                </div>
                <button type="submit" class="w-full rounded-2xl border border-amber-300 bg-white px-4 py-3 font-semibold text-amber-800 @if($isVerified) opacity-60 @endif" @disabled($isVerified)>
                    {{ $isVerified ? 'Correo ya verificado' : 'Enviar codigo por correo' }}
                </button>
            </form>

            <form action="{{ route('web.register.email.verify-code') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label for="verification_code" class="mb-1 block text-sm font-medium text-stone-700">Codigo de verificacion</label>
                    <input id="verification_code" name="verification_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500" placeholder="123456" @disabled($isVerified)>
                </div>
                <input type="hidden" name="email" value="{{ $prefillEmail }}">
                <button type="submit" class="w-full rounded-2xl bg-stone-900 px-4 py-3 font-semibold text-white @if($isVerified) opacity-60 @endif" @disabled($isVerified)>
                    {{ $isVerified ? 'Correo confirmado' : 'Validar codigo' }}
                </button>
            </form>

            <div class="md:col-span-2">
                @if ($isVerified)
                    <p class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        El correo <strong>{{ $verifiedEmail }}</strong> ya quedo verificado y listo para crear la cuenta.
                    </p>
                @else
                    <p class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm text-stone-600">
                        El registro final solo se habilita para correos verificados.
                    </p>
                @endif
            </div>
        </div>

        <form action="{{ route('web.register.submit') }}" method="POST" class="grid grid-cols-1 gap-4 md:grid-cols-2" data-password-form>
            @csrf
            <div>
                <label for="nombre" class="mb-1 block text-sm font-medium text-stone-700">Nombre</label>
                <input id="nombre" name="nombre" type="text" value="{{ $prefillNombre }}" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500">
                @error('nombre')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="apellido" class="mb-1 block text-sm font-medium text-stone-700">Apellido</label>
                <input id="apellido" name="apellido" type="text" value="{{ $prefillApellido }}" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500">
                @error('apellido')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">Email</label>
                <input id="email" name="email" type="email" value="{{ $prefillEmail }}" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500" @readonly($isVerified)>
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label for="password" class="mb-1 block text-sm font-medium text-stone-700">Contrasena</label>
                <input id="password" name="password" type="password" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500" placeholder="Ejemplo: Panaderia1" data-password-input>
                <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-stone-700">
                    <p class="font-medium">Debe cumplir:</p>
                    <ul class="mt-2 space-y-1">
                        <li data-rule="length">Minimo 6 caracteres</li>
                        <li data-rule="upper">Al menos una mayuscula</li>
                        <li data-rule="lower">Al menos una minuscula</li>
                        <li data-rule="digit">Al menos un numero</li>
                    </ul>
                </div>
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-stone-700">Confirmar contrasena</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500">
            </div>
            <div>
                <label for="telefono" class="mb-1 block text-sm font-medium text-stone-700">Telefono</label>
                <input id="telefono" name="telefono" type="tel" value="{{ old('telefono') }}" class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500" placeholder="987654321">
                @error('telefono')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="numero_casa" class="mb-1 block text-sm font-medium text-stone-700">Numero de casa</label>
                <input id="numero_casa" name="numero_casa" type="text" value="{{ old('numero_casa') }}" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500">
                @error('numero_casa')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label for="direccion" class="mb-1 block text-sm font-medium text-stone-700">Direccion</label>
                <input id="direccion" name="direccion" type="text" value="{{ old('direccion') }}" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500">
                @error('direccion')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label for="distrito" class="mb-1 block text-sm font-medium text-stone-700">Distrito</label>
                <select id="distrito" name="distrito" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500">
                    <option value="">Selecciona un distrito</option>
                    @foreach ($distritos as $distrito)
                        <option value="{{ $distrito->nombre }}" @selected(old('distrito') === $distrito->nombre)>{{ $distrito->nombre }}</option>
                    @endforeach
                </select>
                @error('distrito')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="w-full rounded-2xl bg-stone-900 px-4 py-3 font-semibold text-white @if(!$isVerified) opacity-60 @endif" @disabled(!$isVerified)>Crear cuenta</button>
            </div>
        </form>

        <p class="mt-5 text-sm text-stone-600">
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
