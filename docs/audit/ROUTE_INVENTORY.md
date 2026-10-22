# Route inventory — working tree 21 ต.ค. 2569

อ่านการลงทะเบียนจริงด้วย Router reflection โดยไม่ dispatch controller หรือเชื่อม DB
จำนวน 253 routes; เป็น effective middleware หลังรวม group แล้ว

| Method | Path | Handler | Effective middleware |
| --- | --- | --- | --- |
| GET | `/` | `Public\HomeController@index` | — |
| GET | `/about` | `Public\AboutController@index` | — |
| GET | `/board` | `Public\AboutController@board` | — |
| GET | `/statistics` | `Public\AboutController@statistics` | — |
| GET | `/deposits` | `Public\DepositController@index` | — |
| GET | `/deposits/{slug}` | `Public\DepositController@show` | — |
| GET | `/rates` | `Public\DepositController@index` | — |
| GET | `/loans` | `Public\LoanController@index` | — |
| GET | `/calculator` | `Public\CalculatorController@index` | — |
| GET | `/loan-readiness` | `Public\LoanChecklistController@index` | — |
| GET | `/loans/checklist` | `Public\LoanChecklistController@index` | — |
| GET | `/welfare` | `Public\WelfareController@index` | — |
| GET | `/eservice` | `Public\EServiceController@index` | — |
| GET | `/documents` | `Public\DocumentController@index` | — |
| GET | `/documents/{id}/download` | `Public\DocumentController@download` | — |
| GET | `/announcements` | `Public\AnnouncementController@index` | — |
| GET | `/announcements/{slug}` | `Public\AnnouncementController@show` | — |
| GET | `/calendar` | `Public\EventController@index` | — |
| GET | `/events` | `Public\EventController@index` | — |
| GET | `/news` | `Public\NewsController@index` | — |
| GET | `/news/{slug}` | `Public\NewsController@show` | — |
| GET | `/complaints` | `Public\ComplaintController@index` | — |
| POST | `/complaints/submit` | `Public\ComplaintController@submit` | `App\Middlewares\CsrfMiddleware` |
| GET | `/complaints/track` | `Public\ComplaintController@track` | — |
| GET | `/contact` | `Public\ContactController@index` | — |
| POST | `/contact/submit` | `Public\ContactController@submit` | `App\Middlewares\CsrfMiddleware` |
| GET | `/faqs` | `Public\ContactController@faqs` | — |
| GET | `/privacy/policy` | `Public\PrivacyController@policy` | — |
| GET | `/privacy/cookies` | `Public\PrivacyController@cookies` | — |
| GET | `/terms` | `Public\PrivacyController@terms` | — |
| GET | `/sitemap.xml` | `Public\SitemapController@index` | — |
| GET | `/dividend-estimator` | `Public\DividendEstimatorController@index` | — |
| GET | `/verify-tax-cert/{token}` | `Public\TaxCertificateController@verify` | — |
| GET | `/surveys/{slug}` | `Public\SurveyController@show` | — |
| POST | `/surveys/{slug}/submit` | `Public\SurveyController@submit` | `App\Middlewares\CsrfMiddleware` |
| GET | `/csrf-token` | `Closure` | — |
| POST | `/api/cookie-consent` | `Public\ApiController@logCookieConsent` | — |
| POST | `/api/popups/event` | `Public\ApiController@logPopupEvent` | — |
| GET | `/api/public/home` | `Api\SpaApiController@home` | — |
| GET | `/api/public/coop-info` | `Api\SpaApiController@coopInfo` | — |
| GET | `/api/public/interest-rates` | `Api\SpaApiController@interestRates` | — |
| GET | `/api/public/loan-products` | `Api\SpaApiController@loanProducts` | — |
| GET | `/api/public/news` | `Api\SpaApiController@news` | — |
| GET | `/api/public/announcements` | `Api\SpaApiController@announcements` | — |
| GET | `/api/public/documents` | `Api\SpaApiController@documents` | — |
| GET | `/api/public/welfare` | `Api\SpaApiController@welfare` | — |
| GET | `/api/public/faqs` | `Api\SpaApiController@faqs` | — |
| GET | `/api/public/board-members` | `Api\SpaApiController@boardMembers` | — |
| GET | `/api/public/statistics` | `Api\SpaApiController@statistics` | — |
| GET | `/api/auth/me` | `Api\SpaApiController@me` | — |
| GET | `/api/member/dividends` | `Member\DividendController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/requests` | `Api\SpaApiController@memberGetRequests` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| POST | `/api/member/requests/profile-change` | `Api\SpaApiController@memberSubmitProfileRequest` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/dividends/preview` | `Admin\DividendImportController@preview` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"dividends","action":"import"}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/dividends/confirm` | `Admin\DividendImportController@confirm` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"dividends","action":"import"}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/api/admin/members` | `Admin\MemberManagementController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"view"}` |
| GET | `/api/admin/members/dashboard` | `Admin\MemberManagementController@dashboard` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"view"}` |
| GET | `/api/admin/members/health` | `Admin\MemberManagementController@health` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"view"}` |
| GET | `/api/admin/members/{id}` | `Admin\MemberManagementController@show` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"view"}` |
| POST | `/api/admin/members/{id}` | `Admin\MemberManagementController@update` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"view"}`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"update"}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/members/{id}/dividends/{year}` | `Admin\MemberManagementController@updateDividend` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"view"}`<br>`App\Middlewares\PermissionMiddleware:{"module":"dividends","action":"update"}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/api/admin/members/requests` | `Admin\MemberManagementController@requests` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"view"}` |
| POST | `/api/admin/members/requests/{id}/approve` | `Admin\MemberManagementController@approveRequest` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"view"}`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"update"}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/members/requests/{id}/reject` | `Admin\MemberManagementController@rejectRequest` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"view"}`<br>`App\Middlewares\PermissionMiddleware:{"module":"members","action":"update"}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/api/member/dashboard` | `Api\SpaApiController@memberDashboard` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/profile` | `Api\SpaApiController@memberProfile` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/shares` | `Api\SpaApiController@memberShares` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/deposits` | `Api\SpaApiController@memberDeposits` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/loans` | `Api\SpaApiController@memberLoans` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/notifications` | `Api\SpaApiController@memberNotifications` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/receipts` | `Api\SpaApiController@memberReceipts` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/online-services` | `Api\SpaApiController@memberOnlineServices` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/financial-summary` | `Api\SpaApiController@memberFinancialSummary` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/beneficiaries` | `Api\SpaApiController@memberBeneficiaries` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/member/welfare` | `Api\SpaApiController@memberWelfare` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/api/admin/dashboard` | `Api\SpaApiController@adminDashboard` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/api/admin/users` | `Api\SpaApiController@adminUsers` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/api/admin/complaints` | `Api\SpaApiController@adminComplaints` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/api/admin/audit-logs` | `Api\SpaApiController@adminAuditLogs` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/api/admin/access/me` | `Admin\AccessController@me` | `App\Middlewares\AuthMiddleware` |
| GET | `/api/admin/news` | `Api\SpaApiController@adminNews` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"news","action":"view"}` |
| POST | `/api/admin/news` | `Api\SpaApiController@createAdminNews` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"news","action":"view"}`<br>`App\Middlewares\PermissionMiddleware:{"module":"news","action":"create"}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/news/{id}/update` | `Api\SpaApiController@updateAdminNews` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"news","action":"view"}`<br>`App\Middlewares\PermissionMiddleware:{"module":"news","action":"update"}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/news/{id}/delete` | `Api\SpaApiController@deleteAdminNews` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"news","action":"view"}`<br>`App\Middlewares\PermissionMiddleware:{"module":"news","action":"delete"}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/faqs` | `Api\SpaApiController@createAdminFaq` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"faqs","action":"view"}`<br>`App\Middlewares\PermissionMiddleware:{"module":"faqs","action":"create"}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/faqs/{id}/update` | `Api\SpaApiController@updateAdminFaq` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"faqs","action":"view"}`<br>`App\Middlewares\PermissionMiddleware:{"module":"faqs","action":"update"}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/faqs/{id}/delete` | `Api\SpaApiController@deleteAdminFaq` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\PermissionMiddleware:{"module":"faqs","action":"view"}`<br>`App\Middlewares\PermissionMiddleware:{"module":"faqs","action":"delete"}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/api/admin/access/roles` | `Admin\AccessController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/api/admin/access/roles` | `Admin\AccessController@createRole` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/access/roles/{id}/update` | `Admin\AccessController@updateRole` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/access/roles/{id}/delete` | `Admin\AccessController@deleteRole` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/api/admin/access/users` | `Admin\AccessController@users` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/api/admin/access/users` | `Admin\AccessController@createUser` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/access/users/{id}/update` | `Admin\AccessController@updateUser` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/access/users/{id}/delete` | `Admin\AccessController@deleteUser` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/api/admin/led/dashboard` | `Api\LedApiController@dashboard` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/api/admin/led/schema` | `Api\LedApiController@schema` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| POST | `/api/admin/led/schema/refresh` | `Api\LedApiController@refreshSchema` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/api/admin/led/members/search` | `Api\LedApiController@searchMembers` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\RateLimitMiddleware:{"key":"led_search","maxAttempts":60,"decaySeconds":60}` |
| POST | `/api/admin/led/check-member` | `Api\LedApiController@checkMember` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\CsrfMiddleware`<br>`App\Middlewares\RateLimitMiddleware:{"key":"led_check","maxAttempts":30,"decaySeconds":60}` |
| GET | `/api/admin/led/review` | `Api\LedApiController@reviewQueue` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/api/admin/led/review/{id}` | `Api\LedApiController@reviewDetail` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| POST | `/api/admin/led/review/{id}` | `Api\LedApiController@submitReview` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/api/admin/led/batch` | `Api\LedApiController@startBatch` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\CsrfMiddleware`<br>`App\Middlewares\RateLimitMiddleware:{"key":"led_batch","maxAttempts":10,"decaySeconds":60}` |
| GET | `/api/admin/led/runs` | `Api\LedApiController@runs` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/api/admin/led/runs/{id}` | `Api\LedApiController@runDetail` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/login` | `Admin\AuthController@showLogin` | — |
| GET | `/admin/login` | `Admin\AuthController@showLogin` | — |
| POST | `/login` | `Admin\AuthController@login` | `App\Middlewares\CsrfMiddleware`<br>`App\Middlewares\RateLimitMiddleware:{"key":"login","maxAttempts":5,"decaySeconds":900}` |
| GET | `/admin/2fa` | `Admin\AuthController@showTwoFactor` | — |
| POST | `/admin/2fa` | `Admin\AuthController@verifyTwoFactor` | `App\Middlewares\CsrfMiddleware` |
| POST | `/logout` | `Admin\AuthController@logout` | `App\Middlewares\CsrfMiddleware` |
| POST | `/api/change-password` | `Admin\AuthController@changePassword` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/change-password` | `Admin\AuthController@changePassword` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/dashboard` | `Admin\DashboardController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/portal` | `Member\MemberPortalController@dashboard` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware` |
| GET | `/member` | `Closure` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}` |
| GET | `/verify-receipt/{token}` | `PublicReceiptController@verify` | — |
| GET | `/member/dashboard` | `Member\MemberPortalController@dashboard` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/profile` | `Member\MemberPortalController@profile` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| POST | `/member/profile/update` | `Member\MemberPortalController@updateProfile` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/member/shares` | `Member\MemberPortalController@shares` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| POST | `/member/shares/change` | `Member\MemberPortalController@submitShareChange` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/member/deposits` | `Member\MemberPortalController@deposits` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/loans` | `Member\MemberPortalController@loans` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/loan-apply` | `Member\MemberPortalController@loanApplication` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| POST | `/member/loan-apply` | `Member\MemberPortalController@submitLoanApplication` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/member/welfare` | `Member\MemberPortalController@welfare` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| POST | `/member/welfare/claim` | `Member\MemberPortalController@submitWelfareClaim` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/member/beneficiaries` | `Member\MemberPortalController@beneficiaries` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| POST | `/member/beneficiaries/save` | `Member\MemberPortalController@saveBeneficiary` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/member/receipts` | `Member\MemberPortalController@receipts` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/receipts/print/{no}` | `Member\MemberPortalController@printReceipt` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/tax-certificates` | `Member\TaxCertificateController@index` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/tax-certificates/print/{id}` | `Member\TaxCertificateController@print` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/dividend-estimator` | `Member\DividendEstimatorController@index` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/surveys` | `Member\SurveyController@index` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/surveys/{id}` | `Member\SurveyController@show` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| POST | `/member/surveys/{id}/submit` | `Member\SurveyController@submit` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/member/suggestions` | `Member\SuggestionController@index` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| POST | `/member/suggestions/store` | `Member\SuggestionController@store` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/member/notifications` | `Member\MemberPortalController@notifications` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| POST | `/member/notifications/read-all` | `Member\MemberPortalController@markAllNotificationsRead` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/online-services` | `Member\MemberPortalController@onlineServices` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/financial-summary` | `Member\MemberPortalController@financialSummary` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| GET | `/member/settings` | `Member\MemberPortalController@settings` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}` |
| POST | `/member/settings/password` | `Member\MemberPortalController@changePassword` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/member/settings/line` | `Member\MemberPortalController@toggleLine` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/member/settings/revoke-sessions` | `Member\MemberPortalController@revokeSessions` | `App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}`<br>`App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["member"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/staff/dashboard` | `Staff\StaffController@dashboard` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/staff/members` | `Staff\StaffController@members` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}` |
| GET | `/staff/members/detail` | `Staff\StaffController@memberDetail` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\FeatureFlagMiddleware:{"feature":"member_portal"}` |
| GET | `/staff/loans` | `Staff\StaffController@loans` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/staff/loans/document` | `Staff\StaffController@viewLoanDocument` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| POST | `/staff/loans/review` | `Staff\StaffController@reviewLoan` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/staff/welfare` | `Staff\StaffController@welfare` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| POST | `/staff/welfare/review` | `Staff\StaffController@reviewWelfare` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/staff/reports` | `Staff\StaffController@reports` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/staff/billing` | `Staff\StaffController@billing` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| POST | `/staff/billing/generate` | `Staff\StaffController@generateBatchBilling` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/staff/import` | `Staff\StaffController@import` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/staff/import/template` | `Staff\StaffController@downloadTemplate` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| POST | `/staff/import/preview` | `Staff\StaffController@previewImport` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/staff/import/process` | `Staff\StaffController@processImport` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/dashboard` | `Admin\DashboardController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/executive` | `Admin\DashboardController@executive` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/news` | `Admin\NewsController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/news/create` | `Admin\NewsController@create` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/news/store` | `Admin\NewsController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/news/{id}/edit` | `Admin\NewsController@edit` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/news/{id}/update` | `Admin\NewsController@update` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/news/{id}/delete` | `Admin\NewsController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/announcements` | `Admin\AnnouncementController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/announcements/create` | `Admin\AnnouncementController@create` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/announcements/store` | `Admin\AnnouncementController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/announcements/{id}/edit` | `Admin\AnnouncementController@edit` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/announcements/{id}/update` | `Admin\AnnouncementController@update` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/announcements/{id}/delete` | `Admin\AnnouncementController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/events` | `Admin\EventController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/events/create` | `Admin\EventController@create` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/events/store` | `Admin\EventController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/events/{id}/edit` | `Admin\EventController@edit` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/events/{id}/update` | `Admin\EventController@update` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/events/{id}/delete` | `Admin\EventController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/contact-messages` | `Admin\ContactMessageController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/contact-messages/{id}` | `Admin\ContactMessageController@show` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/contact-messages/{id}/update` | `Admin\ContactMessageController@updateStatus` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/contact-messages/{id}/delete` | `Admin\ContactMessageController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/surveys` | `Admin\SurveyController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/surveys/create` | `Admin\SurveyController@create` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/surveys/store` | `Admin\SurveyController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/surveys/{id}/results` | `Admin\SurveyController@results` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/surveys/{id}/delete` | `Admin\SurveyController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/hero-slides` | `Admin\HeroSlideController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/hero-slides/store` | `Admin\HeroSlideController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/hero-slides/{id}/update` | `Admin\HeroSlideController@update` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/hero-slides/{id}/delete` | `Admin\HeroSlideController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/popups` | `Admin\PopupController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/popups/store` | `Admin\PopupController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/popups/{id}/delete` | `Admin\PopupController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/interest-rates` | `Admin\InterestRateController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/interest-rates/{id}/update` | `Admin\InterestRateController@updateRate` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/deposits` | `Admin\DepositProductController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/deposits/store` | `Admin\DepositProductController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/deposits/{id}/delete` | `Admin\DepositProductController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/loans` | `Admin\LoanProductController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/loans/store` | `Admin\LoanProductController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/loans/{id}/delete` | `Admin\LoanProductController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/welfare` | `Admin\WelfareController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/welfare/store` | `Admin\WelfareController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/welfare/{id}/delete` | `Admin\WelfareController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/documents` | `Admin\DocumentController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/documents/store` | `Admin\DocumentController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/documents/{id}/delete` | `Admin\DocumentController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/eservices` | `Admin\EServiceController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/eservices/store` | `Admin\EServiceController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/eservices/{id}/delete` | `Admin\EServiceController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/complaints` | `Admin\ComplaintController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/complaints/{id}` | `Admin\ComplaintController@show` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/complaints/{id}/update-status` | `Admin\ComplaintController@updateStatus` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/suggestions` | `Admin\SuggestionController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/suggestions/{id}` | `Admin\SuggestionController@show` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/suggestions/{id}/update-status` | `Admin\SuggestionController@updateStatus` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/suggestions/{id}/delete` | `Admin\SuggestionController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/board-staff` | `Admin\BoardStaffController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/board-staff/store-board` | `Admin\BoardStaffController@storeBoard` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/board-staff/{id}/delete` | `Admin\BoardStaffController@destroyBoard` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/faqs` | `Admin\FaqController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/faqs/store` | `Admin\FaqController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/faqs/{id}/delete` | `Admin\FaqController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/media` | `Admin\MediaController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/media/upload` | `Admin\MediaController@upload` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/media/{id}/delete` | `Admin\MediaController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/privacy-cookies` | `Admin\PrivacyCookieController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/users` | `Admin\UserController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/users/store` | `Admin\UserController@store` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/users/{id}/delete` | `Admin\UserController@destroy` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/audit-logs` | `Admin\AuditLogController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/backups` | `Admin\BackupController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/backups/create` | `Admin\BackupController@createBackup` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/backups/create-storage` | `Admin\BackupController@createStorageBackup` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/backups/create-full` | `Admin\BackupController@createFullBackup` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/backups/restore` | `Admin\BackupController@restoreBackup` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| POST | `/admin/backups/delete` | `Admin\BackupController@deleteBackup` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/backups/download` | `Closure` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| GET | `/admin/settings` | `Admin\SettingController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}` |
| POST | `/admin/settings/update` | `Admin\SettingController@update` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin"]}`<br>`App\Middlewares\CsrfMiddleware` |
| GET | `/admin/led/` | `Admin\LedController@index` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/admin/led/dashboard` | `Admin\LedController@dashboard` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/admin/led/search` | `Admin\LedController@search` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/admin/led/review` | `Admin\LedController@review` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/admin/led/batch` | `Admin\LedController@batch` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |
| GET | `/admin/led/schema` | `Admin\LedController@schema` | `App\Middlewares\AuthMiddleware`<br>`App\Middlewares\RoleMiddleware:{"allowedRoles":["super_admin","staff","manager","executive","finance","loan_officer","welfare_officer","pr_officer","document_officer","complaint_officer","auditor","it_admin"]}` |

## React route declarations

```text
183: <Route path="/" element={<HomePage />} />
192: <Route path="/verify-receipt" element={<VerifyReceiptPage />} />
193: <Route path="/verify-receipt/:token" element={<VerifyReceiptPage />} />
195: {memberRoutes.map((path) => <Route key={path} path={path} element={memberPortalEnabled ? <MemberDashboardPage /> : <ServiceUnavailablePage />} />)}
196: {profileRoutes.map((path) => <Route key={path} path={path} element={memberPortalEnabled ? <ProfilePage /> : <ServiceUnavailablePage />} />)}
197: <Route path="/service-unavailable" element={<ServiceUnavailablePage />} />
198: <Route path="/staff" element={<StaffProtectedRoute roles={staffRoles}><AdminDashboardPage /></StaffProtectedRoute>} />
199: <Route path="/staff/dashboard" element={<StaffProtectedRoute roles={staffRoles}><AdminDashboardPage /></StaffProtectedRoute>} />
200: {adminRoutes.map((path) => <Route key={path} path={path} element={<StaffProtectedRoute roles={['super_admin']}><AdminDashboardPage /></StaffProtectedRoute>} />)}
201: <Route path="/admin/access" element={<StaffProtectedRoute roles={['super_admin']}><AdminAccessPage /></StaffProtectedRoute>} />
202: <Route path="/admin/workspace" element={<StaffProtectedRoute><AdminWorkspacePage /></StaffProtectedRoute>} />
205: <Route path="/admin/dividends/import" element={<StaffProtectedRoute permission="dividends.import"><AdminDividendImportPage /></StaffProtectedRoute>} />
206: <Route path="/admin/members/dashboard" element={<StaffProtectedRoute permission="members.view"><AdminMembersDashboardPage /></StaffProtectedRoute>} />
207: <Route path="/admin/members" element={<StaffProtectedRoute permission="members.view"><AdminMembersPage /></StaffProtectedRoute>} />
208: <Route path="/admin/members/health" element={<StaffProtectedRoute permission="members.view"><AdminMembersHealthPage /></StaffProtectedRoute>} />
209: <Route path="/admin/members/:id" element={<StaffProtectedRoute permission="members.view"><AdminMemberDetailPage /></StaffProtectedRoute>} />
210: <Route path="/admin/led" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedDashboardPage /></StaffProtectedRoute>} />
211: <Route path="/admin/led/dashboard" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedDashboardPage /></StaffProtectedRoute>} />
212: <Route path="/admin/led/search" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedSearchPage /></StaffProtectedRoute>} />
213: <Route path="/admin/led/review" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedReviewPage /></StaffProtectedRoute>} />
214: <Route path="/admin/led/batch" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedBatchPage /></StaffProtectedRoute>} />
215: <Route path="/admin/led/schema" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedSchemaPage /></StaffProtectedRoute>} />
217: <Route path="/login" element={<LoginPage />} />
218: <Route path="/member/login" element={<LoginPage />} />
219: <Route path="/member/dividends" element={<MemberDividendsPage />} />
220: <Route path="/admin/login" element={<LoginPage />} />
222: <Route path="*" element={<NotFoundPage />} />
```
