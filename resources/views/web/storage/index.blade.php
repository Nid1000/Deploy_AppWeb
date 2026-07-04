@extends('layouts.storefront', ['title' => 'Storage'])

@section('content')
    @php($uploaded = session('gcs_uploaded'))

    <section class="mx-auto flex min-h-[640px] max-w-5xl flex-col items-center justify-center rounded-[2rem] border border-stone-800 bg-black px-6 py-16 text-white shadow-2xl shadow-black/20 md:px-12">
        <div class="mx-auto max-w-3xl text-center">
            <h2 class="font-['Poppins'] text-3xl font-semibold md:text-4xl">
                Almacenamiento de Archivos (GCS)
            </h2>
            <p class="mt-8 text-base text-white md:text-lg">
                Sube tus archivos de manera segura usando Google Cloud Storage.
            </p>
        </div>

        <div class="my-20 h-px w-full max-w-3xl bg-white/10"></div>

        <form action="/storage" method="POST" enctype="multipart/form-data" class="w-full max-w-md text-center">
            @csrf
            <input type="hidden" name="prefijo" value="{{ old('prefijo', $uploadPrefix) }}">

            <label for="archivo" class="block text-lg font-semibold text-white">
                Subir un archivo
            </label>
            <input
                id="archivo"
                name="archivo"
                type="file"
                required
                class="mt-5 block w-full rounded-md border border-white/70 bg-black px-3 py-2 text-sm text-white file:mr-3 file:rounded-sm file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-black"
            >
            @error('archivo')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
            @error('prefijo')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror

            <button type="submit" class="mt-4 w-full rounded-full bg-[#4b3260] px-6 py-3 text-sm font-semibold uppercase text-white transition hover:bg-[#5d3d76]">
                Subir archivo
            </button>
        </form>

        @if ($uploaded)
            <div class="mt-10 w-full max-w-2xl rounded-2xl border border-emerald-400/40 bg-emerald-400/10 p-5 text-left">
                <h3 class="font-semibold text-emerald-100">Archivo subido correctamente</h3>
                <div class="mt-4 space-y-3 text-sm text-white/80">
                    <p>
                        <span class="font-semibold text-white">Objeto:</span>
                        <span class="break-all">{{ $uploaded['object'] }}</span>
                    </p>
                    <p>
                        <span class="font-semibold text-white">URI:</span>
                        <span class="break-all">{{ $uploaded['gs_uri'] }}</span>
                    </p>
                    <a href="{{ $uploaded['signed_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-full bg-white px-5 py-2 text-sm font-semibold text-black">
                        Abrir archivo
                    </a>
                </div>
            </div>
        @endif
    </section>
@endsection
