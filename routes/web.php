<?php

use App\Http\Controllers\AdminContentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\FacultyMemberController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/water.php';
require __DIR__.'/wallet.php';

Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/site-icon.png', \App\Http\Controllers\SiteIconController::class)->name('site.icon');
Route::get('/privacy', [\App\Http\Controllers\PrivacyDocumentationController::class, 'publicIndex'])->name('privacy.public');
Route::get('/policies/{slug}', [\App\Http\Controllers\PrivacyDocumentationController::class, 'published'])->where('slug', '[a-z-]+')->name('privacy.published');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordReset'])->middleware('throttle:security-form')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::get('/verify-new-email/{user}', [ProfileController::class, 'verifyEmail'])->name('profile.email.verify');
Route::get('/two-factor-challenge', [AuthController::class, 'showChallenge'])->name('two-factor.challenge');
Route::post('/two-factor-challenge', [AuthController::class, 'challenge'])->name('two-factor.challenge.store');

Route::middleware(['auth', 'account.usable'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/logout/forget-device', [AuthController::class, 'logoutAndForget'])->name('logout.forget-device');
});

Route::middleware(['auth', 'account.usable', 'onboarding'])->group(function () {
    Route::get('/onboarding/password', [OnboardingController::class, 'password'])->name('onboarding.password');
    Route::post('/onboarding/password', [OnboardingController::class, 'replacePassword'])->middleware('throttle:security-form')->name('onboarding.password.update');
    Route::get('/verify-email', [OnboardingController::class, 'notice'])->name('verification.notice');
    Route::post('/verify-email', [OnboardingController::class, 'send'])->middleware('throttle:3,1')->name('verification.send');
    Route::get('/verify-email/{id}/{hash}', [OnboardingController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [TwoFactorController::class, 'confirm'])->middleware('throttle:security-form')->name('two-factor.confirm');
    Route::get('/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
});

Route::middleware(['auth', 'account.usable', 'onboarding', 'two-factor.confirmed'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/dashboard/notifications/feed', [\App\Http\Controllers\NotificationController::class, 'feed'])->middleware('throttle:30,1')->name('notifications.feed');
    Route::post('/dashboard/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/dashboard/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/dashboard/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/dashboard/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/dashboard/security', [ProfileController::class, 'security'])->name('security.show');
    Route::get('/dashboard/privacy-security', [\App\Http\Controllers\PrivacyDocumentationController::class, 'index'])->name('privacy.index');
    Route::get('/dashboard/privacy-security/drafts/{slug}', [\App\Http\Controllers\PrivacyDocumentationController::class, 'draft'])
        ->middleware('role:admin')->where('slug', '[a-z-]+')->name('privacy.draft');
    Route::put('/dashboard/security/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:security-form')->name('security.password.update');
    Route::post('/dashboard/security/recovery-codes', [TwoFactorController::class, 'regenerate'])->middleware('throttle:security-form')->name('security.recovery-codes.regenerate');
    Route::delete('/dashboard/security/sessions/{session}', [ProfileController::class, 'revokeSession'])->name('security.sessions.destroy');
    Route::delete('/dashboard/security/sessions', [ProfileController::class, 'revokeOtherSessions'])->name('security.sessions.destroy-others');
    Route::delete('/dashboard/security/trusted-devices', [ProfileController::class, 'revokeOtherTrustedDevices'])->name('security.trusted-devices.destroy-others');
    Route::delete('/dashboard/security/trusted-devices/{trustedDevice}', [ProfileController::class, 'revokeTrustedDevice'])->name('security.trusted-devices.destroy');
    Route::get('/dashboard/collections', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/dashboard/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/dashboard/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
    Route::get('/dashboard/directory', [DirectoryController::class, 'index'])->middleware('permission:directory.view')->name('directory.index');
    Route::middleware('permission:payments.manage')->group(function () {
        Route::post('/dashboard/collections', [PaymentController::class, 'store'])->name('payments.store');
        Route::patch('/dashboard/collections/{payment}', [PaymentController::class, 'update'])->name('payments.update');
        Route::delete('/dashboard/collections/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
    });
    Route::middleware('permission:announcements.manage')->group(function () {
        Route::post('/dashboard/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::patch('/dashboard/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/dashboard/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard/content-manager', [AdminContentController::class, 'edit'])->name('admin.content.edit');
        Route::post('/dashboard/content-manager', [AdminContentController::class, 'update'])->name('admin.content.update');
        Route::post('/dashboard/content-manager/branding', [AdminContentController::class, 'updateBranding'])->middleware('throttle:security-form')->name('admin.content.branding');
        Route::delete('/dashboard/members/{member}/sessions', [FacultyMemberController::class, 'revokeAccess'])->name('members.sessions.revoke');
        Route::get('/dashboard/members', [FacultyMemberController::class, 'index'])->name('members.index');
        Route::get('/dashboard/members/create', [FacultyMemberController::class, 'create'])->name('members.create');
        Route::post('/dashboard/members', [FacultyMemberController::class, 'store'])->name('members.store');
        Route::get('/dashboard/members/{member}', [FacultyMemberController::class, 'show'])->name('members.show');
        Route::patch('/dashboard/members/{member}', [FacultyMemberController::class, 'update'])->name('members.update');
        Route::post('/dashboard/members/{member}/invitation', [FacultyMemberController::class, 'resendInvitation'])->middleware('throttle:security-form')->name('members.invitation');
        Route::post('/dashboard/members/{member}/password-reset', [FacultyMemberController::class, 'sendPasswordReset'])->middleware('throttle:security-form')->name('members.password-reset');
        Route::delete('/dashboard/members/{member}/two-factor', [FacultyMemberController::class, 'resetTwoFactor'])->middleware('throttle:security-form')->name('members.two-factor.reset');
    });
    Route::get('/dashboard/{module}', [DashboardController::class, 'comingSoon'])
        ->whereIn('module', [
            'inventory',
            'concerns',
            'reports',
            'ai-assistant',
            'settings',
        ])
        ->name('dashboard.module');
});
