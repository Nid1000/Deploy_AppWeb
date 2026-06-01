@extends('layouts.admin', ['title' => 'Comprobantes'])

@section('content')
    <section class="admin-card">
        <form method="GET" class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="label" for="tipo">Tipo</label>
                <select id="tipo" name="tipo" class="input">
                    <option value="">Todos</option>
                    <option value="boleta" @selected($filters['tipo'] === 'boleta')>Boleta</option>
                    <option value="factura" @selected($filters['tipo'] === 'factura')>Factura</option>
                </select>
            </div>
            <div>
                <label class="label" for="buscar">Buscar</label>
                <input id="buscar" name="buscar" value="{{ $filters['buscar'] }}" class="input" placeholder="Numero o cliente">
            </div>
            <div class="mt-8 flex gap-3">
                <button class="btn btn-primary">Filtrar</button>
                <a href="{{ route('web.admin.receipts.index') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="table-shell mt-6">
        <table class="table-ui">
            <thead>
                <tr>
                    <th>Numero</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Total</th>
                    <th>Archivos</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($receipts as $receipt)
                    <tr>
                        <td>
                            <p class="font-semibold text-stone-900">{{ $receipt->numero_formateado }}</p>
                            <p class="text-xs text-stone-500">{{ $receipt->created_at }}</p>
                        </td>
                        <td>{{ data_get($receipt, 'cliente.nombre', 'Cliente') }}</td>
                        <td><span class="badge badge-surface">{{ ucfirst($receipt->tipo) }}</span></td>
                        <td>S/ {{ number_format((float) $receipt->total, 2) }}</td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                @if ($receipt->pdf_url)
                                    <a href="{{ $receipt->pdf_url }}" target="_blank" class="btn btn-outline-secondary">PDF</a>
                                @endif
                                @if ($receipt->xml_url)
                                    <a href="{{ $receipt->xml_url }}" target="_blank" class="btn btn-outline-secondary">XML</a>
                                @endif
                                @if ($receipt->img_url)
                                    <a href="{{ $receipt->img_url }}" target="_blank" class="btn btn-outline-secondary">Vista</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-10 text-center text-stone-500">No hay comprobantes para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @include('shared.pagination', ['pagination' => $pagination])
@endsection
