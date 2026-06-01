# Guia de vistas del frontend

Este es el frontend web que debes editar:

- `frontend/resources/views/...`

No edites estos archivos:

- `frontend/storage/framework/views/...`

Esos archivos se generan solos por Laravel y pueden cambiar de nombre.

## Pantallas principales

- Inicio:
  - `frontend/resources/views/web/home.blade.php`
- Productos:
  - `frontend/resources/views/web/products/index.blade.php`
- Detalle de producto:
  - `frontend/resources/views/web/products/show.blade.php`
- Tarjeta de producto:
  - `frontend/resources/views/web/products/partials/card.blade.php`
- Categorias:
  - `frontend/resources/views/web/categories/index.blade.php`
- Contacto:
  - `frontend/resources/views/web/contact/index.blade.php`
- Login:
  - `frontend/resources/views/web/auth/login.blade.php`
- Registro:
  - `frontend/resources/views/web/auth/register.blade.php`
- Perfil:
  - `frontend/resources/views/web/profile/show.blade.php`
- Checkout:
  - `frontend/resources/views/web/checkout.blade.php`
- Pedidos:
  - `frontend/resources/views/web/orders/index.blade.php`
- Detalle de pedido:
  - `frontend/resources/views/web/orders/show.blade.php`

## Partes globales del sitio

- Header / menu / boton de tema / carrito / footer:
  - `frontend/resources/views/layouts/storefront.blade.php`
- Layout admin:
  - `frontend/resources/views/layouts/admin.blade.php`

## Estilos y comportamiento

- Estilos globales:
  - `frontend/resources/css/app.css`
- Javascript del tema oscuro:
  - `frontend/resources/js/app.js`

## Datos que vienen del backend

- Controlador web del frontend:
  - `frontend/app/Http/Controllers/Web/StorefrontController.php`
- Cliente que consulta la API:
  - `frontend/app/Services/BackendApiClient.php`

## Regla rapida

Si quieres cambiar:

- texto o estructura de una pantalla: `resources/views`
- colores, tamanos, fondos y diseno: `resources/css/app.css`
- comportamiento de tema oscuro: `resources/js/app.js`
- datos de productos, categorias, pedidos o usuario: `StorefrontController.php` y `BackendApiClient.php`
