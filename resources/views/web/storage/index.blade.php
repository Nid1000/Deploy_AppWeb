@extends('layouts.storefront', ['title' => 'Storage'])

@section('content')
    @php($uploaded = session('gcs_uploaded'))

    <section class="page-hero">
        <div class="max-w-3xl">
            <span class="eyebrow">Google Cloud Storage</span>
            <h2 class="headline mt-4">Repositorio de archivos de Delicias</h2>
            <p class="subheadline mt-4">
                El almacenamiento privado esta conectado al bucket {{ $bucketName }} y puedes subir archivos desde aqui.
            </p>
        </div>
    </section>

    <section class="section-space grid gap-6 lg:grid-cols-[1fr_0.8fr]">
        <article class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                    <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 3v12" />
                        <path d="m7 10 5 5 5-5" />
                        <path d="M5 19h14" />
                    </svg>
                </div>
                <div>
                    <h3 class="section-title text-2xl">Subir archivo</h3>
                    <p class="subheadline mt-3">
                        Selecciona un archivo y se guardara en Google Cloud Storage con enlace temporal.
                    </p>
                </div>
            </div>

            <form action="/storage" method="POST" enctype="multipart/form-data" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label class="label" for="archivo">Archivo</label>
                    <input id="archivo" name="archivo" type="file" required class="input">
                    @error('archivo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-stone-500">Maximo 10 MB por archivo.</p>
                </div>
                <div>
                    <label class="label" for="prefijo">Carpeta en el bucket</label>
                    <input id="prefijo" name="prefijo" value="{{ old('prefijo', $uploadPrefix) }}" class="input" placeholder="uploads">
                    @error('prefijo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-stone-500">Ejemplo: uploads, productos o comprobantes.</p>
                </div>
                <button type="submit" class="btn btn-primary">Subir archivo</button>
            </form>
        </article>

        <aside class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
            <h3 class="section-title text-2xl">Storage activo</h3>
            <div class="mt-6 space-y-4 text-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Bucket</p>
                    <p class="mt-1 break-all text-base font-semibold text-stone-900">{{ $bucketName }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Prefijo por defecto</p>
                    <p class="mt-1 break-all text-base font-semibold text-stone-900">{{ $uploadPrefix }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">URL temporal</p>
                    <p class="mt-1 text-base font-semibold text-stone-900">{{ $signedUrlTtl }} minutos</p>
                </div>
            </div>

            @if ($uploaded)
                <div class="mt-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <h4 class="font-semibold text-emerald-900">Archivo subido</h4>
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
                            Abrir archivo
                        </a>
                    </div>
                </div>
            @else
                <p class="subheadline mt-8">
                    Despues de subir un archivo veras aqui el nombre del objeto y su enlace temporal.
                </p>
            @endif
        </aside>
    </section>
@endsection
