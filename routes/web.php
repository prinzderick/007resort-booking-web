<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\MockPaystackController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketPurchaseController;
use App\Http\Middleware\RequireCustomer;
use App\Http\Middleware\SpamGuard;
use Illuminate\Support\Facades\Route;

/*
| Liveness probe for load balancers / uptime monitoring.
| (Laravel's built-in /up endpoint is also available.)
*/
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => '007resort-booking-web',
]))->name('health');

// ---- Public site (server-rendered, cacheable content, no business logic) ----
Route::middleware('throttle:public')->group(function () {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/facilities/{slug}', [PageController::class, 'facility'])->where('slug', '[a-z\-]+')->name('facility');
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    Route::get('/robots.txt', [PageController::class, 'robots'])->name('robots');
    Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');

    Route::get('/memberships', [MembershipController::class, 'index'])->name('memberships.index');
    Route::get('/pool', [TicketPurchaseController::class, 'form'])->name('pool');

    // Sports / spa / salon: browsing is public, holding needs an account.
    Route::get('/book/{slug}', [BookingController::class, 'resources'])->where('slug', '[a-z\-]+')->name('book.resources');
    Route::get('/book/{slug}/{resourceId}', [BookingController::class, 'slots'])->where('slug', '[a-z\-]+')->name('book.slots');
});

// ---- Customer identity (API customer endpoints) ----
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware(['throttle:register', SpamGuard::class])->name('register.store');
Route::get('/verify', [AuthController::class, 'showVerify'])->name('verify');
Route::post('/verify', [AuthController::class, 'verify'])->middleware('throttle:verify')->name('verify.store');
Route::post('/verify/resend', [AuthController::class, 'resend'])->middleware('throttle:verify')->name('verify.resend');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ---- Signed-in customer: holds, payments, tickets, history ----
Route::middleware(RequireCustomer::class)->group(function () {
    Route::post('/book/{slug}/{resourceId}/hold', [BookingController::class, 'hold'])->middleware('throttle:booking')->where('slug', '[a-z\-]+')->name('book.hold');
    Route::get('/checkout/{bookingId}', [BookingController::class, 'checkout'])->name('checkout.show');
    Route::post('/checkout/{bookingId}/pay', [BookingController::class, 'pay'])->middleware('throttle:payment')->name('checkout.pay');
    Route::post('/checkout/{bookingId}/release', [BookingController::class, 'release'])->middleware('throttle:booking')->name('checkout.release');

    Route::post('/pool/order', [TicketPurchaseController::class, 'order'])->middleware('throttle:payment')->name('pool.order');
    Route::post('/memberships/{planId}/buy', [MembershipController::class, 'buy'])->middleware('throttle:payment')->name('memberships.buy');

    Route::get('/payment/return', PaymentReturnController::class)->middleware('throttle:payment')->name('payment.return');

    Route::get('/tickets/{id}', [TicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{id}/qr.svg', [TicketController::class, 'qr'])->name('tickets.qr');
    Route::get('/tickets/{id}/download', [TicketController::class, 'download'])->name('tickets.download');
    Route::get('/orders/{orderId}/tickets', [TicketController::class, 'order'])->name('tickets.order');

    Route::get('/account', [AccountController::class, 'dashboard'])->name('account');
    Route::get('/account/bookings', [AccountController::class, 'bookings'])->name('account.bookings');
    Route::get('/account/bookings/{bookingId}', [AccountController::class, 'show'])->name('account.bookings.show');
    Route::post('/account/bookings/{bookingId}/cancel', [AccountController::class, 'cancel'])->middleware('throttle:booking')->name('account.bookings.cancel');
    Route::get('/account/bookings/{bookingId}/reschedule', [AccountController::class, 'rescheduleForm'])->name('account.bookings.reschedule');
    Route::post('/account/bookings/{bookingId}/reschedule', [AccountController::class, 'reschedule'])->middleware('throttle:booking')->name('account.bookings.reschedule.store');
});

// ---- Mock API mode only: fake Paystack hosted page (controller 404s unless R007_MOCK=true) ----
Route::get('/mock/paystack/{reference}', [MockPaystackController::class, 'show'])->name('mock.paystack');
Route::post('/mock/paystack/{reference}', [MockPaystackController::class, 'complete'])->name('mock.paystack.complete');
