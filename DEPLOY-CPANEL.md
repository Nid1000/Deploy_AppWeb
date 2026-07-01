# Despliegue del frontend en cPanel

Este repositorio contiene solo la aplicacion web Laravel de:

- `https://delicias.saborcentral.com`

La API real vive fuera de este repositorio y se consume desde:

- `https://api.saborcentral.com`

## Document root

Clona o sube este proyecto fuera de una carpeta publica, por ejemplo:

```text
/home/USUARIO/apps/delicias-web
```

Configura el dominio en cPanel:

```text
delicias.saborcentral.com -> /home/USUARIO/apps/delicias-web/public
```

No apuntes el dominio a la raiz del repositorio.

## Variables de entorno

Crea `/home/USUARIO/apps/delicias-web/.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://delicias.saborcentral.com
BACKEND_API_BASE_URL=https://api.saborcentral.com
GOOGLE_REDIRECT_URI=https://delicias.saborcentral.com/register/google/callback
```

Completa tambien:

```env
APP_KEY=
MAIL_MAILER=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=
RESEND_API_KEY=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

Nunca subas `.env` a Git.

## Instalacion

Desde Terminal de cPanel:

```bash
cd /home/USUARIO/apps/delicias-web
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan optimize
```

Da permisos de escritura a:

```text
storage
bootstrap/cache
```

## API externa

El frontend usa `BACKEND_API_BASE_URL` para llamar a la API. En produccion debe ser:

```env
BACKEND_API_BASE_URL=https://api.saborcentral.com
```

No hay que subir, migrar ni desplegar un backend desde este repositorio.

## Verificacion

Comprueba en este orden:

```text
https://api.saborcentral.com/api/health
https://api.saborcentral.com/api/productos?limite=1
https://delicias.saborcentral.com/
```

La API debe devolver datos reales y el frontend debe mostrar productos.
