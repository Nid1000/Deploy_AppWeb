# Frontend Laravel

Este `frontend` es la aplicacion web en Laravel que conserva el diseno del sistema y consume el backend por API.

## Arquitectura

- `/`: interfaz web Laravel + Blade + Vite
- `/backend`: API Laravel independiente
- Conexion API: `BACKEND_API_BASE_URL`

En produccion:

- Frontend: `https://delicias.saborcentral.com`
- Backend: `https://api.saborcentral.com`

Consulta [`DEPLOY-CPANEL.md`](DEPLOY-CPANEL.md) para configurar ambos dominios
desde este mismo repositorio.

La interfaz Blade usa:

- `app/Http/Controllers/Web` para la logica web
- `resources/views` para las vistas Blade
- `resources/css/app.css` para estilos
- `app/Services/BackendApiClient.php` para consumir el backend

## Registro con correo real

El frontend ahora puede:

- enviar un codigo de verificacion por correo antes del alta
- validar cuentas Gmail reales usando Google OAuth
- completar el registro web solo cuando el correo ya fue verificado

Importante:

- esta validacion ocurre en el `frontend`
- la creacion final del usuario sigue delegada al backend en `auth/register`
- el login completo con Google como reemplazo de password requerira tambien soporte equivalente en el backend API

## Puertos

- Frontend Laravel: `http://127.0.0.1:3000`
- Backend API: `http://127.0.0.1:5001`

## Configuracion

1. Copia `.env.example` a `.env`
2. Ajusta `BACKEND_API_BASE_URL` si tu backend usa otra URL
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

## Nota

El frontend ya quedo enfocado solo en Laravel Blade.
