# Despliegue conjunto en cPanel

Este repositorio contiene dos aplicaciones Laravel independientes:

- `/`: frontend de `https://delicias.saborcentral.com`
- `/backend`: API de `https://api.saborcentral.com`

Comparten repositorio, pero no comparten `.env`, `vendor`, sesiones ni claves.

## Raices de los dominios

Clona el repositorio fuera de una carpeta publica, por ejemplo:

```text
/home/USUARIO/apps/delicias-suite
```

Configura en cPanel:

```text
delicias.saborcentral.com -> /home/USUARIO/apps/delicias-suite/public
api.saborcentral.com      -> /home/USUARIO/apps/delicias-suite/backend/public
```

No apuntes ninguno de los dominios a la raiz del repositorio.

## Variables del frontend

Crea `/home/USUARIO/apps/delicias-suite/.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://delicias.saborcentral.com
BACKEND_API_BASE_URL=https://api.saborcentral.com
GOOGLE_REDIRECT_URI=https://delicias.saborcentral.com/register/google/callback
```

Completa también `APP_KEY`, correo y credenciales de Google.

## Variables del backend

Crea `/home/USUARIO/apps/delicias-suite/backend/.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.saborcentral.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=BASE_ACTUAL
DB_USERNAME=USUARIO_ACTUAL
DB_PASSWORD=CLAVE_ACTUAL
JWT_SECRET=SECRETO_ACTUAL
GOOGLE_CLIENT_ID=EL_MISMO_CLIENT_ID_DEL_FRONTEND
FRONTEND_URL=https://delicias.saborcentral.com
PASSWORD_RESET_TTL_MINUTES=30
PRODUCT_EMAIL_NOTIFICATIONS_ENABLED=true
RESEND_API_KEY=LA_MISMA_CLAVE_RESEND_VALIDA
MAIL_FROM_ADDRESS=CORREO_VERIFICADO_EN_RESEND
MAIL_FROM_NAME="Delicias del centro"
```

Conserva los demás tokens de facturación y correo del `.env` actual del API.
Nunca subas ninguno de los dos archivos `.env` a Git.

## Recuperacion de contrasena

El despliegue FTP automatico excluye `/backend`, por lo que estos archivos se
suben manualmente dentro de `public_html/api.saborcentral.com` conservando sus
rutas:

```text
backend/app/Http/Controllers/AuthController.php -> app/Http/Controllers/AuthController.php
backend/app/Http/Controllers/FacturacionController.php -> app/Http/Controllers/FacturacionController.php
backend/app/Http/Controllers/ProductosController.php -> app/Http/Controllers/ProductosController.php
backend/app/Services/ComprobanteEmailService.php -> app/Services/ComprobanteEmailService.php
backend/app/Services/NewProductEmailService.php -> app/Services/NewProductEmailService.php
backend/app/Services/JwtService.php              -> app/Services/JwtService.php
backend/config/services.php                      -> config/services.php
backend/routes/api.php                           -> routes/api.php
```

No reemplaces el `.env` del hosting. Agrega solo las variables indicadas arriba
y conserva la base de datos, claves y tokens existentes.

Despues ejecuta con PHP 8.3:

```bash
cd ~/public_html/api.saborcentral.com
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan cache:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
```

## Instalacion

Desde Terminal de cPanel:

```bash
cd /home/USUARIO/apps/delicias-suite
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan optimize

cd backend
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan optimize
```

Da permisos de escritura a `storage` y `bootstrap/cache` en ambas aplicaciones.

## Correos de ciclo de vida del cliente

El API puede enviar:

- bienvenida al crear una cuenta;
- reactivacion cuando la ultima compra supera 30 dias;
- solicitud de opinion un dia despues de marcar un pedido como entregado.

Archivos adicionales para subir manualmente al API:

```text
backend/app/Services/CustomerLifecycleEmailService.php -> app/Services/CustomerLifecycleEmailService.php
backend/database/migrations/2026_06_12_000000_create_customer_email_events_table.php -> database/migrations/2026_06_12_000000_create_customer_email_events_table.php
backend/routes/console.php -> routes/console.php
```

Agrega al `.env` del API:

```env
CUSTOMER_LIFECYCLE_EMAILS_ENABLED=true
WELCOME_EMAIL_ENABLED=true
WELCOME_OFFER_TEXT=
WELCOME_EMAIL_RETRY_DAYS=7
DORMANT_EMAIL_ENABLED=true
DORMANT_CUSTOMER_DAYS=30
DORMANT_OFFER_TEXT=
REVIEW_EMAIL_ENABLED=true
REVIEW_EMAIL_DELAY_DAYS=1
```

No escribas descuentos ni beneficios en `WELCOME_OFFER_TEXT` o
`DORMANT_OFFER_TEXT` si no se aplican realmente en caja o checkout.

Ejecuta solo la migracion nueva:

```bash
cd ~/public_html/api.saborcentral.com
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate \
  --path=database/migrations/2026_06_12_000000_create_customer_email_events_table.php \
  --force
```

En `cPanel > Cron Jobs`, agrega una tarea cada hora:

```cron
0 * * * * cd /home/bcdroovr/public_html/api.saborcentral.com && /opt/cpanel/ea-php83/root/usr/bin/php artisan customers:lifecycle-emails --limit=100 >> /dev/null 2>&1
```

Para probarla manualmente:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan customers:lifecycle-emails --limit=10
```

## Datos persistentes

La base MySQL existente es obligatoria. El código recibido no contiene
migraciones completas para productos, pedidos, pagos, comprobantes, reservas y
almacén. No ejecutes migraciones destructivas ni reemplaces la base actual.

Antes de cambiar el Document Root del API, conserva:

```text
api.saborcentral.com/.env
api.saborcentral.com/public/uploads
```

Después copia `public/uploads` a:

```text
/home/USUARIO/apps/delicias-suite/backend/public/uploads
```

## Verificacion

Comprueba en este orden:

```text
https://api.saborcentral.com/
https://api.saborcentral.com/api/health
https://api.saborcentral.com/api/productos?limite=1
https://delicias.saborcentral.com/
```

La raíz del API debe responder `Delicias API`, el health debe devolver
`{"statusCode":200,"ok":true}` y el frontend debe mostrar productos.
