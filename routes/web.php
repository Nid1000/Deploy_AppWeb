<?php

use App\Http\Controllers\Web\AdminAuthWebController;
use App\Http\Controllers\Web\AdminWebController;
use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\CartWebController;
use App\Http\Controllers\Web\CheckoutWebController;
use App\Http\Controllers\Web\ContactWebController;
use App\Http\Controllers\Web\OrdersWebController;
use App\Http\Controllers\Web\ProfileWebController;
use App\Http\Controllers\Web\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('web.home');
Route::get('/categorias', [StorefrontController::class, 'categories'])->name('web.categories');
Route::get('/productos', [StorefrontController::class, 'products'])->name('web.products');
Route::get('/productos/{id}', [StorefrontController::class, 'showProduct'])->whereNumber('id')->name('web.products.show');
Route::get('/contacto', [StorefrontController::class, 'contact'])->name('web.contact');
Route::post('/contacto', [ContactWebController::class, 'store'])->name('web.contact.submit');
Route::post('/carrito/agregar', [CartWebController::class, 'add'])->name('web.cart.add');
Route::patch('/carrito/{id}', [CartWebController::class, 'update'])->whereNumber('id')->name('web.cart.update');
Route::post('/carrito/vaciar', [CartWebController::class, 'clear'])->name('web.cart.clear');

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('web.login');
Route::post('/login', [AuthWebController::class, 'login'])->name('web.login.submit');
Route::get('/register', [AuthWebController::class, 'showRegister'])->name('web.register');
Route::post('/register', [AuthWebController::class, 'register'])->name('web.register.submit');
Route::post('/logout', [AuthWebController::class, 'logout'])->name('web.logout');

Route::middleware('web.user')->group(function () {
    Route::get('/perfil', [ProfileWebController::class, 'show'])->name('web.profile');
    Route::patch('/perfil', [ProfileWebController::class, 'updateProfile'])->name('web.profile.update');
    Route::patch('/perfil/password', [ProfileWebController::class, 'updatePassword'])->name('web.profile.password');
    Route::post('/notificaciones/marcar-vistas', [ProfileWebController::class, 'markNotificationsSeen'])->name('web.notifications.seen');
    Route::get('/checkout', [CheckoutWebController::class, 'show'])->name('web.checkout');
    Route::post('/checkout/validar-documento', [CheckoutWebController::class, 'validateDocument'])->name('web.checkout.validate-document');
    Route::post('/checkout', [CheckoutWebController::class, 'store'])->name('web.checkout.submit');
    Route::get('/orders', [OrdersWebController::class, 'index'])->name('web.orders');
    Route::get('/historial', [OrdersWebController::class, 'index'])->name('web.history');
    Route::get('/orders/{id}', [OrdersWebController::class, 'show'])->whereNumber('id')->name('web.orders.show');
    Route::post('/orders/{id}/cancel', [OrdersWebController::class, 'cancel'])->whereNumber('id')->name('web.orders.cancel');
});

Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthWebController::class, 'showLogin'])->name('web.admin.login');
    Route::post('/login', [AdminAuthWebController::class, 'login'])->name('web.admin.login.submit');
    Route::post('/logout', [AdminAuthWebController::class, 'logout'])->name('web.admin.logout');

    Route::middleware('web.admin')->group(function () {
        Route::get('/', [AdminWebController::class, 'dashboard'])->name('web.admin.dashboard');

        Route::get('/categorias', [AdminWebController::class, 'categoriesIndex'])->name('web.admin.categories.index');
        Route::get('/categorias/nueva', [AdminWebController::class, 'categoriesCreate'])->name('web.admin.categories.create');
        Route::post('/categorias', [AdminWebController::class, 'categoriesStore'])->name('web.admin.categories.store');
        Route::get('/categorias/{id}', [AdminWebController::class, 'categoriesEdit'])->whereNumber('id')->name('web.admin.categories.edit');
        Route::patch('/categorias/{id}', [AdminWebController::class, 'categoriesUpdate'])->whereNumber('id')->name('web.admin.categories.update');
        Route::post('/categorias/{id}/toggle', [AdminWebController::class, 'categoriesToggle'])->whereNumber('id')->name('web.admin.categories.toggle');
        Route::post('/categorias/{id}/delete', [AdminWebController::class, 'categoriesDelete'])->whereNumber('id')->name('web.admin.categories.delete');

        Route::get('/productos', [AdminWebController::class, 'productsIndex'])->name('web.admin.products.index');
        Route::get('/productos/nuevo', [AdminWebController::class, 'productsCreate'])->name('web.admin.products.create');
        Route::post('/productos', [AdminWebController::class, 'productsStore'])->name('web.admin.products.store');
        Route::get('/productos/{id}', [AdminWebController::class, 'productsEdit'])->whereNumber('id')->name('web.admin.products.edit');
        Route::patch('/productos/{id}', [AdminWebController::class, 'productsUpdate'])->whereNumber('id')->name('web.admin.products.update');
        Route::post('/productos/{id}/destacado', [AdminWebController::class, 'productsToggleFeatured'])->whereNumber('id')->name('web.admin.products.featured');
        Route::post('/productos/{id}/delete', [AdminWebController::class, 'productsDelete'])->whereNumber('id')->name('web.admin.products.delete');

        Route::get('/usuarios', [AdminWebController::class, 'usersIndex'])->name('web.admin.users.index');
        Route::get('/usuarios/{id}', [AdminWebController::class, 'usersShow'])->whereNumber('id')->name('web.admin.users.show');
        Route::patch('/usuarios/{id}', [AdminWebController::class, 'usersUpdate'])->whereNumber('id')->name('web.admin.users.update');
        Route::post('/usuarios/{id}/toggle', [AdminWebController::class, 'usersToggle'])->whereNumber('id')->name('web.admin.users.toggle');

        Route::get('/pedidos', [AdminWebController::class, 'ordersIndex'])->name('web.admin.orders.index');
        Route::get('/pedidos/{id}', [AdminWebController::class, 'ordersShow'])->whereNumber('id')->name('web.admin.orders.show');
        Route::post('/pedidos/{id}/estado', [AdminWebController::class, 'ordersUpdateState'])->whereNumber('id')->name('web.admin.orders.state');
        Route::post('/pedidos/{id}/reparto', [AdminWebController::class, 'ordersUpdateShipping'])->whereNumber('id')->name('web.admin.orders.shipping');
        Route::post('/pedidos/{id}/fecha-entrega', [AdminWebController::class, 'ordersUpdateDeliveryDate'])->whereNumber('id')->name('web.admin.orders.delivery');

        Route::get('/comprobantes', [AdminWebController::class, 'receiptsIndex'])->name('web.admin.receipts.index');
        Route::get('/reservas', [AdminWebController::class, 'reservationsIndex'])->name('web.admin.reservations.index');
        Route::post('/reservas/{id}/estado', [AdminWebController::class, 'reservationsUpdateState'])->whereNumber('id')->name('web.admin.reservations.state');
        Route::get('/reservas/exportar', [AdminWebController::class, 'reservationsExport'])->name('web.admin.reservations.export');
        Route::get('/almacen', [AdminWebController::class, 'warehouseIndex'])->name('web.admin.warehouse.index');
        Route::post('/almacen/movimientos', [AdminWebController::class, 'warehouseStore'])->name('web.admin.warehouse.store');
        Route::get('/almacen/exportar', [AdminWebController::class, 'warehouseExport'])->name('web.admin.warehouse.export');
        Route::get('/reportes', [AdminWebController::class, 'reportsIndex'])->name('web.admin.reports.index');
        Route::get('/reportes/exportar/{tipo}', [AdminWebController::class, 'reportsExport'])->name('web.admin.reports.export');

        Route::get('/configuracion', [AdminWebController::class, 'settingsIndex'])->name('web.admin.settings.index');
        Route::post('/configuracion', [AdminWebController::class, 'settingsUpdate'])->name('web.admin.settings.update');
        Route::post('/configuracion/notificacion', [AdminWebController::class, 'sendNotification'])->name('web.admin.settings.notify');
        Route::post('/notificaciones/marcar-vistas', [AdminWebController::class, 'markNotificationsSeen'])->name('web.admin.notifications.seen');
    });
});
