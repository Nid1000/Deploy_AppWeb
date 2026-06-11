<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriasController;
use App\Http\Controllers\ProductosController;
use App\Http\Controllers\UsuariosController;
use App\Http\Controllers\PedidosController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\NotificacionesController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\AdminReservasController;
use App\Http\Controllers\AdminAlmacenController;
use Illuminate\Support\Facades\DB;

// Nota: Laravel ya prefija estas rutas con /api (routes/api.php).

// Health (verifica conectividad con DB)
Route::get('/health', function () {
    try {
        DB::select('select 1 as ok');
        return response()->json(['statusCode' => 200, 'ok' => true], 200);
    } catch (\Throwable $e) {
        return response()->json([
            'statusCode' => 500,
            'ok' => false,
            'message' => 'No se pudo conectar a la base de datos',
        ], 500);
    }
});

// Auth
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);
Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/auth/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
Route::post('/auth/admin/login', [AuthController::class, 'adminLogin']);
Route::get('/auth/verify', [AuthController::class, 'verify'])->middleware('jwt');

// Categorías (público)
Route::get('/categorias', [CategoriasController::class, 'index']);
Route::get('/categorias/{id}', [CategoriasController::class, 'show'])->whereNumber('id');
Route::get('/categorias/{id}/productos', [CategoriasController::class, 'productos'])->whereNumber('id');

// Categorías (admin)
Route::middleware(['jwt', 'tipo:admin'])->group(function () {
    Route::get('/categorias/admin/todos', [CategoriasController::class, 'adminList']);
    Route::get('/categorias/admin/{id}', [CategoriasController::class, 'adminShow'])->whereNumber('id');
    Route::post('/categorias/admin', [CategoriasController::class, 'adminCreate']);
    Route::put('/categorias/admin/{id}', [CategoriasController::class, 'adminUpdate'])->whereNumber('id');
    Route::post('/categorias/admin/{id}/imagen', [CategoriasController::class, 'adminUpdateImagen'])->whereNumber('id');
    Route::put('/categorias/admin/{id}/imagen', [CategoriasController::class, 'adminUpdateImagen'])->whereNumber('id');
    Route::patch('/categorias/admin/{id}/estado', [CategoriasController::class, 'adminEstado'])->whereNumber('id');
    Route::delete('/categorias/admin/{id}', [CategoriasController::class, 'adminDelete'])->whereNumber('id');
});

// Productos (público)
Route::get('/productos', [ProductosController::class, 'index']);
Route::get('/productos/{id}', [ProductosController::class, 'show'])->whereNumber('id');

// Productos (admin)
Route::middleware(['jwt', 'tipo:admin'])->group(function () {
    Route::post('/productos', [ProductosController::class, 'store']);
    Route::put('/productos/{id}', [ProductosController::class, 'update'])->whereNumber('id');
    Route::post('/productos/{id}/imagen', [ProductosController::class, 'updateImagen'])->whereNumber('id');
    Route::delete('/productos/{id}', [ProductosController::class, 'destroy'])->whereNumber('id');
});

// Usuarios
Route::get('/usuarios/distritos-huancayo', [UsuariosController::class, 'distritosHuancayo']);

Route::middleware(['jwt', 'tipo:usuario'])->group(function () {
    Route::get('/usuarios/perfil', [UsuariosController::class, 'perfil']);
    Route::put('/usuarios/perfil', [UsuariosController::class, 'updatePerfil']);
    Route::put('/usuarios/cambiar-password', [UsuariosController::class, 'cambiarPassword']);
    Route::get('/usuarios/estadisticas', [UsuariosController::class, 'estadisticas']);
});

Route::middleware(['jwt', 'tipo:admin'])->group(function () {
    Route::get('/usuarios/admin/todos', [UsuariosController::class, 'adminList']);
    Route::get('/usuarios/admin/{id}', [UsuariosController::class, 'adminShow'])->whereNumber('id');
    Route::patch('/usuarios/admin/{id}/estado', [UsuariosController::class, 'adminEstado'])->whereNumber('id');
    Route::put('/usuarios/admin/{id}', [UsuariosController::class, 'adminUpdate'])->whereNumber('id');
});

// Pedidos
Route::middleware(['jwt', 'tipo:usuario'])->group(function () {
    Route::post('/pedidos', [PedidosController::class, 'store']);
    Route::get('/pedidos/mis-pedidos', [PedidosController::class, 'misPedidos']);
    Route::get('/pedidos/{id}', [PedidosController::class, 'show'])->whereNumber('id');
    Route::put('/pedidos/{id}/cancelar', [PedidosController::class, 'cancelar'])->whereNumber('id');
});

Route::middleware(['jwt', 'tipo:admin'])->group(function () {
    Route::get('/pedidos/admin/todos', [PedidosController::class, 'adminList']);
    Route::get('/pedidos/admin/{id}', [PedidosController::class, 'adminShow'])->whereNumber('id');
    Route::patch('/pedidos/admin/{id}/estado', [PedidosController::class, 'adminEstado'])->whereNumber('id');
    Route::put('/pedidos/admin/{id}/fecha-entrega', [PedidosController::class, 'adminFechaEntrega'])->whereNumber('id');
    Route::put('/pedidos/admin/{id}/reparto', [PedidosController::class, 'adminReparto'])->whereNumber('id');
});

// Facturación
Route::middleware(['jwt', 'tipo:usuario'])->group(function () {
    Route::post('/facturacion/emitir', [FacturacionController::class, 'emitir']);
    Route::get('/facturacion/mis-comprobantes', [FacturacionController::class, 'misComprobantes']);
    Route::get('/facturacion/consulta-dni', [FacturacionController::class, 'consultaDni']);
    Route::get('/facturacion/consulta-ruc', [FacturacionController::class, 'consultaRuc']);
});

Route::middleware(['jwt', 'tipo:admin'])->group(function () {
    Route::get('/facturacion/admin/comprobantes', [FacturacionController::class, 'adminComprobantes']);
});

// Reservas y almacen (admin)
Route::middleware(['jwt', 'tipo:admin'])->group(function () {
    Route::get('/reservas/admin/todas', [AdminReservasController::class, 'index']);
    Route::patch('/reservas/admin/{id}/estado', [AdminReservasController::class, 'updateEstado'])->whereNumber('id');
    Route::get('/reservas/admin/exportar', [AdminReservasController::class, 'export']);

    Route::get('/almacen/admin/movimientos', [AdminAlmacenController::class, 'index']);
    Route::post('/almacen/admin/movimientos', [AdminAlmacenController::class, 'store']);
    Route::get('/almacen/admin/exportar', [AdminAlmacenController::class, 'export']);
});

// Notificaciones
Route::middleware(['jwt', 'tipo:usuario'])->group(function () {
    Route::get('/notificaciones/pendientes', [NotificacionesController::class, 'pendientes']);
    Route::post('/notificaciones/marcar-mostradas', [NotificacionesController::class, 'marcarMostradas']);
});

Route::middleware(['jwt', 'tipo:admin'])->group(function () {
    Route::post('/notificaciones/admin/enviar', [NotificacionesController::class, 'adminEnviar']);
    Route::get('/notificaciones/admin/pendientes', [NotificacionesController::class, 'adminPendientes']);
    Route::post('/notificaciones/admin/marcar-mostradas', [NotificacionesController::class, 'adminMarcarMostradas']);
});

// Reportes (admin)
Route::middleware(['jwt', 'tipo:admin'])->prefix('reportes/admin')->group(function () {
    Route::get('/ventas-diarias', [ReportesController::class, 'ventasDiarias']);
    Route::get('/ventas-semanales', [ReportesController::class, 'ventasSemanales']);
    Route::get('/ventas-mensuales', [ReportesController::class, 'ventasMensuales']);
    Route::get('/top-productos', [ReportesController::class, 'topProductos']);
    Route::get('/top-categorias', [ReportesController::class, 'topCategorias']);
    Route::get('/exportar/ventas', [ReportesController::class, 'exportVentas']);
    Route::get('/exportar/pedidos', [ReportesController::class, 'exportPedidos']);
    Route::get('/exportar/productos', [ReportesController::class, 'exportProductos']);
});

// Contacto (público)
Route::post('/contacto', [ContactoController::class, 'store']);

// Chatbot / IA (público)
Route::get('/chatbot/health', [ChatbotController::class, 'health']);
Route::post('/chatbot/ask', [ChatbotController::class, 'ask']);
