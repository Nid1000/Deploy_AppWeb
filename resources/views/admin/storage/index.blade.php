@extends('layouts.admin', ['title' => 'Google Cloud Storage'])

@section('content')
    @php($uploaded = session('gcs_uploaded'))

    <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <article class="admin-card">
            <h3 class="text-2xl font-semibold text-stone-950">Subir archivo al bucket</h3>
            <p class="mt-2 text-sm text-stone-500">
                Bucket configurado: <span class="font-semibold text-stone-800">{{ $bucketName }}</span>
            </p>

            <form action="{{ route('web.admin.storage.store') }}" method="POST" enctype="multipart/form-data" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="archivo">Archivo</label>
                    <input id="archivo" name="archivo" type="file" required class="input">
                    @error('archivo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="prefijo">Prefijo en GCS</label>
                    <input id="prefijo" name="prefijo" value="{{ old('prefijo', $uploadPrefix) }}" class="input" placeholder="uploads">
                    @error('prefijo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-stone-500">Ejemplo: productos, comprobantes o uploads/temporales.</p>
                </div>
                <button class="btn btn-primary">Subir a Google Cloud</button>
            </form>
        </article>

        <article class="admin-card">
            <h3 class="text-2xl font-semibold text-stone-950">Configuracion activa</h3>
            <dl class="mt-6 space-y-4 text-sm">
                <div>
                    <dt class="font-semibold text-stone-900">Bucket</dt>
                    <dd class="mt-1 text-stone-600">{{ $bucketName }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-stone-900">Prefijo por defecto</dt>
                    <dd class="mt-1 text-stone-600">{{ $uploadPrefix }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-stone-900">Duracion de URL firmada</dt>
                    <dd class="mt-1 text-stone-600">{{ $signedUrlTtl }} minutos</dd>
                </div>
            </dl>

            @if ($uploaded)
                <div class="mt-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <h4 class="font-semibold text-emerald-900">Archivo disponible</h4>
                    <div class="mt-4 space-y-3 text-sm">
                        <p>
                            <span class="font-semibold text-stone-900">Objeto:</span>
                            <span class="break-all text-stone-700">{{ $uploaded['object'] }}</span>
                        </p>
                        <p>
                            <span class="font-semibold text-stone-900">URI:</span>
                            <span class="break-all text-stone-700">{{ $uploaded['gs_uri'] }}</span>
                        </p>
                        <a href="{{ $uploaded['signed_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                            Abrir URL firmada
                        </a>
                    </div>
                </div>
            @else
                <p class="mt-8 text-sm text-stone-500">
                    Cuando subas un archivo, aqui veras el nombre del objeto y una URL temporal para abrirlo sin hacer publico el bucket.
                </p>
            @endif
        </article>
    </section>
@endsection
