<?php

declare(strict_types=1);

/** @var \App\Core\Router $router */

use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\FeatureFlagMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\RoleMiddleware;

$staffRoles = ['staff', 'manager', 'executive', 'finance', 'loan_officer', 'welfare_officer', 'pr_officer', 'document_officer', 'complaint_officer', 'auditor', 'it_admin'];
$backofficeRoles = ['super_admin', ...$staffRoles];

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
$router->get('/', 'Public\\HomeController@index');
$router->get('/about', 'Public\\AboutController@index');
$router->get('/board', 'Public\\AboutController@board');
$router->get('/statistics', 'Public\\AboutController@statistics');

// Deposits
$router->get('/deposits', 'Public\\DepositController@index');
$router->get('/deposits/{slug}', 'Public\\DepositController@show');
$router->get('/rates', 'Public\\DepositController@index');

// Loans & Calculator
$router->get('/loans', 'Public\\LoanController@index');
$router->get('/calculator', 'Public\\CalculatorController@index');
$router->get('/loan-readiness', 'Public\\LoanChecklistController@index');
$router->get('/loans/checklist', 'Public\\LoanChecklistController@index');

// Welfare & Services
$router->get('/welfare', 'Public\\WelfareController@index');
$router->get('/eservice', 'Public\\EServiceController@index');

// Documents
$router->get('/documents', 'Public\\DocumentController@index');
$router->get('/documents/{id}/download', 'Public\\DocumentController@download');

// Announcements & News & Events
$router->get('/announcements', 'Public\\AnnouncementController@index');
$router->get('/announcements/{slug}', 'Public\\AnnouncementController@show');
$router->get('/calendar', 'Public\\EventController@index');
$router->get('/events', 'Public\\EventController@index');
$router->get('/news', 'Public\\NewsController@index');
$router->get('/news/{slug}', 'Public\\NewsController@show');

// Complaints
$router->get('/complaints', 'Public\\ComplaintController@index');
$router->post('/complaints/submit', 'Public\\ComplaintController@submit', [CsrfMiddleware::class]);
$router->get('/complaints/track', 'Public\\ComplaintController@track');

// Contact & FAQs
$router->get('/contact', 'Public\\ContactController@index');
$router->post('/contact/submit', 'Public\\ContactController@submit', [CsrfMiddleware::class]);
$router->get('/faqs', 'Public\\ContactController@faqs');

// Privacy & Terms & SEO
$router->get('/privacy/policy', 'Public\\PrivacyController@policy');
$router->get('/privacy/cookies', 'Public\\PrivacyController@cookies');
$router->get('/terms', 'Public\\PrivacyController@terms');
$router->get('/sitemap.xml', 'Public\\SitemapController@index');

// Public Dividend & Tax Verification & Surveys
$router->get('/dividend-estimator', 'Public\\DividendEstimatorController@index');
$router->get('/verify-tax-cert/{token}', 'Public\\TaxCertificateController@verify');
$router->get('/surveys/{slug}', 'Public\\SurveyController@show');
$router->post('/surveys/{slug}/submit', 'Public\\SurveyController@submit', [CsrfMiddleware::class]);

// APIs
$router->get('/csrf-token', function ($request, $response) {
    $response->json(['token' => \App\Core\Csrf::token()]);
});
$router->post('/api/cookie-consent', 'Public\\ApiController@logCookieConsent');
$router->post('/api/popups/event', 'Public\\ApiController@logPopupEvent');

/*
|--------------------------------------------------------------------------
| SPA REST API — Public Data (no auth required)
|--------------------------------------------------------------------------
*/
$router->get('/api/public/home', 'Api\\SpaApiController@home');
$router->get('/api/public/coop-info', 'Api\\SpaApiController@coopInfo');
$router->get('/api/public/interest-rates', 'Api\\SpaApiController@interestRates');
$router->get('/api/public/loan-products', 'Api\\SpaApiController@loanProducts');
$router->get('/api/public/news', 'Api\\SpaApiController@news');
$router->get('/api/public/announcements', 'Api\\SpaApiController@announcements');
$router->get('/api/public/documents', 'Api\\SpaApiController@documents');
$router->get('/api/public/welfare', 'Api\\SpaApiController@welfare');
$router->get('/api/public/faqs', 'Api\\SpaApiController@faqs');
$router->get('/api/public/board-members', 'Api\\SpaApiController@boardMembers');
$router->get('/api/public/statistics', 'Api\\SpaApiController@statistics');

/*
|--------------------------------------------------------------------------
| SPA REST API — Auth Check
|--------------------------------------------------------------------------
*/
$router->get('/api/auth/me', 'Api\\SpaApiController@me');
$router->get('/api/member/dividends', 'Member\\DividendController@index', [AuthMiddleware::class, new RoleMiddleware(['member'])]);
$router->post('/api/admin/dividends/preview', 'Admin\\DividendImportController@preview', [AuthMiddleware::class, new RoleMiddleware(['super_admin', 'member_admin']), CsrfMiddleware::class]);
$router->post('/api/admin/dividends/confirm', 'Admin\\DividendImportController@confirm', [AuthMiddleware::class, new RoleMiddleware(['super_admin', 'member_admin']), CsrfMiddleware::class]);
$router->get('/api/admin/members', 'Admin\\MemberManagementController@index', [AuthMiddleware::class, new RoleMiddleware(['super_admin', 'member_admin'])]);
$router->group(['prefix'=>'api/admin/members','middleware'=>[AuthMiddleware::class,new RoleMiddleware(['super_admin', 'member_admin'])]],function(\App\Core\Router $r){
    $r->get('/dashboard','Admin\\MemberManagementController@dashboard');
    $r->get('/health','Admin\\MemberManagementController@health');
    $r->get('/{id}','Admin\\MemberManagementController@show');
    $r->post('/{id}','Admin\\MemberManagementController@update',[CsrfMiddleware::class]);
    $r->post('/{id}/dividends/{year}','Admin\\MemberManagementController@updateDividend',[CsrfMiddleware::class]);
});

/*
|--------------------------------------------------------------------------
| SPA REST API — Member Data (auth + member role required)
|--------------------------------------------------------------------------
*/
$router->group(['prefix' => 'api/member', 'middleware' => [new FeatureFlagMiddleware('member_portal'), AuthMiddleware::class, new RoleMiddleware(['member'])]], function (\App\Core\Router $r) {
    $r->get('/dashboard', 'Api\\SpaApiController@memberDashboard');
    $r->get('/profile', 'Api\\SpaApiController@memberProfile');
    $r->get('/shares', 'Api\\SpaApiController@memberShares');
    $r->get('/deposits', 'Api\\SpaApiController@memberDeposits');
    $r->get('/loans', 'Api\\SpaApiController@memberLoans');
    $r->get('/notifications', 'Api\\SpaApiController@memberNotifications');
    $r->get('/receipts', 'Api\\SpaApiController@memberReceipts');
    $r->get('/online-services', 'Api\\SpaApiController@memberOnlineServices');
    $r->get('/financial-summary', 'Api\\SpaApiController@memberFinancialSummary');
    $r->get('/beneficiaries', 'Api\\SpaApiController@memberBeneficiaries');
    $r->get('/welfare', 'Api\\SpaApiController@memberWelfare');
});
/*
|--------------------------------------------------------------------------
| SPA REST API — Admin Data (auth + super_admin role required)
|--------------------------------------------------------------------------
*/
$router->group(['prefix' => 'api/admin', 'middleware' => [AuthMiddleware::class, new RoleMiddleware(['super_admin'])]], function (\App\Core\Router $r) {
    $r->get('/dashboard', 'Api\\SpaApiController@adminDashboard');
    $r->get('/users', 'Api\\SpaApiController@adminUsers');
    $r->get('/complaints', 'Api\\SpaApiController@adminComplaints');
    $r->get('/audit-logs', 'Api\\SpaApiController@adminAuditLogs');
    $r->get('/news', 'Api\\SpaApiController@adminNews');
    $r->post('/news', 'Api\\SpaApiController@createAdminNews', [CsrfMiddleware::class]);
    $r->post('/news/{id}/delete', 'Api\\SpaApiController@deleteAdminNews', [CsrfMiddleware::class]);
});

/*
|--------------------------------------------------------------------------
| SPA REST API — LED Member Check Module (CKAN Open Data Integration)
|--------------------------------------------------------------------------
*/
$router->group(['prefix' => 'api/admin/led', 'middleware' => [AuthMiddleware::class, new RoleMiddleware($backofficeRoles)]], function (\App\Core\Router $r) {
    // Dashboard & Schema Discovery
    $r->get('/dashboard', 'Api\\LedApiController@dashboard');
    $r->get('/schema', 'Api\\LedApiController@schema');
    $r->post('/schema/refresh', 'Api\\LedApiController@refreshSchema', [CsrfMiddleware::class]);

    // Member Search & Individual LED Check
    $r->get('/members/search', 'Api\\LedApiController@searchMembers', [new RateLimitMiddleware('led_search', 60, 60)]);
    $r->post('/check-member', 'Api\\LedApiController@checkMember', [CsrfMiddleware::class, new RateLimitMiddleware('led_check', 30, 60)]);

    // Review Queue & Human Verification Decision
    $r->get('/review', 'Api\\LedApiController@reviewQueue');
    $r->get('/review/{id}', 'Api\\LedApiController@reviewDetail');
    $r->post('/review/{id}', 'Api\\LedApiController@submitReview', [CsrfMiddleware::class]);

    // Batch Processing & Run History
    $r->post('/batch', 'Api\\LedApiController@startBatch', [CsrfMiddleware::class, new RateLimitMiddleware('led_batch', 10, 60)]);
    $r->get('/runs', 'Api\\LedApiController@runs');
    $r->get('/runs/{id}', 'Api\\LedApiController@runDetail');
});

/*
|--------------------------------------------------------------------------
| Authentication & Standard Dashboard Routes
|--------------------------------------------------------------------------
*/
$router->get('/login', 'Admin\\AuthController@showLogin');
$router->get('/admin/login', 'Admin\\AuthController@showLogin');
$router->post('/login', 'Admin\\AuthController@login', [CsrfMiddleware::class, new RateLimitMiddleware('login', (int) config('security.rate_limiting.login.max_attempts', 5), (int) config('security.rate_limiting.login.decay_seconds', 900))]);
$router->get('/admin/2fa', 'Admin\\AuthController@showTwoFactor');
$router->post('/admin/2fa', 'Admin\\AuthController@verifyTwoFactor', [CsrfMiddleware::class]);
$router->post('/logout', 'Admin\\AuthController@logout', [CsrfMiddleware::class]);
$router->post('/api/change-password', 'Admin\\AuthController@changePassword', [AuthMiddleware::class, CsrfMiddleware::class]);
$router->post('/change-password', 'Admin\\AuthController@changePassword', [AuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/dashboard', 'Admin\\DashboardController@index', [AuthMiddleware::class, new RoleMiddleware(['super_admin'])]);

$router->get('/portal', 'Member\\MemberPortalController@dashboard', [new FeatureFlagMiddleware('member_portal'), AuthMiddleware::class]);
$router->get('/member', function ($request, $response) {
    $response->redirect(url('member/dashboard'));
}, [new FeatureFlagMiddleware('member_portal')]);

// Public QR Code Receipt Verification
$router->get('/verify-receipt/{token}', 'PublicReceiptController@verify');

/*
|--------------------------------------------------------------------------
| Protected Member Portal Routes
|--------------------------------------------------------------------------
*/
$router->group(['prefix' => 'member', 'middleware' => [new FeatureFlagMiddleware('member_portal'), AuthMiddleware::class, new RoleMiddleware(['member'])]], function (\App\Core\Router $r) {
    $r->get('/dashboard', 'Member\\MemberPortalController@dashboard');
    $r->get('/profile', 'Member\\MemberPortalController@profile');
    $r->post('/profile/update', 'Member\\MemberPortalController@updateProfile', [CsrfMiddleware::class]);
    $r->get('/shares', 'Member\\MemberPortalController@shares');
    $r->post('/shares/change', 'Member\\MemberPortalController@submitShareChange', [CsrfMiddleware::class]);
    $r->get('/deposits', 'Member\\MemberPortalController@deposits');
    $r->get('/loans', 'Member\\MemberPortalController@loans');
    $r->get('/loan-apply', 'Member\\MemberPortalController@loanApplication');
    $r->post('/loan-apply', 'Member\\MemberPortalController@submitLoanApplication', [CsrfMiddleware::class]);
    $r->get('/welfare', 'Member\\MemberPortalController@welfare');
    $r->post('/welfare/claim', 'Member\\MemberPortalController@submitWelfareClaim', [CsrfMiddleware::class]);
    $r->get('/beneficiaries', 'Member\\MemberPortalController@beneficiaries');
    $r->post('/beneficiaries/save', 'Member\\MemberPortalController@saveBeneficiary', [CsrfMiddleware::class]);
    $r->get('/receipts', 'Member\\MemberPortalController@receipts');
    $r->get('/receipts/print/{no}', 'Member\\MemberPortalController@printReceipt');

    // Tax Certificates
    $r->get('/tax-certificates', 'Member\\TaxCertificateController@index');
    $r->get('/tax-certificates/print/{id}', 'Member\\TaxCertificateController@print');

    // Dividend Estimator
    $r->get('/dividend-estimator', 'Member\\DividendEstimatorController@index');

    // Member Surveys & Polls
    $r->get('/surveys', 'Member\\SurveyController@index');
    $r->get('/surveys/{id}', 'Member\\SurveyController@show');
    $r->post('/surveys/{id}/submit', 'Member\\SurveyController@submit', [CsrfMiddleware::class]);

    // Member Suggestions & Innovation Box
    $r->get('/suggestions', 'Member\\SuggestionController@index');
    $r->post('/suggestions/store', 'Member\\SuggestionController@store', [CsrfMiddleware::class]);

    $r->get('/notifications', 'Member\\MemberPortalController@notifications');
    $r->post('/notifications/read-all', 'Member\\MemberPortalController@markAllNotificationsRead');
    $r->get('/online-services', 'Member\\MemberPortalController@onlineServices');
    $r->get('/financial-summary', 'Member\\MemberPortalController@financialSummary');
    $r->get('/settings', 'Member\\MemberPortalController@settings');
    $r->post('/settings/password', 'Member\\MemberPortalController@changePassword', [CsrfMiddleware::class]);
    $r->post('/settings/line', 'Member\\MemberPortalController@toggleLine', [CsrfMiddleware::class]);
    $r->post('/settings/revoke-sessions', 'Member\\MemberPortalController@revokeSessions', [CsrfMiddleware::class]);
});

/*
|--------------------------------------------------------------------------
| Protected Staff Operations Routes
|--------------------------------------------------------------------------
*/
$router->group(['prefix' => 'staff', 'middleware' => [AuthMiddleware::class, new RoleMiddleware($staffRoles)]], function (\App\Core\Router $r) {
    $r->get('/dashboard', 'Staff\\StaffController@dashboard');
    $r->get('/members', 'Staff\\StaffController@members', [new FeatureFlagMiddleware('member_portal')]);
    $r->get('/members/detail', 'Staff\\StaffController@memberDetail', [new FeatureFlagMiddleware('member_portal')]);
    $r->get('/loans', 'Staff\\StaffController@loans');
    $r->get('/loans/document', 'Staff\\StaffController@viewLoanDocument');
    $r->post('/loans/review', 'Staff\\StaffController@reviewLoan', [CsrfMiddleware::class]);
    $r->get('/welfare', 'Staff\\StaffController@welfare');
    $r->post('/welfare/review', 'Staff\\StaffController@reviewWelfare', [CsrfMiddleware::class]);
    $r->get('/reports', 'Staff\\StaffController@reports');
    $r->get('/billing', 'Staff\\StaffController@billing');
    $r->post('/billing/generate', 'Staff\\StaffController@generateBatchBilling', [CsrfMiddleware::class]);
    $r->get('/import', 'Staff\\StaffController@import');
    $r->get('/import/template', 'Staff\\StaffController@downloadTemplate');
    $r->post('/import/preview', 'Staff\\StaffController@previewImport', [CsrfMiddleware::class]);
    $r->post('/import/process', 'Staff\\StaffController@processImport', [CsrfMiddleware::class]);
});

/*
|--------------------------------------------------------------------------
| Protected Admin Routes
|--------------------------------------------------------------------------
*/
$router->group(['prefix' => 'admin', 'middleware' => [AuthMiddleware::class, new RoleMiddleware(['super_admin'])]], function (\App\Core\Router $r) {
    // Dashboard
    $r->get('/dashboard', 'Admin\\DashboardController@index');
    $r->get('/executive', 'Admin\\DashboardController@executive');

    // News CRUD
    $r->get('/news', 'Admin\\NewsController@index');
    $r->get('/news/create', 'Admin\\NewsController@create');
    $r->post('/news/store', 'Admin\\NewsController@store', [CsrfMiddleware::class]);
    $r->get('/news/{id}/edit', 'Admin\\NewsController@edit');
    $r->post('/news/{id}/update', 'Admin\\NewsController@update', [CsrfMiddleware::class]);
    $r->post('/news/{id}/delete', 'Admin\\NewsController@destroy', [CsrfMiddleware::class]);

    // Important Announcements CRUD
    $r->get('/announcements', 'Admin\\AnnouncementController@index');
    $r->get('/announcements/create', 'Admin\\AnnouncementController@create');
    $r->post('/announcements/store', 'Admin\\AnnouncementController@store', [CsrfMiddleware::class]);
    $r->get('/announcements/{id}/edit', 'Admin\\AnnouncementController@edit');
    $r->post('/announcements/{id}/update', 'Admin\\AnnouncementController@update', [CsrfMiddleware::class]);
    $r->post('/announcements/{id}/delete', 'Admin\\AnnouncementController@destroy', [CsrfMiddleware::class]);

    // Events Calendar CRUD
    $r->get('/events', 'Admin\\EventController@index');
    $r->get('/events/create', 'Admin\\EventController@create');
    $r->post('/events/store', 'Admin\\EventController@store', [CsrfMiddleware::class]);
    $r->get('/events/{id}/edit', 'Admin\\EventController@edit');
    $r->post('/events/{id}/update', 'Admin\\EventController@update', [CsrfMiddleware::class]);
    $r->post('/events/{id}/delete', 'Admin\\EventController@destroy', [CsrfMiddleware::class]);

    // Contact Messages Management
    $r->get('/contact-messages', 'Admin\\ContactMessageController@index');
    $r->get('/contact-messages/{id}', 'Admin\\ContactMessageController@show');
    $r->post('/contact-messages/{id}/update', 'Admin\\ContactMessageController@updateStatus', [CsrfMiddleware::class]);
    $r->post('/contact-messages/{id}/delete', 'Admin\\ContactMessageController@destroy', [CsrfMiddleware::class]);

    // Member Surveys & Polls Management
    $r->get('/surveys', 'Admin\\SurveyController@index');
    $r->get('/surveys/create', 'Admin\\SurveyController@create');
    $r->post('/surveys/store', 'Admin\\SurveyController@store', [CsrfMiddleware::class]);
    $r->get('/surveys/{id}/results', 'Admin\\SurveyController@results');
    $r->post('/surveys/{id}/delete', 'Admin\\SurveyController@destroy', [CsrfMiddleware::class]);

    // Hero Slides
    $r->get('/hero-slides', 'Admin\\HeroSlideController@index');
    $r->post('/hero-slides/store', 'Admin\\HeroSlideController@store', [CsrfMiddleware::class]);
    $r->post('/hero-slides/{id}/update', 'Admin\\HeroSlideController@update', [CsrfMiddleware::class]);
    $r->post('/hero-slides/{id}/delete', 'Admin\\HeroSlideController@destroy', [CsrfMiddleware::class]);

    // Popups
    $r->get('/popups', 'Admin\\PopupController@index');
    $r->post('/popups/store', 'Admin\\PopupController@store', [CsrfMiddleware::class]);
    $r->post('/popups/{id}/delete', 'Admin\\PopupController@destroy', [CsrfMiddleware::class]);

    // Rates & History
    $r->get('/interest-rates', 'Admin\\InterestRateController@index');
    $r->post('/interest-rates/{id}/update', 'Admin\\InterestRateController@updateRate', [CsrfMiddleware::class]);

    // Deposits
    $r->get('/deposits', 'Admin\\DepositProductController@index');
    $r->post('/deposits/store', 'Admin\\DepositProductController@store', [CsrfMiddleware::class]);
    $r->post('/deposits/{id}/delete', 'Admin\\DepositProductController@destroy', [CsrfMiddleware::class]);

    // Loans
    $r->get('/loans', 'Admin\\LoanProductController@index');
    $r->post('/loans/store', 'Admin\\LoanProductController@store', [CsrfMiddleware::class]);
    $r->post('/loans/{id}/delete', 'Admin\\LoanProductController@destroy', [CsrfMiddleware::class]);

    // Welfare
    $r->get('/welfare', 'Admin\\WelfareController@index');
    $r->post('/welfare/store', 'Admin\\WelfareController@store', [CsrfMiddleware::class]);
    $r->post('/welfare/{id}/delete', 'Admin\\WelfareController@destroy', [CsrfMiddleware::class]);

    // Documents
    $r->get('/documents', 'Admin\\DocumentController@index');
    $r->post('/documents/store', 'Admin\\DocumentController@store', [CsrfMiddleware::class]);
    $r->post('/documents/{id}/delete', 'Admin\\DocumentController@destroy', [CsrfMiddleware::class]);

    // E-Services
    $r->get('/eservices', 'Admin\\EServiceController@index');
    $r->post('/eservices/store', 'Admin\\EServiceController@store', [CsrfMiddleware::class]);
    $r->post('/eservices/{id}/delete', 'Admin\\EServiceController@destroy', [CsrfMiddleware::class]);

    // Complaints
    $r->get('/complaints', 'Admin\\ComplaintController@index');
    $r->get('/complaints/{id}', 'Admin\\ComplaintController@show');
    $r->post('/complaints/{id}/update-status', 'Admin\\ComplaintController@updateStatus', [CsrfMiddleware::class]);

    // Member Suggestions Management
    $r->get('/suggestions', 'Admin\\SuggestionController@index');
    $r->get('/suggestions/{id}', 'Admin\\SuggestionController@show');
    $r->post('/suggestions/{id}/update-status', 'Admin\\SuggestionController@updateStatus', [CsrfMiddleware::class]);
    $r->post('/suggestions/{id}/delete', 'Admin\\SuggestionController@destroy', [CsrfMiddleware::class]);

    // Board & Staff
    $r->get('/board-staff', 'Admin\\BoardStaffController@index');
    $r->post('/board-staff/store-board', 'Admin\\BoardStaffController@storeBoard', [CsrfMiddleware::class]);
    $r->post('/board-staff/{id}/delete', 'Admin\\BoardStaffController@destroyBoard', [CsrfMiddleware::class]);

    // FAQs
    $r->get('/faqs', 'Admin\\FaqController@index');
    $r->post('/faqs/store', 'Admin\\FaqController@store', [CsrfMiddleware::class]);
    $r->post('/faqs/{id}/delete', 'Admin\\FaqController@destroy', [CsrfMiddleware::class]);

    // Media Library
    $r->get('/media', 'Admin\\MediaController@index');
    $r->post('/media/upload', 'Admin\\MediaController@upload', [CsrfMiddleware::class]);
    $r->post('/media/{id}/delete', 'Admin\\MediaController@destroy', [CsrfMiddleware::class]);

    // Privacy & Cookies
    $r->get('/privacy-cookies', 'Admin\\PrivacyCookieController@index');

    // Users & Roles
    $r->get('/users', 'Admin\\UserController@index');
    $r->post('/users/store', 'Admin\\UserController@store', [CsrfMiddleware::class]);
    $r->post('/users/{id}/delete', 'Admin\\UserController@destroy', [CsrfMiddleware::class]);

    // Audit Trail
    $r->get('/audit-logs', 'Admin\\AuditLogController@index');

    // Backups
    $r->get('/backups', 'Admin\BackupController@index');
    $r->post('/backups/create', 'Admin\BackupController@createBackup', [CsrfMiddleware::class]);
    $r->post('/backups/create-storage', 'Admin\BackupController@createStorageBackup', [CsrfMiddleware::class]);
    $r->post('/backups/create-full', 'Admin\BackupController@createFullBackup', [CsrfMiddleware::class]);
    $r->post('/backups/restore', 'Admin\BackupController@restoreBackup', [CsrfMiddleware::class]);
    $r->post('/backups/delete', 'Admin\BackupController@deleteBackup', [CsrfMiddleware::class]);
    $r->get('/backups/download', function($req, $res) {
        (new \App\Controllers\Admin\BackupController($req, $res))->download((string)$req->query('file'));
    });

    // Settings
    $r->get('/settings', 'Admin\\SettingController@index');
    $r->post('/settings/update', 'Admin\\SettingController@update', [CsrfMiddleware::class]);
});

/*
|--------------------------------------------------------------------------
| Protected LED Member Check Web Routes (Super Admin & Staff)
| Role Staff has full decision and view rights like admin in this section
|--------------------------------------------------------------------------
*/
$router->group(['prefix' => 'admin/led', 'middleware' => [AuthMiddleware::class, new RoleMiddleware($backofficeRoles)]], function (\App\Core\Router $r) {
    $r->get('', 'Admin\\LedController@index');
    $r->get('/dashboard', 'Admin\\LedController@dashboard');
    $r->get('/search', 'Admin\\LedController@search');
    $r->get('/review', 'Admin\\LedController@review');
    $r->get('/batch', 'Admin\\LedController@batch');
    $r->get('/schema', 'Admin\\LedController@schema');
});
