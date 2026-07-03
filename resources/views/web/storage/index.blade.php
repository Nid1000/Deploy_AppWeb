@extends('layouts.storefront', ['title' => 'Storage'])

@section('content')
    <section class="page-hero">
        <div class="max-w-3xl">
            <span class="eyebrow">Google Cloud Storage</span>
            <h2 class="headline mt-4">Repositorio de archivos de Delicias</h2>
            <p class="subheadline mt-4">
                El almacenamiento privado esta conectado al bucket {{ $bucketName }}.
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
                    <h3 class="section-title text-2xl">Storage activo</h3>
                    <p class="subheadline mt-3">
                        Los archivos administrativos se guardan con el prefijo {{ $uploadPrefix }} y se consultan mediante enlaces temporales.
                    </p>
                </div>
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Bucket</p>
                    <p class="mt-2 break-all text-lg font-semibold text-stone-900">{{ $bucketName }}</p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Prefijo</p>
                    <p class="mt-2 break-all text-lg font-semibold text-stone-900">{{ $uploadPrefix }}</p>
                </div>
            </div>
        </article>

        <aside class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
            <h3 class="section-title text-2xl">Panel seguro</h3>
            <p class="subheadline mt-3">
                La carga de archivos esta reservada para administradores.
            </p>
            <a href="{{ route('web.admin.storage.index') }}" class="btn btn-primary mt-6">
                Abrir panel de Storage
            </a>
        </aside>
    </section>
@endsection
