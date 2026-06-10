<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Codigo de verificacion</title>
</head>
<body style="margin:0;padding:24px;background:#f5f5f4;font-family:Arial,sans-serif;color:#1c1917;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #fde68a;border-radius:24px;padding:32px;">
        <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.2em;text-transform:uppercase;color:#b45309;">Verificacion</p>
        <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;">Confirma tu correo</h1>
        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">
            Usa este codigo para continuar con la creacion de tu cuenta.
        </p>
        <div style="margin:0 0 20px;padding:18px 20px;background:#fffbeb;border:1px solid #fcd34d;border-radius:18px;text-align:center;">
            <span style="font-size:32px;font-weight:700;letter-spacing:0.28em;color:#92400e;">{{ $code }}</span>
        </div>
        <p style="margin:0 0 12px;font-size:14px;line-height:1.6;">
            Este codigo vence a las {{ $expiresAt->timezone(config('app.timezone'))->format('H:i') }}.
        </p>
        <p style="margin:0;font-size:14px;line-height:1.6;color:#57534e;">
            Si no solicitaste este registro, puedes ignorar este mensaje.
        </p>
    </div>
</body>
</html>
