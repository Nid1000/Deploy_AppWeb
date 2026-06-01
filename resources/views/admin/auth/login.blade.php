@extends('layouts.storefront', ['title' => 'Login administrador'])

@section('content')
    <section class="admin-login-shell mx-auto max-w-3xl">
        <div class="admin-warning-banner">
            No estas autenticado como administrador.
            <a href="#admin-login-form">Inicia sesion.</a>
        </div>

        <div class="admin-login-card">
            <h3 class="admin-login-title">Login de administrador</h3>

            <form id="admin-login-form" action="{{ route('web.admin.login.submit') }}" method="POST" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="email" class="label">Correo electronico</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required class="input admin-input">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="label">Contrasena</label>
                    <input id="password" name="password" type="password" required class="input admin-input">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Entrar</button>
            </form>
        </div>
    </section>
@endsection
