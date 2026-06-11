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
```

Conserva los demás tokens de facturación y correo del `.env` actual del API.
Nunca subas ninguno de los dos archivos `.env` a Git.

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
