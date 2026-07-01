@extends('layouts.storefront', ['title' => 'Nueva contraseña'])

@section('content')
    <div class="auth-shell">
        <div class="mb-6">
            <p class="text-sm uppercase tracking-[0.25em] text-amber-700">Seguridad</p>
            <h2 class="mt-2 text-2xl font-semibold text-stone-900">Crea una nueva contraseña</h2>
            <p class="mt-2 text-sm text-stone-600">
                Debe tener al menos 6 caracteres, una mayúscula, una minúscula y un número.
            </p>
        </div>

        @if ($token === '')
            <div class="flash-error">
                El enlace de recuperación no es válido. Solicita uno nuevo.
            </div>
            <a href="{{ route('web.password.forgot') }}" class="mt-5 inline-flex font-semibold text-amber-700 underline underline-offset-4">
                Solicitar otro enlace
            </a>
        @else
            <form action="{{ route('web.password.update') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-stone-700">Nueva contraseña</label>
                    <input id="password" name="password" type="password" required autofocus class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium text-stone-700">Confirmar contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 outline-none transition focus:border-amber-500">
                </div>

                <button type="submit" class="w-full rounded-2xl bg-stone-900 px-4 py-3 font-semibold text-white">
                    Guardar nueva contraseña
                </button>
            </form>
        @endif
    </div>
@endsection
