<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\PublicInvitationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SantriController;
use App\Http\Controllers\Api\ScannerController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;

/*
|--------------------------------------------------------------------------
| Invitaku REST API
|--------------------------------------------------------------------------
*/

// --- Public ---
Route::post('/login', [AuthController::class, 'login']);

Route::get('/public/invitations/{token}', [PublicInvitationController::class, 'show']);
Route::get('/public/invitations/{token}/qr', [PublicInvitationController::class, 'qr']);

// WhatsApp Business Cloud API webhook (verification + delivery status)
Route::get('/webhooks/whatsapp', [WebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WebhookController::class, 'receive']);

// --- Authenticated ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Shared read-only resources (admin + scanner)
    Route::middleware('role:admin,scanner')->group(function () {
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::get('/template-categories', [TemplateController::class, 'categories']);
        Route::get('/templates/{idOrSlug}', [TemplateController::class, 'show']);

        Route::get('/scanner/events', [ScannerController::class, 'events']);

        // Read-only santri mode data for the scanner dashboard.
        Route::get('/santri/overview', [SantriController::class, 'overview']);
        Route::get('/santri/guests', [SantriController::class, 'index']);
    });

    // Scanner endpoints (admin + scanner), rate limited
    Route::middleware(['role:admin,scanner', 'throttle:scanner'])->group(function () {
        Route::post('/scanner/validate', [ScannerController::class, 'validateToken']);
        Route::post('/scanner/check-in', [ScannerController::class, 'checkIn']);

        // Simple santri-mode scanner (one automatic event, code or token input)
        Route::post('/santri/scanner/validate', [SantriController::class, 'validateScan']);
        Route::post('/santri/scanner/check-in', [SantriController::class, 'checkIn']);
    });

    // --- Admin only ---
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Simple santri mode (uses the existing events/guests/invitations tables)
        Route::post('/santri/guests', [SantriController::class, 'store']);
        Route::put('/santri/guests/{guest}', [SantriController::class, 'update']);
        Route::delete('/santri/guests/{guest}', [SantriController::class, 'destroy']);
        Route::post('/santri/guests/{guest}/toggle-attendance', [SantriController::class, 'toggleAttendance']);

        // Events
        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::get('/events/{event}', [EventController::class, 'show']);
        Route::put('/events/{event}', [EventController::class, 'update']);
        Route::delete('/events/{event}', [EventController::class, 'destroy']);
        Route::post('/events/{event}/banner', [EventController::class, 'uploadBanner']);

        // Guests
        Route::get('/events/{event}/guests', [GuestController::class, 'index']);
        Route::post('/events/{event}/guests', [GuestController::class, 'store']);
        Route::post('/events/{event}/guests/import', [GuestController::class, 'import']);
        Route::put('/guests/{guest}', [GuestController::class, 'update']);
        Route::delete('/guests/{guest}', [GuestController::class, 'destroy']);

        // Invitations
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::get('/events/{event}/invitations', [InvitationController::class, 'forEvent']);
        Route::get('/guests/{guest}/invitation', [InvitationController::class, 'show']);
        Route::post('/guests/{guest}/generate-invitation', [InvitationController::class, 'generate']);
        Route::post('/guests/{guest}/send-whatsapp', [InvitationController::class, 'sendWhatsApp']);
        Route::post('/invitations/{invitation}/mark-sent', [InvitationController::class, 'markSent']);

        // Reports
        Route::get('/events/{event}/attendance', [ReportController::class, 'attendance']);
        Route::get('/events/{event}/attendance/export', [ReportController::class, 'export']);
        Route::get('/reports/summary', [ReportController::class, 'summary']);

        // Officers / users
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});
