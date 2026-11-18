<?php

use Illuminate\Support\Facades\Route;

// Controllers khu vực Khách hàng (Storefront)
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\SearchHistoryController;

// Controllers khu vực Quản trị (Admin & Staff)
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController as AdminUserController;

/*
|--------------------------------------------------------------------------
| 1. PUBLIC ROUTES (Khách vãng lai & Thành viên đều truy cập được)
|--------------------------------------------------------------------------
*/

// Trang chủ: Hiển thị sản phẩm mới + khu vực "Gợi ý dành riêng cho bạn"
Route::get('/', [HomeController::class, 'index'])->name('home');

// Xem danh sách sản phẩm, lọc theo danh mục và xem chi tiết biến thể
Route::get('/san-pham', [ProductController::class, 'index'])->name('products.index');
Route::get('/danh-muc/{slug}', [ProductController::class, 'byCategory'])->name('products.category');
Route::get('/san-pham/{slug}', [ProductController::class, 'show'])->name('products.show');

// Luồng Tìm kiếm & Thu thập tín hiệu gợi ý sản phẩm
Route::prefix('tim-kiem')->name('search.')->group(function () {
    // Thực hiện tìm kiếm và lưu từ khóa vào bảng search_histories
    Route::get('/', [SearchController::class, 'index'])->name('index');

    // Ghi nhận lượt click vào sản phẩm từ trang kết quả tìm kiếm (lưu search_product_clicks) rồi chuyển hướng sang trang chi tiết
    Route::get('/click/{searchHistory}/{product}', [SearchController::class, 'trackClick'])->name('click');

    // Gợi ý nhanh từ khóa cũ khi khách gõ vào ô tìm kiếm (AJAX)
    Route::get('/goi-y-tu-khoa', [SearchController::class, 'autocomplete'])->name('autocomplete');
});

// Giỏ hàng (Hoạt động với cả session_id khi chưa đăng nhập và user_id khi đã đăng nhập)
Route::prefix('gio-hang')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/them', [CartController::class, 'add'])->name('add'); // Nhận product_variant_id & quantity
    Route::patch('/cap-nhat/{cartItem}', [CartController::class, 'update'])->name('update');
    Route::delete('/xoa/{cartItem}', [CartController::class, 'remove'])->name('remove');
    Route::delete('/xoa-tat-ca', [CartController::class, 'clear'])->name('clear');
});

/*
|--------------------------------------------------------------------------
| 2. AUTHENTICATION ROUTES (Đăng nhập, Đăng ký, Đăng xuất)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])->name('login.post');

    Route::get('/dang-ky', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/dang-ky', [AuthController::class, 'register'])->name('register.post');
});

Route::post('/dang-xuat', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| 3. CUSTOMER ROUTES (Yêu cầu đăng nhập để đặt hàng & quản lý cá nhân)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    // Thanh toán & Đặt hàng
    Route::get('/thanh-toan', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/thanh-toan', [CheckoutController::class, 'process'])->name('checkout.process');

    // Quản lý đơn hàng của khách
    Route::prefix('don-hang-cua-toi')->name('customer.orders.')->group(function () {
        Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
        Route::get('/{orderCode}', [CustomerOrderController::class, 'show'])->name('show');
        Route::patch('/{orderCode}/huy', [CustomerOrderController::class, 'cancel'])->name('cancel');
    });

    // Quản lý lịch sử tìm kiếm cá nhân (Giúp khách xem hoặc xóa từ khóa để làm mới gợi ý)
    Route::prefix('lich-su-tim-kiem')->name('customer.search-history.')->group(function () {
        Route::get('/', [SearchHistoryController::class, 'index'])->name('index');
        Route::delete('/{searchHistory}', [SearchHistoryController::class, 'destroy'])->name('destroy');
        Route::delete('/', [SearchHistoryController::class, 'clear'])->name('clear');
    });
});

/*
|--------------------------------------------------------------------------
| 4. ADMIN & STAFF ROUTES (Khu vực quản trị hệ thống)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,staff'])->group(function () {
    // Bảng điều khiển: Thống kê doanh thu, top từ khóa tìm kiếm & từ khóa 0 kết quả
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Quản lý Danh mục sản phẩm
    Route::resource('categories', CategoryController::class)->except(['show']);

    // Quản lý Sản phẩm & Biến thể màu sắc/kích cỡ
    Route::resource('products', AdminProductController::class);
    Route::delete('product-variants/{variant}', [AdminProductController::class, 'destroyVariant'])
        ->name('products.variants.destroy');

    // Quản lý Đơn hàng & Cập nhật trạng thái giao hàng / thanh toán
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [AdminOrderController::class, 'index'])->name('index');
        Route::get('/{order}', [AdminOrderController::class, 'show'])->name('show');
        Route::patch('/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('update-status');
    });

    // Phân quyền cao nhất: Chỉ Admin mới được quản lý tài khoản & cấp quyền nhân viên
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', AdminUserController::class)->except(['create', 'store', 'show']);
    });
});