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

            <button type="submit" class="mt-5 w-full rounded-full bg-[var(--color-primary)] px-6 py-3 text-sm font-semibold uppercase text-white shadow-lg shadow-amber-200/40 transition hover:-translate-y-0.5 hover:bg-[var(--color-secondary)]">
                Subir archivo
            </button>
        </form>

        @if ($uploaded)
            <div class="mt-10 w-full max-w-3xl rounded-2xl border border-emerald-500 bg-stone-950 px-6 py-8 text-center text-white shadow-xl shadow-emerald-900/10">
                <h3 class="text-xl font-semibold text-emerald-400">¡Archivo subido exitosamente!</h3>

                @if (!empty($uploaded['signed_url']))
                    <p class="mt-2 text-base leading-7 text-white">
                        Tu archivo ha sido subido a Google Cloud Storage. Puedes verlo usando este enlace firmado válido por {{ $signedUrlTtl }} minutos:
                    </p>
                    <a href="{{ $uploaded['signed_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex items-center gap-2 rounded-full bg-white px-8 py-3 text-sm font-semibold uppercase text-stone-950 transition hover:bg-emerald-50">
                        Ver archivo
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 3h7v7" />
                            <path d="M10 14 21 3" />
                            <path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5" />
                        </svg>
                    </a>
                @else
                    <p class="mt-2 text-base leading-7 text-white">
                        Tu archivo ha sido subido al bucket {{ $bucketName }}.
                    </p>
                    <p class="mt-4 rounded-xl border border-amber-300/45 bg-amber-300/10 p-3 text-sm text-amber-100">
                        {{ $uploaded['signed_url_error'] ?? 'No se pudo crear el enlace temporal.' }}
                    </p>
                @endif
            </div>
        @endif
    </section>
@endsection
