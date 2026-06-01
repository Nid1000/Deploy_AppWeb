# Frontend Laravel

Este `frontend` es la aplicacion web en Laravel que conserva el diseno del sistema y consume el backend por API.

## Arquitectura

- `frontend/`: interfaz web Laravel + Blade + Vite
- `backend/`: API Laravel en `http://127.0.0.1:5001`
- Conexion API: `BACKEND_API_BASE_URL`

La interfaz Blade usa:

- `app/Http/Controllers/Web` para la logica web
- `resources/views` para las vistas Blade
- `resources/css/app.css` para estilos
- `app/Services/BackendApiClient.php` para consumir el backend

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

## Nota

El frontend ya quedo enfocado solo en Laravel Blade.
