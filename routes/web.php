<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\MockPaystackController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketPurchaseController;
use App\Http\Middleware\PublicCache;
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

// ---- Public site (server-rendered; every word comes from the CMS, booking data from the API) ----
Route::middleware(['throttle:public', PublicCache::class])->group(function () {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/facilities/{slug}', [PageController::class, 'facility'])->where('slug', '[a-z\-]+')->name('facility');
    Route::get('/sports', [PageController::class, 'themed'])->defaults('slug', 'sports')->name('sports');
    Route::get('/spa', [PageController::class, 'themed'])->defaults('slug', 'spa')->name('spa');
    Route::get('/dining', [PageController::class, 'themed'])->defaults('slug', 'dining')->name('dining');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{slug}.ics', [EventController::class, 'ics'])->where('slug', '[a-z0-9\-]+')->name('events.ics');
    Route::get('/events/{slug}', [EventController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('events.show');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('blog.show');
    Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
    Route::get('/about', [ContentController::class, 'about'])->name('about');
    Route::get('/faq', [ContentController::class, 'faq'])->name('faq');
    Route::get('/terms', [ContentController::class, 'legal'])->defaults('slug', 'terms')->name('legal.terms');
    Route::get('/privacy', [ContentController::class, 'legal'])->defaults('slug', 'privacy')->name('legal.privacy');
    Route::get('/cookies', [ContentController::class, 'legal'])->defaults('slug', 'cookies')->name('legal.cookies');
    Route::get('/pages/{slug}', [ContentController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('pages.show');
    Route::get('/contact', [ContactController::class, 'show'])->name('contact');

    Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
    Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

    Route::get('/memberships', [MembershipController::class, 'index'])->name('memberships.index');
    // Friendly aliases that CMS editors (and the CMS demo content) use for links: /book, /tickets, /membership.
    Route::redirect('/book', '/sports', 301);
    Route::redirect('/tickets', '/pool', 301);
    Route::redirect('/membership', '/memberships', 301);
    Route::get('/pool', [TicketPurchaseController::class, 'form'])->name('pool');

    // Sports / spa / salon: browsing is public, holding needs an account.
    Route::get('/book-now', [PageController::class, 'bookNow'])->name('book.now');
    Route::get('/book/{slug}', [BookingController::class, 'resources'])->where('slug', '[a-z\-]+')->name('book.resources');
    Route::get('/book/{slug}/{resourceId}', [BookingController::class, 'slots'])->where('slug', '[a-z\-]+')->name('book.slots');
});

// ---- Forms that talk to the CMS API (CSRF + honeypot + rate limits) ----
Route::post('/contact', [ContactController::class, 'send'])->middleware(['throttle:contact', SpamGuard::class])->name('contact.send');
Route::post('/newsletter', [NewsletterController::class, 'subscribe'])->middleware('throttle:subscribe')->name('newsletter.subscribe');
Route::get('/newsletter/confirm', [NewsletterController::class, 'confirmPage'])->name('newsletter.confirm');
Route::post('/newsletter/confirm', [NewsletterController::class, 'confirm'])->middleware('throttle:verify')->name('newsletter.confirm.store');
Route::get('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribePage'])->name('newsletter.unsubscribe');
Route::post('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe'])->middleware('throttle:verify')->name('newsletter.unsubscribe.store');

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
