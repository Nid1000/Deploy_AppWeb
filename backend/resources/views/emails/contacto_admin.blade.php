<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Nuevo contacto</title>
  </head>
  <body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.4;">
    <h2>Nuevo mensaje de contacto</h2>
    <p><strong>Nombre:</strong> {{ $c['nombre'] ?? '' }}</p>
    <p><strong>Email:</strong> {{ $c['email'] ?? '' }}</p>
    @if(!empty($c['telefono']))
      <p><strong>Teléfono:</strong> {{ $c['telefono'] }}</p>
    @endif
    <p><strong>Mensaje:</strong></p>
    <pre style="white-space: pre-wrap; background: #f6f6f6; padding: 12px; border-radius: 8px;">{{ $c['mensaje'] ?? '' }}</pre>
    <p style="color:#666; font-size:12px;">Enviado desde {{ config('app.name') }}.</p>
  </body>
</html>

