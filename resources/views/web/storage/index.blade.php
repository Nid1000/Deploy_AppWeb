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
            <div class="mt-10 w-full max-w-3xl rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-8 text-center shadow-xl shadow-emerald-900/8">
                <h3 class="text-xl font-semibold text-emerald-700">Archivo subido exitosamente</h3>
                <p class="mt-2 text-base leading-7 text-stone-700">
                    Tu archivo ha sido subido al bucket {{ $bucketName }}. Puedes descargarlo desde este enlace:
                </p>

                @if (!empty($uploaded['download_url']))
                    <a href="{{ $uploaded['download_url'] }}" download class="mt-5 inline-flex items-center gap-2 rounded-full bg-[var(--color-primary)] px-8 py-3 text-sm font-semibold uppercase text-white shadow-lg shadow-amber-200/40 transition hover:-translate-y-0.5 hover:bg-[var(--color-secondary)]">
                        Ver archivo
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 3v12" />
                            <path d="m7 10 5 5 5-5" />
                            <path d="M5 21h14" />
                        </svg>
                    </a>
                @endif
            </div>
        @endif
    </section>
@endsection
