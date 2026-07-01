@extends('layouts.admin', ['title' => 'Detalle de pedido'])

@section('content')
    <section class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <article class="admin-card">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-stone-500">Pedido #{{ $order->id }}</p>
                    <h3 class="mt-2 text-3xl font-semibold text-stone-950">S/ {{ number_format((float) $order->total, 2) }}</h3>
                </div>
                <span class="badge badge-accent">{{ ucfirst($order->estado) }}</span>
            </div>

            <div class="mt-6 space-y-4">
                @foreach ($details as $detail)
                    <div class="rounded-[1.5rem] border border-stone-200 p-4">
                        <div class="flex items-center gap-4">
                            <img src="{{ $detail->producto_imagen_url }}" alt="{{ $detail->producto_nombre }}" class="h-16 w-16 rounded-2xl object-cover">
                            <div class="flex-1">
                                <p class="font-semibold text-stone-900">{{ $detail->producto_nombre }}</p>
                                <p class="text-sm text-stone-500">Cantidad: {{ $detail->cantidad }}</p>
                            </div>
                            <p class="font-semibold text-stone-900">S/ {{ number_format($detail->subtotal, 2) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="space-y-6">
            <div class="admin-card">
                <h4 class="text-xl font-semibold text-stone-950">Pago</h4>
                <div class="mt-4 rounded-2xl border border-stone-200 bg-white p-4">
                    <p class="font-semibold text-stone-900">
                        {{ match($order->metodo_pago ?? null) {
                            'yape' => 'Yape',
                            'tarjeta' => 'Tarjeta',
                            'contra_entrega' => 'Efectivo contra entrega',
                            default => 'Por confirmar',
                        } }}
                    </p>
                    <p class="mt-1 text-sm text-stone-500">Estado: {{ ucfirst($order->estado_pago ?? 'pendiente') }}</p>
                    @if (!empty($order->pago_referencia ?? null))
                        <p class="mt-2 text-sm text-stone-600">{{ $order->pago_referencia }}</p>
                    @endif
                </div>
            </div>

            <div class="admin-card">
                <h4 class="text-xl font-semibold text-stone-950">Actualizar estado</h4>
                <form action="{{ route('web.admin.orders.state', $order->id) }}" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <select name="estado" class="input">
                        @foreach (['pendiente', 'listo', 'entregado', 'cancelado'] as $estado)
                            <option value="{{ $estado }}" @selected($order->estado === $estado)>{{ ucfirst($estado) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary w-full justify-center">Guardar estado</button>
                </form>
            </div>

            <div class="admin-card">
                <h4 class="text-xl font-semibold text-stone-950">Entrega y reparto</h4>
                <form action="{{ route('web.admin.orders.delivery', $order->id) }}" method="POST" class="mt-4 space-y-3">
                    @csrf
                    <label class="label" for="fecha_entrega">Fecha de entrega</label>
                    <input id="fecha_entrega" name="fecha_entrega" type="date" value="{{ $order->fecha_entrega }}" class="input">
                    <button class="btn btn-outline-secondary w-full justify-center">Actualizar fecha</button>
                </form>

                <form action="{{ route('web.admin.orders.shipping', $order->id) }}" method="POST" class="mt-5 space-y-3">
                    @csrf
                    <div class="rounded-2xl border border-stone-200 bg-white p-4">
                        <p class="font-semibold text-stone-900">Registro de conductor</p>
                        <p class="mt-1 text-sm text-stone-500">Guarda el responsable y el control de salida/regreso del reparto.</p>
                    </div>
                    <div>
                        <label class="label" for="conductor">Conductor</label>
                        <input id="conductor" name="conductor" value="{{ $order->conductor ?? '' }}" class="input" placeholder="Nombre del conductor">
                    </div>
                    <div>
                        <label class="label" for="conductor_dni">DNI del conductor</label>
                        <input id="conductor_dni" name="conductor_dni" value="{{ $order->conductor_dni ?? '' }}" class="input" inputmode="numeric" maxlength="8" placeholder="DNI">
                    </div>
                    <div>
                        <label class="label" for="vehiculo">Vehiculo</label>
                        <input id="vehiculo" name="vehiculo" value="{{ $order->vehiculo ?? '' }}" class="input" placeholder="Moto, auto o placa">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="salida_reparto_at">Hora de salida</label>
                            <input id="salida_reparto_at" name="salida_reparto_at" type="datetime-local" value="{{ ($order->salida_reparto_at ?? null) ? str_replace(' ', 'T', substr((string) $order->salida_reparto_at, 0, 16)) : '' }}" class="input">
                        </div>
                        <div>
                            <label class="label" for="regreso_reparto_at">Hora de regreso</label>
                            <input id="regreso_reparto_at" name="regreso_reparto_at" type="datetime-local" value="{{ ($order->regreso_reparto_at ?? null) ? str_replace(' ', 'T', substr((string) $order->regreso_reparto_at, 0, 16)) : '' }}" class="input">
                        </div>
                    </div>
                    <button class="btn btn-primary w-full justify-center">Guardar reparto</button>
                </form>
            </div>
        </article>
    </section>
@endsection
