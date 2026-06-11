@extends('layouts.storefront', ['title' => 'Recuperar contrasena'])

@section('content')
    <div class="auth-shell">
        <div class="mb-6">
            <p class="text-sm uppercase tracking-[0.25em] text-amber-700">Recuperacion</p>
            <h2 class="mt-2 text-2xl font-semibold text-stone-900">Olvidaste tu contrasena?</h2>
            <p class="mt-2 text-sm text-stone-600">
                Ingresa el correo de tu cuenta y te enviaremos un enlace seguro para crear una nueva contrasena.
            </p>
        </div>

        <form action="{{ route('web.password.email') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500" placeholder="tu@email.com">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-2xl bg-stone-900 px-4 py-3 font-semibold text-white">
                Enviar enlace
            </button>
        </form>

        <p class="mt-5 text-sm text-stone-600">
            <a href="{{ route('web.login') }}" class="font-semibold text-amber-700 underline underline-offset-4">
                Volver a iniciar sesion
            </a>
        </p>
    </div>
@endsection
