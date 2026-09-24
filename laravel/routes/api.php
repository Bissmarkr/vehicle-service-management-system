<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MechanicController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceHistoryController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'VSMS Laravel API is running',
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('role:admin');
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->middleware('role:admin');
    Route::get('/customer/dashboard', [DashboardController::class, 'index'])->middleware('role:customer');
    Route::get('/customer/overview', [CustomerDashboardController::class, 'index'])->middleware('role:customer');
    Route::get('/mechanic/dashboard', [DashboardController::class, 'index'])->middleware('role:mechanic');
    Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('/reports/revenue', [ReportController::class, 'revenue']);
    Route::get('/reports/services', [ReportController::class, 'services']);

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('vehicles', VehicleController::class);
    Route::apiResource('services', ServiceTypeController::class);
    Route::apiResource('mechanics', MechanicController::class);
    Route::apiResource('spare-parts', SparePartController::class);
    Route::apiResource('bookings', BookingController::class);
    Route::post('/bookings/{booking}/assign-mechanic', [BookingController::class, 'assignMechanic']);
    Route::put('/bookings/{booking}/status', [BookingController::class, 'updateStatus']);
    Route::put('/bookings/{booking}/progress', [BookingController::class, 'progress'])->middleware('role:mechanic');

    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'store']);
    Route::post('/payments/create-checkout-session', [PaymentController::class, 'createCheckoutSession']);
    Route::post('/payments/create-remaining-checkout-session', [PaymentController::class, 'createRemainingCheckoutSession']);
    Route::get('/payments/status', [PaymentController::class, 'paymentStatus']);
    Route::get('/payments/my-payments', [PaymentController::class, 'myPayments'])->middleware('role:customer');
    Route::get('/admin/payment-requests', [PaymentController::class, 'adminPaymentRequests'])->middleware('role:admin');
    Route::get('/admin/payment-requests/{payment}', [PaymentController::class, 'adminPaymentRequest'])->middleware('role:admin');
    Route::post('/admin/payment-requests/{payment}/read', [PaymentController::class, 'markPaymentRequestRead'])->middleware('role:admin');
    Route::post('/admin/payment-requests/{payment}/approve', [PaymentController::class, 'approvePaymentRequest'])->middleware('role:admin');
    Route::post('/admin/payment-requests/{payment}/reject', [PaymentController::class, 'rejectPaymentRequest'])->middleware('role:admin');
    Route::apiResource('payments', PaymentController::class)->only(['index', 'show', 'store'])->middleware('role:admin');
    Route::get('/vehicles/{vehicle}/service-history', [ServiceHistoryController::class, 'forVehicle']);
    Route::get('/service-history', [ServiceHistoryController::class, 'index']);
});
