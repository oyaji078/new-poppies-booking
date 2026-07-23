<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Api\DokuNotificationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RoomController;
use App\Livewire\Admin\AmenityManager;
use App\Livewire\Admin\BookingManager;
use App\Livewire\Admin\CancellationRefundManager;
use App\Livewire\Admin\DokuEnvironmentSwitcher;
use App\Livewire\Admin\FaqManager;
use App\Livewire\Admin\FrontDesk;
use App\Livewire\Admin\GuestList;
use App\Livewire\Admin\InventoryCalendar;
use App\Livewire\Admin\PageManager;
use App\Livewire\Admin\PaymentReviewQueue;
use App\Livewire\Admin\PromotionManager;
use App\Livewire\Admin\RoomManager;
use App\Livewire\Admin\RoomTypeManager;
use App\Livewire\Admin\SettingsManager;
use App\Livewire\Admin\UserManager;
use App\Livewire\Public\CheckoutWizard;
use App\Livewire\Public\RoomSearch;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
// Health probe that works on every host (including Vercel, where /api is reserved).
Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/kamar', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/kamar/{roomType}', [RoomController::class, 'show'])->name('rooms.show');

// Availability search + checkout (Phase 4)
Route::get('/cari', RoomSearch::class)->name('search');
Route::get('/checkout/{roomType}', CheckoutWizard::class)->name('checkout');

// Booking lookup — guest access needs booking code + email
Route::get('/pemesanan', [BookingController::class, 'lookupForm'])->name('booking.lookup.form');
Route::post('/pemesanan', [BookingController::class, 'lookup'])
    ->middleware('throttle:10,1')
    ->name('booking.lookup');
Route::get('/pemesanan/{booking}', [BookingController::class, 'show'])->name('booking.show');
Route::get('/pemesanan/{booking}/invoice', [BookingController::class, 'invoice'])->name('booking.invoice');
Route::post('/pemesanan/{booking}/batal', [BookingController::class, 'cancel'])->name('booking.cancel');

// Payment (Phase 5) — the browser callback is never proof of payment.
// POST, never GET: this call creates a real transaction at DOKU, and a browser
// prefetch or link scanner must never be able to trigger it.
Route::post('/pembayaran/{booking}', [PaymentController::class, 'start'])->name('payment.start');
Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');

// DOKU server-to-server notification. It deliberately lives OUTSIDE the /api
// prefix: on serverless hosts (Vercel) the /api path is reserved for the
// platform's own functions and never reaches Laravel's router. Web routes do.
// CSRF-exempt (see bootstrap/app.php) and authenticated by DOKU signature.
// Rate limited to blunt any attempt to brute-force signatures.
Route::post('/webhook/doku/notifications', DokuNotificationController::class)
    ->middleware('throttle:120,1')
    ->name('payments.doku.notifications');
// Polled by the "waiting for confirmation" pages so they refresh the instant the
// server-to-server notification lands. Rate limited — it is called on a timer.
Route::get('/pemesanan/{booking}/status', [PaymentController::class, 'status'])
    ->middleware('throttle:60,1')
    ->name('booking.status');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Signed-in guest's own booking history (account-based, not code+email lookup).
Route::get('/pemesanan-saya', [AccountController::class, 'bookings'])
    ->middleware('auth')
    ->name('account.bookings');

/*
|--------------------------------------------------------------------------
| Admin / back office
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Room management (Phase 2)
        Route::get('/tipe-kamar', RoomTypeManager::class)->name('room-types.index');
        Route::get('/kamar', RoomManager::class)->name('rooms.index');
        Route::get('/fasilitas', AmenityManager::class)->name('amenities.index');
        Route::get('/konten', PageManager::class)->name('pages.index');

        // Inventory & pricing (Phase 3)
        Route::get('/inventaris', InventoryCalendar::class)->name('inventory.index');
        Route::get('/promosi', PromotionManager::class)->name('promotions.index');

        // Reservations & payments (Phase 4–5)
        Route::get('/reservasi', BookingManager::class)->name('bookings.index');
        Route::get('/peninjauan-pembayaran', PaymentReviewQueue::class)->name('payments.review');

        // Hotel operations (Phase 6)
        Route::get('/front-desk', FrontDesk::class)->name('frontdesk.index');
        Route::get('/pembatalan', CancellationRefundManager::class)->name('cancellations.index');
        Route::get('/tamu', GuestList::class)->name('guests.index');

        // Reports & chatbot (Phase 7)
        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/ekspor', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/faq', FaqManager::class)->name('faqs.index');

        // Payment gateway mode. Deciding whether guests are charged real money
        // is a super-admin-only action, so it carries its own guard on top of
        // the staff middleware.
        Route::get('/doku', DokuEnvironmentSwitcher::class)
            ->middleware('superadmin')
            ->name('doku.environment');

        // Staff account & role management — creating accounts and granting roles
        // decides who can operate the hotel, so it is super-admin-only.
        Route::get('/pengguna', UserManager::class)
            ->middleware('superadmin')
            ->name('users.index');

        // Hotel operating parameters (tax, service, hold, cancellation, identity)
        // drive pricing and money handling — super-admin-only.
        Route::get('/pengaturan', SettingsManager::class)
            ->middleware('superadmin')
            ->name('settings.edit');
    });
