# Frontend Laravel

Este proyecto es la aplicacion web en Laravel que conserva el diseno del sistema y consume la API real por HTTP.

## Arquitectura

- `/`: interfaz web Laravel + Blade + Vite
- Conexion API externa: `BACKEND_API_BASE_URL`

En produccion:

- Frontend: `https://delicias.saborcentral.com`
- API: `https://api.saborcentral.com`

Consulta [`DEPLOY-CPANEL.md`](DEPLOY-CPANEL.md) para desplegar el frontend.

La interfaz Blade usa:

- `app/Http/Controllers/Web` para la logica web
- `resources/views` para las vistas Blade
- `resources/css/app.css` para estilos
- `app/Services/BackendApiClient.php` para consumir la API

## Registro con correo real

El frontend ahora puede:

- enviar un código de verificación por correo antes del alta
- validar cuentas Gmail reales usando Google OAuth
- completar el registro web solo cuando el correo ya fue verificado

Importante:

- esta validación ocurre en el `frontend`
- la creacion final del usuario sigue delegada a la API en `auth/register`
- el login completo con Google como reemplazo de password requiere soporte equivalente en la API

## Puertos

- Frontend Laravel: `http://127.0.0.1:3000`
- API de produccion: `https://api.saborcentral.com`

## Configuracion

1. Copia `.env.example` a `.env`
2. Ajusta `BACKEND_API_BASE_URL` si necesitas usar una API distinta
3. Instala dependencias:

```bash
composer install
npm install
```

## Desarrollo

Terminal 1:

```bash
php artisan serve --host=0.0.0.0 --port=3000
```

Terminal 2:

```bash
npm run dev
```

## Produccion local

```bash
npm run build
php artisan serve --host=0.0.0.0 --port=3000
```

## Despliegue con Docker en Coolify

El proyecto incluye un `Dockerfile` listo para construir la aplicacion Laravel, compilar los assets de Vite y exponer el servicio por el puerto `6000`.

En Coolify:

1. Crea un nuevo recurso desde tu repositorio de GitHub.
2. Selecciona despliegue con `Dockerfile`.
3. Usa el puerto `6000` o define la variable `PORT=6000`.
4. Configura las variables de entorno tomando como base `.env.example`.
5. Genera `APP_KEY` localmente con:

```bash
php artisan key:generate --show
```

6. Ajusta `APP_URL` al dominio publico de Coolify.
7. Ajusta `BACKEND_API_BASE_URL` a la URL accesible de tu backend API. No uses `127.0.0.1` si el backend esta en otro contenedor o servicio.
8. Si usaras Resend para correos reales, configura:

```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=no-reply@tudominio.com
MAIL_FROM_NAME="${APP_NAME}"
RESEND_API_KEY=re_xxx
```

9. Si usaras Google para traer un Gmail verificado, configura:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://tu-dominio.com/register/google/callback
```

10. Para subir archivos al bucket privado `almacendelicias` desde el panel admin, configura:

```env
GOOGLE_CLOUD_PROJECT_ID=
GOOGLE_APPLICATION_CREDENTIALS=/ruta/segura/gcp-service-account.json
GCS_BUCKET_NAME=almacendelicias
GCS_UPLOAD_PREFIX=uploads
GCS_SIGNED_URL_TTL=60
```

La cuenta de servicio necesita permisos para crear y leer objetos en el bucket. No guardes el JSON dentro de `public`.

Si vas a usar SQLite, crea un volumen persistente para `/var/www/html/database` y deja:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite
```

Tambien es recomendable crear un volumen persistente para `/var/www/html/storage` si la aplicacion guarda archivos, logs o contenido publico generado.

Para ejecutar migraciones automaticamente al iniciar el contenedor, define:

```env
RUN_MIGRATIONS=true
```

Si prefieres MySQL/MariaDB en Coolify, cambia las variables:

```env
DB_CONNECTION=mysql
DB_HOST=nombre-del-servicio-db
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=usuario
DB_PASSWORD=clave
```

## Izipay en ambiente de prueba

El frontend envía a `POST /api/pagos/izipay/crear` los datos de la boleta junto con `modo_prueba`, `emitir_comprobante_al_confirmar` y la URL de retorno al pedido.

La API backend debe crear el `formToken` con credenciales de integración de Izipay, validar la firma de la respuesta o IPN y emitir la boleta solamente cuando el estado verificado sea pagado. No se debe confiar únicamente en la redirección del navegador.

Configura en ambos despliegues:

```env
IZIPAY_TEST_MODE=true
```

Después limpia la caché:

```bash
php artisan config:clear
php artisan config:cache
```

Obtén las credenciales sandbox y tarjetas vigentes desde el Backoffice Izipay. No mezcles una llave pública sandbox con credenciales API de producción.

## Nota

El frontend ya quedo enfocado solo en Laravel Blade.
