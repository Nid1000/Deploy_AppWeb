@extends('layouts.storefront', ['title' => 'Pedido #' . $order->id])

@section('content')
    <section class="page-hero">
        <div class="max-w-3xl">
            <a href="{{ route('web.orders') }}" class="eyebrow">Volver al historial</a>
            <h2 class="headline mt-4">Pedido #{{ $order->id }}</h2>
            <p class="subheadline mt-4">
                Estado actual: {{ ucfirst($order->estado) }}. Total: S/ {{ number_format((float) $order->total, 2) }}.
            </p>
        </div>
    </section>

    @include('web.profile.partials.account-nav')

    <section class="mt-8 grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
        <div class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
            <h3 class="text-2xl font-semibold text-stone-900">Detalle del pedido</h3>
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-[1.5rem] border border-amber-100 bg-white p-4">
                    <p class="text-sm text-stone-500">Fecha</p>
                    <p class="mt-2 font-medium text-stone-900">{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d/m/Y H:i') }}</p>
                </div>
                <div class="rounded-[1.5rem] border border-amber-100 bg-white p-4">
                    <p class="text-sm text-stone-500">Entrega</p>
                    <p class="mt-2 font-medium text-stone-900">{{ !empty($order->fecha_entrega ?? null) ? \Illuminate\Support\Carbon::parse($order->fecha_entrega)->format('d/m/Y') : 'Por confirmar' }}</p>
                </div>
                <div class="rounded-[1.5rem] border border-amber-100 bg-white p-4 md:col-span-2">
                    <p class="text-sm text-stone-500">Dirección</p>
                    <p class="mt-2 font-medium text-stone-900">
                        {{ $order->direccion_entrega ?: 'Sin dirección registrada' }}
                        @if ($order->numero_casa_entrega)
                            , {{ $order->numero_casa_entrega }}
                        @endif
                        @if ($order->distrito_entrega)
                            , {{ $order->distrito_entrega }}
                        @endif
                    </p>
                </div>
                <div class="rounded-[1.5rem] border border-amber-100 bg-white p-4 md:col-span-2">
                    <p class="text-sm text-stone-500">Notas</p>
                    <p class="mt-2 font-medium text-stone-900">{{ $order->notas ?: 'Sin notas adicionales.' }}</p>
                </div>
                <div class="rounded-[1.5rem] border border-amber-100 bg-white p-4 md:col-span-2">
                    <p class="text-sm text-stone-500">Pago</p>
                    <p class="mt-2 font-medium text-stone-900">
                        {{ match($order->metodo_pago ?? null) {
                            'yape' => 'Yape',
                            'tarjeta' => 'Tarjeta',
                            'contra_entrega' => 'Efectivo contra entrega',
                            default => 'Por confirmar',
                        } }}
                        @if (!empty($order->estado_pago ?? null))
                            <span class="text-sm text-stone-500">({{ ucfirst($order->estado_pago) }})</span>
                        @endif
                    </p>
                    @if (!empty($order->pago_referencia ?? null))
                        <p class="mt-1 text-sm text-stone-500">{{ $order->pago_referencia }}</p>
                    @endif
                </div>
            </div>

            <div class="mt-8 space-y-4">
                @foreach ($details as $detail)
                    <article class="rounded-[1.5rem] border border-amber-100 bg-white p-4 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            @if ($detail->producto_imagen_url)
                                <img src="{{ $detail->producto_imagen_url }}" alt="{{ $detail->producto_nombre }}" class="h-20 w-20 rounded-2xl object-cover">
                            @endif
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-stone-900">{{ $detail->producto_nombre ?: 'Producto' }}</h4>
                                <p class="mt-1 text-sm text-stone-500">{{ $detail->cantidad }} unidad(es) x S/ {{ number_format($detail->precio_unitario, 2) }}</p>
                            </div>
                            <p class="text-lg font-semibold text-[var(--color-secondary)]">S/ {{ number_format($detail->subtotal, 2) }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            @if ($receipt)
                <section class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
                    <p class="eyebrow">Comprobante</p>
                    <h3 class="mt-3 text-3xl font-semibold text-stone-900">{{ strtoupper($receipt->tipo) }} {{ $receipt->numero_formateado }}</h3>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ $receipt->pdf_url }}" target="_blank" class="btn btn-primary">Abrir PDF</a>
                        <a href="{{ $receipt->xml_url }}" target="_blank" class="btn btn-outline-secondary">Descargar XML</a>
                        <a href="{{ $receipt->img_url }}" target="_blank" class="btn btn-outline-secondary">Ver imagen</a>
                    </div>
                </section>
            @endif

            <section class="rounded-[2rem] border border-dashed border-amber-300 bg-amber-50 p-6">
                <p class="text-sm font-semibold text-stone-900">Siguiente paso</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    Puedes volver al menú para seguir agregando productos o regresar al historial para revisar pedidos anteriores.
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('web.products') }}" class="btn btn-outline-secondary">Seguir comprando</a>
                    <a href="{{ route('web.orders') }}" class="btn btn-outline-secondary">Volver al historial</a>
                </div>
            </section>
        </div>
    </section>
@endsection
