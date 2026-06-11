@extends('layouts.storefront', ['title' => 'Ingresar'])

@section('content')
    <div class="auth-shell">
        <div class="mb-6">
            <p class="text-sm uppercase tracking-[0.25em] text-amber-700">Acceso</p>
            <h2 class="mt-2 text-2xl font-semibold text-stone-900">Iniciar sesion</h2>
            <p class="mt-2 text-sm text-stone-600">Ingresa a tu cuenta para revisar pedidos, carrito e informacion personal.</p>
        </div>

        <form action="{{ route('web.login.submit') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500" placeholder="tu@email.com">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <div class="mb-1 flex items-center justify-between gap-4">
                    <label for="password" class="block text-sm font-medium text-stone-700">Contrasena</label>
                    <a href="{{ route('web.password.forgot') }}" class="text-sm font-semibold text-amber-700 underline underline-offset-4">
                        Olvidaste tu contrasena?
                    </a>
                </div>
                <input id="password" name="password" type="password" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500" placeholder="••••••••">
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="w-full rounded-2xl bg-stone-900 px-4 py-3 font-semibold text-white">Ingresar</button>
        </form>

        <a href="{{ route('web.login.google.redirect') }}" class="mt-4 inline-flex w-full items-center justify-center rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm font-semibold text-stone-800 transition hover:border-stone-400 hover:bg-stone-100">
            Continuar con Google
        </a>

        <p class="mt-5 text-sm text-stone-600">
            No tienes cuenta?
            <a href="{{ route('web.register') }}" class="font-semibold text-amber-700 underline underline-offset-4">Registrate</a>
        </p>
    </div>
@endsection
