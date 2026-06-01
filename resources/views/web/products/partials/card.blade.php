<article class="product-card">
    <a href="{{ route('web.products.show', $product->id) }}" class="product-image-wrap">
        <img src="{{ $product->imagen_url }}" alt="{{ $product->nombre }}" class="product-image">
        <div class="product-badges">
            @if ($product->destacado)
                <span class="badge badge-accent">Destacado</span>
            @endif
            @if ($product->stock_bajo)
                <span class="badge badge-warning">Quedan {{ $product->stock }}</span>
            @endif
            @if ($product->agotado)
                <span class="badge badge-danger">Agotado</span>
            @endif
        </div>
        @if ($product->categoria_nombre)
            <span class="badge badge-surface product-category">{{ $product->categoria_nombre }}</span>
        @endif
    </a>

    <div class="product-card-body">
        <div class="flex items-start justify-between gap-3">
            <h3 class="text-lg font-semibold leading-tight text-stone-900">{{ $product->nombre }}</h3>
            <span class="shrink-0 text-lg font-semibold text-[var(--color-secondary)]">S/ {{ number_format($product->precio, 2) }}</span>
        </div>
        @if ($product->descripcion)
            <p class="mt-2 line-clamp-2 text-sm text-stone-600">{{ $product->descripcion }}</p>
        @endif
        <div class="mt-3 flex items-center justify-between gap-3 text-xs uppercase tracking-[0.14em] text-stone-500">
            <span>{{ $product->agotado ? 'No disponible' : 'Disponible hoy' }}</span>
            <span>{{ $product->stock_bajo ? 'Ultimas unidades' : 'Entrega rapida' }}</span>
        </div>
        <div class="mt-4 flex gap-3">
            @if (!$product->agotado)
                <form action="{{ route('web.cart.add') }}" method="POST" class="flex-1">
                    @csrf
                    <input type="hidden" name="producto_id" value="{{ $product->id }}">
                    <input type="hidden" name="cantidad" value="1">
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                    <button type="submit" class="btn btn-primary w-full justify-center">Agregar al carrito</button>
                </form>
            @else
                <span class="btn btn-outline-secondary flex-1 justify-center opacity-60">Sin stock</span>
            @endif
            <a href="{{ route('web.products.show', $product->id) }}" class="btn btn-outline-secondary justify-center px-4">Ver</a>
        </div>
    </div>
</article>
