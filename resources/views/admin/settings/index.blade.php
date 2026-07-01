@extends('layouts.admin', ['title' => 'Configuracion'])

@section('content')
    <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <article class="admin-card">
            <h3 class="text-2xl font-semibold text-stone-950">Preferencias del frontend</h3>
            <form action="{{ route('web.admin.settings.update') }}" method="POST" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="moneda">Moneda</label>
                    <input id="moneda" name="moneda" value="{{ old('moneda', $settings['moneda'] ?? 'S/') }}" class="input">
                </div>
                <div>
                    <label class="label" for="prefijo">Prefijo</label>
                    <input id="prefijo" name="prefijo" value="{{ old('prefijo', $settings['prefijo'] ?? 'DEL') }}" class="input">
                </div>
                <div>
                    <label class="label" for="branding">Branding</label>
                    <input id="branding" name="branding" value="{{ old('branding', $settings['branding'] ?? 'Delicias') }}" class="input">
                </div>
                <button class="btn btn-primary">Guardar configuración</button>
            </form>
        </article>

        <article class="admin-card">
            <h3 class="text-2xl font-semibold text-stone-950">Enviar notificacion</h3>
            <form action="{{ route('web.admin.settings.notify') }}" method="POST" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="title">Titulo</label>
                    <input id="title" name="title" value="{{ old('title') }}" class="input">
                </div>
                <div>
                    <label class="label" for="message">Mensaje</label>
                    <textarea id="message" name="message" rows="5" class="input min-h-36">{{ old('message') }}</textarea>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="label" for="audience">Audiencia</label>
                        <select id="audience" name="audience" class="input">
                            <option value="both">Web y mobile</option>
                            <option value="web">Solo web</option>
                            <option value="mobile">Solo mobile</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="route">Ruta</label>
                        <input id="route" name="route" value="{{ old('route', 'store') }}" class="input">
                    </div>
                    <div>
                        <label class="label" for="targetId">Target ID</label>
                        <input id="targetId" name="targetId" value="{{ old('targetId') }}" class="input">
                    </div>
                </div>
                <div>
                    <label class="label" for="userId">Usuario especifico</label>
                    <input id="userId" name="userId" value="{{ old('userId') }}" class="input" placeholder="Opcional">
                </div>
                <button class="btn btn-primary">Enviar notificacion</button>
            </form>
        </article>
    </section>
@endsection
