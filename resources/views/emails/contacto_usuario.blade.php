<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Recibimos tu mensaje</title>
  </head>
  <body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.4;">
    <h2>¡Gracias por contactarnos!</h2>
    <p>Hola {{ $c['nombre'] ?? 'cliente' }},</p>
    <p>Recibimos tu mensaje y te responderemos lo antes posible.</p>
    <p><strong>Tu mensaje:</strong></p>
    <pre style="white-space: pre-wrap; background: #f6f6f6; padding: 12px; border-radius: 8px;">{{ $c['mensaje'] ?? '' }}</pre>
    <p>Atentamente,<br/>{{ config('app.name') }}</p>
  </body>
</html>

