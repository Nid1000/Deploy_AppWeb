@extends('layouts.storefront', ['title' => 'Storage'])

@section('content')
    @php($uploaded = session('gcs_uploaded'))

    <section class="mx-auto flex min-h-[640px] max-w-5xl flex-col items-center justify-center rounded-[2rem] border border-amber-200/80 bg-white/92 px-6 py-16 text-stone-900 shadow-2xl shadow-amber-100/45 md:px-12">
        <div class="mx-auto max-w-3xl text-center">
            <span class="eyebrow">Google Cloud Storage</span>
            <h2 class="mt-6 font-['Poppins'] text-3xl font-semibold text-stone-950 md:text-4xl">
                Almacenamiento de Archivos (GCS)
            </h2>
            <p class="mt-6 text-base leading-7 text-stone-600 md:text-lg">
                Sube tus archivos de manera segura usando Google Cloud Storage.
            </p>
        </div>

        <div class="my-18 h-px w-full max-w-3xl bg-amber-200/80"></div>

        <form action="/storage" method="POST" enctype="multipart/form-data" class="w-full max-w-md text-center">
            @csrf
            <input type="hidden" name="prefijo" value="{{ old('prefijo', $uploadPrefix) }}">

            <label for="archivo" class="block text-lg font-semibold text-stone-950">
                Subir un archivo
            </label>
            <input
                id="archivo"
                name="archivo"
                type="file"
                required
                class="mt-5 block w-full rounded-2xl border border-amber-200 bg-white px-4 py-3 text-sm text-stone-700 shadow-sm shadow-amber-100/25 file:mr-3 file:rounded-xl file:border-0 file:bg-amber-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-secondary)]"
            >
            @error('archivo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('prefijo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

            <button type="submit" class="mt-5 w-full rounded-full bg-[var(--color-primary)] px-6 py-3 text-sm font-semibold uppercase text-white shadow-lg shadow-amber-200/40 transition hover:-translate-y-0.5 hover:bg-[var(--color-secondary)]">
                Subir archivo
            </button>
        </form>

        @if ($uploaded)
            <div class="mt-10 w-full max-w-2xl rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-left shadow-sm">
                <h3 class="font-semibold text-emerald-900">Archivo subido correctamente</h3>
                <div class="mt-4 space-y-3 text-sm text-stone-700">
                    <p>
                        <span class="font-semibold text-stone-950">Objeto:</span>
                        <span class="break-all">{{ $uploaded['object'] }}</span>
                    </p>
                    <p>
                        <span class="font-semibold text-stone-950">URI:</span>
                        <span class="break-all">{{ $uploaded['gs_uri'] }}</span>
                    </p>
                    @if (!empty($uploaded['signed_url']))
                        <a href="{{ $uploaded['signed_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-full bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white">
                            Abrir archivo
                        </a>
                    @else
                        <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-800">
                            {{ $uploaded['signed_url_error'] ?? 'El archivo fue subido, pero no se pudo crear el enlace temporal.' }}
                        </p>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
