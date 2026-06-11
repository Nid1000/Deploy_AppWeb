<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Nuevo pedido</title>
  </head>
  <body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.4;">
    <h2>Nuevo pedido #{{ (int)($p['id'] ?? 0) }}</h2>
    <p><strong>Total:</strong> S/ {{ number_format((float)($p['total'] ?? 0), 2) }}</p>
    @if(!empty($p['cliente_nombre']))
      <p><strong>Cliente:</strong> {{ $p['cliente_nombre'] }} @if(!empty($p['cliente_email'])) ({{ $p['cliente_email'] }}) @endif</p>
    @endif
    @if(!empty($p['telefono_contacto']))
      <p><strong>Teléfono:</strong> {{ $p['telefono_contacto'] }}</p>
    @endif
    @if(!empty($p['direccion_entrega']))
      <p><strong>Entrega:</strong> {{ $p['direccion_entrega'] }} @if(!empty($p['distrito_entrega'])) ({{ $p['distrito_entrega'] }}) @endif</p>
    @endif
    <h3>Detalle</h3>
    <ul>
      @foreach($d as $item)
        <li>
          {{ $item['producto_nombre'] ?? ('Producto ' . ($item['producto_id'] ?? '')) }}
          — x{{ (int)($item['cantidad'] ?? 0) }}
          — S/ {{ number_format((float)($item['subtotal'] ?? 0), 2) }}
        </li>
      @endforeach
    </ul>
    <p style="color:#666; font-size:12px;">Notificación automática.</p>
  </body>
</html>

