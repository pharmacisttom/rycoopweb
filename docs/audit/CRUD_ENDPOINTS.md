# Admin endpoint source inventory

Generated with `php scripts/audit-crud-inventory.php`. Source registration is not proof of working CRUD. Tables/services are controller-level references, not runtime query traces.

| Method | URL | Handler | Middleware | Controller references |
|---|---|---|---|---|
| POST | `/api/admin/dividends/preview` | `Admin\DividendImportController@preview` | AuthMiddleware, PermissionMiddleware, CsrfMiddleware | DividendImportService, MemberDataException, MemberSystemHealth |
| POST | `/api/admin/dividends/confirm` | `Admin\DividendImportController@confirm` | AuthMiddleware, PermissionMiddleware, CsrfMiddleware | DividendImportService, MemberDataException, MemberSystemHealth |
| GET | `/api/admin/members` | `Admin\MemberManagementController@index` | AuthMiddleware, PermissionMiddleware | members, member_dividends, member_import_runs, users, member_data_changes, MemberAdminService, MemberDataException, MemberSchemaException, MemberSystemHealth |
| GET | `/api/admin/members/dashboard` | `Admin\MemberManagementController@dashboard` | AuthMiddleware, PermissionMiddleware | members, member_dividends, member_import_runs, users, member_data_changes, MemberAdminService, MemberDataException, MemberSchemaException, MemberSystemHealth |
| GET | `/api/admin/members/health` | `Admin\MemberManagementController@health` | AuthMiddleware, PermissionMiddleware | members, member_dividends, member_import_runs, users, member_data_changes, MemberAdminService, MemberDataException, MemberSchemaException, MemberSystemHealth |
| GET | `/api/admin/members/requests` | `Admin\MemberManagementController@requests` | AuthMiddleware, PermissionMiddleware | members, member_dividends, member_import_runs, users, member_data_changes, MemberAdminService, MemberDataException, MemberSchemaException, MemberSystemHealth |
| GET | `/api/admin/members/{id}` | `Admin\MemberManagementController@show` | AuthMiddleware, PermissionMiddleware | members, member_dividends, member_import_runs, users, member_data_changes, MemberAdminService, MemberDataException, MemberSchemaException, MemberSystemHealth |
| POST | `/api/admin/members/{id}` | `Admin\MemberManagementController@update` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | members, member_dividends, member_import_runs, users, member_data_changes, MemberAdminService, MemberDataException, MemberSchemaException, MemberSystemHealth |
| POST | `/api/admin/members/{id}/dividends/{year}` | `Admin\MemberManagementController@updateDividend` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | members, member_dividends, member_import_runs, users, member_data_changes, MemberAdminService, MemberDataException, MemberSchemaException, MemberSystemHealth |
| POST | `/api/admin/members/requests/{id}/approve` | `Admin\MemberManagementController@approveRequest` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | members, member_dividends, member_import_runs, users, member_data_changes, MemberAdminService, MemberDataException, MemberSchemaException, MemberSystemHealth |
| POST | `/api/admin/members/requests/{id}/reject` | `Admin\MemberManagementController@rejectRequest` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | members, member_dividends, member_import_runs, users, member_data_changes, MemberAdminService, MemberDataException, MemberSchemaException, MemberSystemHealth |
| GET | `/api/admin/dashboard` | `Api\SpaApiController@adminDashboard` | AuthMiddleware, RoleMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| GET | `/api/admin/users` | `Api\SpaApiController@adminUsers` | AuthMiddleware, RoleMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| GET | `/api/admin/complaints` | `Api\SpaApiController@adminComplaints` | AuthMiddleware, RoleMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| GET | `/api/admin/audit-logs` | `Api\SpaApiController@adminAuditLogs` | AuthMiddleware, RoleMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| GET | `/api/admin/rates` | `Api\SpaApiController@adminRates` | AuthMiddleware, RoleMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| POST | `/api/admin/rates/{id}` | `Api\SpaApiController@updateAdminRate` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| POST | `/api/admin/loan-products/{id}` | `Api\SpaApiController@updateAdminLoanProduct` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| GET | `/api/admin/access/me` | `Admin\AccessController@me` | AuthMiddleware | roles, members, users, AccessService, AdminControlCenterService, DualControlSecurityService |
| GET | `/api/admin/dashboard/summary` | `Admin\ControlCenterApiController@summary` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/dashboard/trends` | `Admin\ControlCenterApiController@trends` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/analytics/usage` | `Admin\ControlCenterApiController@usage` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/analytics/visitors` | `Admin\ControlCenterApiController@visitorAnalytics` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| POST | `/api/admin/analytics/visitors/export` | `Admin\ControlCenterApiController@exportVisitorAnalytics` | AuthMiddleware, CsrfMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/operations/overview` | `Admin\ControlCenterApiController@operations` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/security/overview` | `Admin\ControlCenterApiController@securityOverview` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/security/sessions` | `Admin\ControlCenterApiController@securitySessions` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/security/mfa` | `Admin\ControlCenterApiController@securityMfa` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/audit-logs/center` | `Admin\ControlCenterApiController@auditLogs` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/backups` | `Admin\ControlCenterApiController@backups` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/backups/health` | `Admin\ControlCenterApiController@backupsHealth` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| POST | `/api/admin/backups/restore-request` | `Admin\ControlCenterApiController@createRestoreRequest` | AuthMiddleware, CsrfMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| POST | `/api/admin/backups/restore-execute` | `Admin\ControlCenterApiController@executeRestore` | AuthMiddleware, CsrfMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/approvals` | `Admin\ControlCenterApiController@approvalsList` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| POST | `/api/admin/approvals` | `Admin\ControlCenterApiController@createApprovalRequest` | AuthMiddleware, CsrfMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| POST | `/api/admin/approvals/{id}/approve` | `Admin\ControlCenterApiController@approveRequest` | AuthMiddleware, CsrfMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| POST | `/api/admin/approvals/{id}/reject` | `Admin\ControlCenterApiController@rejectRequest` | AuthMiddleware, CsrfMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| POST | `/api/admin/approvals/{id}/cancel` | `Admin\ControlCenterApiController@cancelRequest` | AuthMiddleware, CsrfMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/system/health` | `Admin\ControlCenterApiController@systemHealth` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/access/permissions` | `Admin\ControlCenterApiController@accessPermissions` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/departments` | `Admin\ControlCenterApiController@departments` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/reports/catalog` | `Admin\ControlCenterApiController@reportsCatalog` | AuthMiddleware | financial_statistics, member_dividends, AdminControlCenterService, AuditService, VisitorCounterService |
| GET | `/api/admin/announcements` | `Admin\AnnouncementController@index` | AuthMiddleware, PermissionMiddleware | important_announcements, AuditService, MediaService |
| POST | `/api/admin/announcements` | `Admin\AnnouncementController@store` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | important_announcements, AuditService, MediaService |
| POST | `/api/admin/announcements/{id}/update` | `Admin\AnnouncementController@update` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | important_announcements, AuditService, MediaService |
| GET | `/api/admin/news` | `Api\SpaApiController@adminNews` | AuthMiddleware, PermissionMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| POST | `/api/admin/news` | `Api\SpaApiController@createAdminNews` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| POST | `/api/admin/news/{id}/update` | `Api\SpaApiController@updateAdminNews` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| POST | `/api/admin/news/{id}/publish` | `Admin\NewsWorkflowController@publish` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | NewsWorkflowService |
| POST | `/api/admin/news/{id}/delete` | `Api\SpaApiController@deleteAdminNews` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| POST | `/api/admin/faqs` | `Api\SpaApiController@createAdminFaq` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| POST | `/api/admin/faqs/{id}/update` | `Api\SpaApiController@updateAdminFaq` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| POST | `/api/admin/faqs/{id}/delete` | `Api\SpaApiController@deleteAdminFaq` | AuthMiddleware, PermissionMiddleware, PermissionMiddleware, CsrfMiddleware | hero_slides, interest_rates, news, news_categories, deposit_products, loan_products, financial_statistics, eservice_links, important_announcements, events, documents, welfare_types, faqs, share_change_requests, online_requests, welfare_applications, user_google_identities, users, members, audit_logs, complaints, user_roles, roles, interest_rate_history, the, member, MemberPortalService, AuditService |
| GET | `/api/admin/access/roles` | `Admin\AccessController@index` | AuthMiddleware, RoleMiddleware | roles, members, users, AccessService, AdminControlCenterService, DualControlSecurityService |
| POST | `/api/admin/access/roles` | `Admin\AccessController@createRole` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | roles, members, users, AccessService, AdminControlCenterService, DualControlSecurityService |
| POST | `/api/admin/access/roles/{id}/update` | `Admin\AccessController@updateRole` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | roles, members, users, AccessService, AdminControlCenterService, DualControlSecurityService |
| POST | `/api/admin/access/roles/{id}/delete` | `Admin\AccessController@deleteRole` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | roles, members, users, AccessService, AdminControlCenterService, DualControlSecurityService |
| GET | `/api/admin/access/users` | `Admin\AccessController@users` | AuthMiddleware, RoleMiddleware | roles, members, users, AccessService, AdminControlCenterService, DualControlSecurityService |
| POST | `/api/admin/access/users` | `Admin\AccessController@createUser` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | roles, members, users, AccessService, AdminControlCenterService, DualControlSecurityService |
| POST | `/api/admin/access/users/{id}/update` | `Admin\AccessController@updateUser` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | roles, members, users, AccessService, AdminControlCenterService, DualControlSecurityService |
| POST | `/api/admin/access/users/{id}/delete` | `Admin\AccessController@deleteUser` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | roles, members, users, AccessService, AdminControlCenterService, DualControlSecurityService |
| GET | `/api/admin/led/dashboard` | `Api\LedApiController@dashboard` | AuthMiddleware, RoleMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| GET | `/api/admin/led/schema` | `Api\LedApiController@schema` | AuthMiddleware, RoleMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| POST | `/api/admin/led/schema/refresh` | `Api\LedApiController@refreshSchema` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| GET | `/api/admin/led/members/search` | `Api\LedApiController@searchMembers` | AuthMiddleware, RoleMiddleware, RateLimitMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| POST | `/api/admin/led/check-member` | `Api\LedApiController@checkMember` | AuthMiddleware, RoleMiddleware, CsrfMiddleware, RateLimitMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| GET | `/api/admin/led/review` | `Api\LedApiController@reviewQueue` | AuthMiddleware, RoleMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| GET | `/api/admin/led/review/{id}` | `Api\LedApiController@reviewDetail` | AuthMiddleware, RoleMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| POST | `/api/admin/led/review/{id}` | `Api\LedApiController@submitReview` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| POST | `/api/admin/led/batch` | `Api\LedApiController@startBatch` | AuthMiddleware, RoleMiddleware, CsrfMiddleware, RateLimitMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| GET | `/api/admin/led/runs` | `Api\LedApiController@runs` | AuthMiddleware, RoleMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| GET | `/api/admin/led/runs/{id}` | `Api\LedApiController@runDetail` | AuthMiddleware, RoleMiddleware | members, led_check_results, led_check_runs, users, led_match_records, parent, this, AuditService, LedBatchService, LedMatcherService, LedSchemaService, LedService |
| GET | `/api/admin/staff` | `Admin\StaffManagementController@index` | AuthMiddleware, RoleMiddleware | users, user_roles, roles, user_google_identities, staff, staff_invitations, login_logs, AuditService, GoogleAuthService, StaffInvitationService, TwoFactorService |
| POST | `/api/admin/staff/invite` | `Admin\StaffManagementController@invite` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | users, user_roles, roles, user_google_identities, staff, staff_invitations, login_logs, AuditService, GoogleAuthService, StaffInvitationService, TwoFactorService |
| POST | `/api/admin/staff/invitations/{id}/revoke` | `Admin\StaffManagementController@revokeInvitation` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | users, user_roles, roles, user_google_identities, staff, staff_invitations, login_logs, AuditService, GoogleAuthService, StaffInvitationService, TwoFactorService |
| POST | `/api/admin/staff/invitations/{id}/resend` | `Admin\StaffManagementController@resendInvitation` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | users, user_roles, roles, user_google_identities, staff, staff_invitations, login_logs, AuditService, GoogleAuthService, StaffInvitationService, TwoFactorService |
| POST | `/api/admin/staff/{id}/suspend` | `Admin\StaffManagementController@suspend` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | users, user_roles, roles, user_google_identities, staff, staff_invitations, login_logs, AuditService, GoogleAuthService, StaffInvitationService, TwoFactorService |
| POST | `/api/admin/staff/{id}/activate` | `Admin\StaffManagementController@activate` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | users, user_roles, roles, user_google_identities, staff, staff_invitations, login_logs, AuditService, GoogleAuthService, StaffInvitationService, TwoFactorService |
| POST | `/api/admin/staff/{id}/role` | `Admin\StaffManagementController@updateRole` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | users, user_roles, roles, user_google_identities, staff, staff_invitations, login_logs, AuditService, GoogleAuthService, StaffInvitationService, TwoFactorService |
| POST | `/api/admin/staff/{id}/revoke-sessions` | `Admin\StaffManagementController@revokeSessions` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | users, user_roles, roles, user_google_identities, staff, staff_invitations, login_logs, AuditService, GoogleAuthService, StaffInvitationService, TwoFactorService |
| POST | `/api/admin/staff/{id}/relink` | `Admin\StaffManagementController@relink` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | users, user_roles, roles, user_google_identities, staff, staff_invitations, login_logs, AuditService, GoogleAuthService, StaffInvitationService, TwoFactorService |
| GET | `/api/admin/security/dashboard` | `Admin\StaffManagementController@securityDashboard` | AuthMiddleware, RoleMiddleware | users, user_roles, roles, user_google_identities, staff, staff_invitations, login_logs, AuditService, GoogleAuthService, StaffInvitationService, TwoFactorService |
| GET | `/admin/login` | `Admin\AuthController@showLogin` |  | users, user_roles, roles, members, login_logs, IP, password, AuditService, TwoFactorService |
| GET | `/admin/2fa` | `Admin\AuthController@showTwoFactor` |  | users, user_roles, roles, members, login_logs, IP, password, AuditService, TwoFactorService |
| POST | `/admin/2fa` | `Admin\AuthController@verifyTwoFactor` | CsrfMiddleware | users, user_roles, roles, members, login_logs, IP, password, AuditService, TwoFactorService |
| GET | `/staff/dashboard` | `Staff\StaffController@dashboard` | AuthMiddleware, RoleMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| GET | `/staff/members` | `Staff\StaffController@members` | AuthMiddleware, RoleMiddleware, FeatureFlagMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| GET | `/staff/members/detail` | `Staff\StaffController@memberDetail` | AuthMiddleware, RoleMiddleware, FeatureFlagMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| GET | `/staff/loans` | `Staff\StaffController@loans` | AuthMiddleware, RoleMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| GET | `/staff/loans/document` | `Staff\StaffController@viewLoanDocument` | AuthMiddleware, RoleMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| POST | `/staff/loans/review` | `Staff\StaffController@reviewLoan` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| GET | `/staff/welfare` | `Staff\StaffController@welfare` | AuthMiddleware, RoleMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| POST | `/staff/welfare/review` | `Staff\StaffController@reviewWelfare` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| GET | `/staff/reports` | `Staff\StaffController@reports` | AuthMiddleware, RoleMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| GET | `/staff/billing` | `Staff\StaffController@billing` | AuthMiddleware, RoleMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| POST | `/staff/billing/generate` | `Staff\StaffController@generateBatchBilling` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| GET | `/staff/import` | `Staff\StaffController@import` | AuthMiddleware, RoleMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| GET | `/staff/import/template` | `Staff\StaffController@downloadTemplate` | AuthMiddleware, RoleMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| POST | `/staff/import/preview` | `Staff\StaffController@previewImport` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| POST | `/staff/import/process` | `Staff\StaffController@processImport` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | members, welfare_applications, receipts, AuditService, StaffService |
| GET | `/admin/dashboard` | `Admin\DashboardController@index` | AuthMiddleware, RoleMiddleware | news, popups, hero_slides, documents, complaints, users, audit_logs, financial_statistics |
| GET | `/admin/executive` | `Admin\DashboardController@executive` | AuthMiddleware, RoleMiddleware | news, popups, hero_slides, documents, complaints, users, audit_logs, financial_statistics |
| GET | `/admin/news` | `Admin\NewsController@index` | AuthMiddleware, RoleMiddleware | news, news_categories, users, AuditService |
| GET | `/admin/news/create` | `Admin\NewsController@create` | AuthMiddleware, RoleMiddleware | news, news_categories, users, AuditService |
| POST | `/admin/news/store` | `Admin\NewsController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | news, news_categories, users, AuditService |
| GET | `/admin/news/{id}/edit` | `Admin\NewsController@edit` | AuthMiddleware, RoleMiddleware | news, news_categories, users, AuditService |
| POST | `/admin/news/{id}/update` | `Admin\NewsController@update` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | news, news_categories, users, AuditService |
| POST | `/admin/news/{id}/delete` | `Admin\NewsController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | news, news_categories, users, AuditService |
| GET | `/admin/announcements` | `Admin\AnnouncementController@index` | AuthMiddleware, RoleMiddleware | important_announcements, AuditService, MediaService |
| GET | `/admin/announcements/create` | `Admin\AnnouncementController@create` | AuthMiddleware, RoleMiddleware | important_announcements, AuditService, MediaService |
| POST | `/admin/announcements/store` | `Admin\AnnouncementController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | important_announcements, AuditService, MediaService |
| GET | `/admin/announcements/{id}/edit` | `Admin\AnnouncementController@edit` | AuthMiddleware, RoleMiddleware | important_announcements, AuditService, MediaService |
| POST | `/admin/announcements/{id}/update` | `Admin\AnnouncementController@update` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | important_announcements, AuditService, MediaService |
| POST | `/admin/announcements/{id}/delete` | `Admin\AnnouncementController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | important_announcements, AuditService, MediaService |
| GET | `/admin/events` | `Admin\EventController@index` | AuthMiddleware, RoleMiddleware | events, AuditService |
| GET | `/admin/events/create` | `Admin\EventController@create` | AuthMiddleware, RoleMiddleware | events, AuditService |
| POST | `/admin/events/store` | `Admin\EventController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | events, AuditService |
| GET | `/admin/events/{id}/edit` | `Admin\EventController@edit` | AuthMiddleware, RoleMiddleware | events, AuditService |
| POST | `/admin/events/{id}/update` | `Admin\EventController@update` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | events, AuditService |
| POST | `/admin/events/{id}/delete` | `Admin\EventController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | events, AuditService |
| GET | `/admin/contact-messages` | `Admin\ContactMessageController@index` | AuthMiddleware, RoleMiddleware | contact_messages, users, AuditService |
| GET | `/admin/contact-messages/{id}` | `Admin\ContactMessageController@show` | AuthMiddleware, RoleMiddleware | contact_messages, users, AuditService |
| POST | `/admin/contact-messages/{id}/update` | `Admin\ContactMessageController@updateStatus` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | contact_messages, users, AuditService |
| POST | `/admin/contact-messages/{id}/delete` | `Admin\ContactMessageController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | contact_messages, users, AuditService |
| GET | `/admin/surveys` | `Admin\SurveyController@index` | AuthMiddleware, RoleMiddleware | survey_responses, survey_questions, surveys, survey_answers, AuditService |
| GET | `/admin/surveys/create` | `Admin\SurveyController@create` | AuthMiddleware, RoleMiddleware | survey_responses, survey_questions, surveys, survey_answers, AuditService |
| POST | `/admin/surveys/store` | `Admin\SurveyController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | survey_responses, survey_questions, surveys, survey_answers, AuditService |
| GET | `/admin/surveys/{id}/results` | `Admin\SurveyController@results` | AuthMiddleware, RoleMiddleware | survey_responses, survey_questions, surveys, survey_answers, AuditService |
| POST | `/admin/surveys/{id}/delete` | `Admin\SurveyController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | survey_responses, survey_questions, surveys, survey_answers, AuditService |
| GET | `/admin/hero-slides` | `Admin\HeroSlideController@index` | AuthMiddleware, RoleMiddleware | hero_slides, AuditService, MediaService |
| POST | `/admin/hero-slides/store` | `Admin\HeroSlideController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | hero_slides, AuditService, MediaService |
| POST | `/admin/hero-slides/{id}/update` | `Admin\HeroSlideController@update` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | hero_slides, AuditService, MediaService |
| POST | `/admin/hero-slides/{id}/delete` | `Admin\HeroSlideController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | hero_slides, AuditService, MediaService |
| GET | `/admin/popups` | `Admin\PopupController@index` | AuthMiddleware, RoleMiddleware | popups, AuditService |
| POST | `/admin/popups/store` | `Admin\PopupController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | popups, AuditService |
| POST | `/admin/popups/{id}/delete` | `Admin\PopupController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | popups, AuditService |
| GET | `/admin/interest-rates` | `Admin\InterestRateController@index` | AuthMiddleware, RoleMiddleware | interest_rates, interest_rate_history, users, active, AuditService |
| POST | `/admin/interest-rates/{id}/update` | `Admin\InterestRateController@updateRate` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | interest_rates, interest_rate_history, users, active, AuditService |
| GET | `/admin/deposits` | `Admin\DepositProductController@index` | AuthMiddleware, RoleMiddleware | deposit_products, AuditService |
| POST | `/admin/deposits/store` | `Admin\DepositProductController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | deposit_products, AuditService |
| POST | `/admin/deposits/{id}/delete` | `Admin\DepositProductController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | deposit_products, AuditService |
| GET | `/admin/loans` | `Admin\LoanProductController@index` | AuthMiddleware, RoleMiddleware | loan_products, AuditService |
| POST | `/admin/loans/store` | `Admin\LoanProductController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | loan_products, AuditService |
| POST | `/admin/loans/{id}/delete` | `Admin\LoanProductController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | loan_products, AuditService |
| GET | `/admin/welfare` | `Admin\WelfareController@index` | AuthMiddleware, RoleMiddleware | welfare, AuditService |
| POST | `/admin/welfare/store` | `Admin\WelfareController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | welfare, AuditService |
| POST | `/admin/welfare/{id}/delete` | `Admin\WelfareController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | welfare, AuditService |
| GET | `/admin/documents` | `Admin\DocumentController@index` | AuthMiddleware, RoleMiddleware | document_categories, documents, AuditService, MediaService |
| POST | `/admin/documents/store` | `Admin\DocumentController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | document_categories, documents, AuditService, MediaService |
| POST | `/admin/documents/{id}/delete` | `Admin\DocumentController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | document_categories, documents, AuditService, MediaService |
| GET | `/admin/eservices` | `Admin\EServiceController@index` | AuthMiddleware, RoleMiddleware | eservice_links, AuditService |
| POST | `/admin/eservices/store` | `Admin\EServiceController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | eservice_links, AuditService |
| POST | `/admin/eservices/{id}/delete` | `Admin\EServiceController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | eservice_links, AuditService |
| GET | `/admin/complaints` | `Admin\ComplaintController@index` | AuthMiddleware, RoleMiddleware | complaints, users, complaint_logs, AuditService |
| GET | `/admin/complaints/{id}` | `Admin\ComplaintController@show` | AuthMiddleware, RoleMiddleware | complaints, users, complaint_logs, AuditService |
| POST | `/admin/complaints/{id}/update-status` | `Admin\ComplaintController@updateStatus` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | complaints, users, complaint_logs, AuditService |
| GET | `/admin/suggestions` | `Admin\SuggestionController@index` | AuthMiddleware, RoleMiddleware | status, member_suggestions, AuditService, SuggestionService |
| GET | `/admin/suggestions/{id}` | `Admin\SuggestionController@show` | AuthMiddleware, RoleMiddleware | status, member_suggestions, AuditService, SuggestionService |
| POST | `/admin/suggestions/{id}/update-status` | `Admin\SuggestionController@updateStatus` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | status, member_suggestions, AuditService, SuggestionService |
| POST | `/admin/suggestions/{id}/delete` | `Admin\SuggestionController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | status, member_suggestions, AuditService, SuggestionService |
| GET | `/admin/board-staff` | `Admin\BoardStaffController@index` | AuthMiddleware, RoleMiddleware | boards, staff, AuditService |
| POST | `/admin/board-staff/store-board` | `Admin\BoardStaffController@storeBoard` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | boards, staff, AuditService |
| POST | `/admin/board-staff/{id}/delete` | `Admin\BoardStaffController@destroyBoard` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | boards, staff, AuditService |
| GET | `/admin/faqs` | `Admin\FaqController@index` | AuthMiddleware, RoleMiddleware | faqs, AuditService |
| POST | `/admin/faqs/store` | `Admin\FaqController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | faqs, AuditService |
| POST | `/admin/faqs/{id}/delete` | `Admin\FaqController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | faqs, AuditService |
| GET | `/admin/media` | `Admin\MediaController@index` | AuthMiddleware, RoleMiddleware | media, users, AuditService, MediaService |
| POST | `/admin/media/upload` | `Admin\MediaController@upload` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | media, users, AuditService, MediaService |
| POST | `/admin/media/{id}/delete` | `Admin\MediaController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | media, users, AuditService, MediaService |
| GET | `/admin/privacy-cookies` | `Admin\PrivacyCookieController@index` | AuthMiddleware, RoleMiddleware | cookie_categories, cookie_consents, AuditService |
| GET | `/admin/users` | `Admin\UserController@index` | AuthMiddleware, RoleMiddleware | users, user_roles, roles, AdminControlCenterService, AuditService, DualControlSecurityService |
| POST | `/admin/users/store` | `Admin\UserController@store` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | users, user_roles, roles, AdminControlCenterService, AuditService, DualControlSecurityService |
| POST | `/admin/users/{id}/delete` | `Admin\UserController@destroy` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | users, user_roles, roles, AdminControlCenterService, AuditService, DualControlSecurityService |
| GET | `/admin/audit-logs` | `Admin\AuditLogController@index` | AuthMiddleware, RoleMiddleware | audit_logs, users |
| GET | `/admin/backups` | `Admin\BackupController@index` | AuthMiddleware, RoleMiddleware | information_schema, Backup, AdminControlCenterService, AuditService, DualControlSecurityService, Logger |
| POST | `/admin/backups/create` | `Admin\BackupController@createBackup` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | information_schema, Backup, AdminControlCenterService, AuditService, DualControlSecurityService, Logger |
| POST | `/admin/backups/create-storage` | `Admin\BackupController@createStorageBackup` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | information_schema, Backup, AdminControlCenterService, AuditService, DualControlSecurityService, Logger |
| POST | `/admin/backups/create-full` | `Admin\BackupController@createFullBackup` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | information_schema, Backup, AdminControlCenterService, AuditService, DualControlSecurityService, Logger |
| POST | `/admin/backups/restore` | `Admin\BackupController@restoreBackup` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | information_schema, Backup, AdminControlCenterService, AuditService, DualControlSecurityService, Logger |
| POST | `/admin/backups/delete` | `Admin\BackupController@deleteBackup` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | information_schema, Backup, AdminControlCenterService, AuditService, DualControlSecurityService, Logger |
| GET | `/admin/backups/download` | `closure` | AuthMiddleware, RoleMiddleware |  |
| GET | `/admin/settings` | `Admin\SettingController@index` | AuthMiddleware, RoleMiddleware | site_settings, value, AdminControlCenterService, AuditService, DualControlSecurityService |
| POST | `/admin/settings/update` | `Admin\SettingController@update` | AuthMiddleware, RoleMiddleware, CsrfMiddleware | site_settings, value, AdminControlCenterService, AuditService, DualControlSecurityService |
| GET | `/admin/led` | `Admin\LedController@index` | AuthMiddleware, RoleMiddleware |  |
| GET | `/admin/led/dashboard` | `Admin\LedController@dashboard` | AuthMiddleware, RoleMiddleware |  |
| GET | `/admin/led/search` | `Admin\LedController@search` | AuthMiddleware, RoleMiddleware |  |
| GET | `/admin/led/review` | `Admin\LedController@review` | AuthMiddleware, RoleMiddleware |  |
| GET | `/admin/led/batch` | `Admin\LedController@batch` | AuthMiddleware, RoleMiddleware |  |
| GET | `/admin/led/schema` | `Admin\LedController@schema` | AuthMiddleware, RoleMiddleware |  |
| GET | `/api/admin/public-content/sections` | `Admin\PublicContentController@index` | AuthMiddleware | PublicContentService |
| GET | `/api/admin/public-content/sections/{section}` | `Admin\PublicContentController@show` | AuthMiddleware | PublicContentService |
| POST | `/api/admin/public-content/sections/{section}/drafts` | `Admin\PublicContentController@draft` | AuthMiddleware, CsrfMiddleware | PublicContentService |
| POST | `/api/admin/public-content/revisions/{id}/publish` | `Admin\PublicContentController@publish` | AuthMiddleware, CsrfMiddleware | PublicContentService |

## Frontend mutation controls (source locations)

- `src/pages/AdminAccessPage.jsx:15` Promise.all([memberAdminApi('/api/admin/access/roles',{signal:controller.signal}),memberAdminApi(`/api/admin/access/users?q=${encodeURIComponent(query)}&page=${page}`,{signal:contr
- `src/pages/AdminAccessPage.jsx:19` useEffect(()=>{const controller=new AbortController();setMfaError('');memberAdminApi('/api/admin/staff/',{signal:controller.signal}).then(result=>setDirectory(result.staff||[])).ca
- `src/pages/AdminAccessPage.jsx:22` try{await run(signal=>memberAdminApi(path,{data,signal}),{label,scope,confirm:confirmation});onSuccess?.();setMessage('บันทึกสำเร็จ ระบบเก็บ
- `src/pages/AdminAccessPage.jsx:36` {key:'actions',label:'ดำเนินการ',width:'33%',render:item=><div className="admin-actions"><button className="acc-btn" disabled={busy||loading} onClick={()=>setRole
- `src/pages/AdminAccessPage.jsx:42` {key:'actions',label:'ดำเนินการ',width:'33%',render:item=><div className="admin-actions"><button className="acc-btn" disabled={busy||loading||!can('users.update')
- `src/pages/AdminAccessPage.jsx:43` {can('users.suspend')&&<button className="acc-btn" disabled={busy||Number(item.id)===Number(user.id)} onClick={()=>mutate(`/api/admin/staff/${item.id}/${item.status==='active'?'sus
- `src/pages/AdminAccessPage.jsx:44` {can('security.sessions.revoke')&&<button className="acc-btn" disabled={busy} onClick={()=>mutate(`/api/admin/staff/${item.id}/revoke-sessions`,{},`เพิกถอนเซส�
- `src/pages/AdminAccessPage.jsx:45` {can('users.update')&&<button className="acc-btn danger" disabled={busy||Number(item.id)===Number(user.id)} onClick={()=>mutate(`/api/admin/access/users/${item.id}/delete`,{version
- `src/pages/AdminAccessPage.jsx:51` <AdminCard title="บทบาทและสิทธิ์" description="สิทธิ์มีผลกับผู้ใช้ทุกคนที่ได้รับบท
- `src/pages/AdminAccessPage.jsx:55` <AdminCard title="บัญชีเจ้าหน้าที่" description="ค้นหาทั้งระบบด้วยชื่อ ชื่อผู้ใช้ ห�
- `src/pages/AdminAccessPage.jsx:56` <form className="admin-toolbar" onSubmit={event=>{event.preventDefault();setPage(1);setQuery(search.trim());}}><AdminFormField label="ค้นหาเจ้าหน้าที�
- `src/pages/AdminAccessPage.jsx:62` {role&&catalog&&<form onSubmit={event=>{event.preventDefault();mutate(role.id?`/api/admin/access/roles/${role.id}/update`:'/api/admin/access/roles',role,`บันทึกบท�
- `src/pages/AdminAccessPage.jsx:73` {account&&<form onSubmit={event=>{event.preventDefault();const{password,...fields}=account;mutate(account.id?`/api/admin/access/users/${account.id}/update`:'/api/admin/access/users
- `src/pages/AdminControlCenterPage.jsx:376` memberAdminApi('/api/admin/dashboard/summary', { signal }),
- `src/pages/AdminControlCenterPage.jsx:377` memberAdminApi(`/api/admin/dashboard/trends?days=${days}`, { signal }),
- `src/pages/AdminControlCenterPage.jsx:382` const vis = await memberAdminApi(`/api/admin/analytics/visitors?days=${days}`, { signal });
- `src/pages/AdminControlCenterPage.jsx:385` const ops = await memberAdminApi('/api/admin/operations/overview', { signal });
- `src/pages/AdminControlCenterPage.jsx:390` const staffResult=await memberAdminApi('/api/admin/staff/',{signal});
- `src/pages/AdminControlCenterPage.jsx:395` setPermMatrixData(await memberAdminApi('/api/admin/access/permissions',{signal}));
- `src/pages/AdminControlCenterPage.jsx:397` const dept = await memberAdminApi('/api/admin/departments', { signal });
- `src/pages/AdminControlCenterPage.jsx:407` const logs = await memberAdminApi(`/api/admin/audit-logs/center?${params.toString()}`, { signal });
- `src/pages/AdminControlCenterPage.jsx:410` const sec = await memberAdminApi('/api/admin/security/overview', { signal });
- `src/pages/AdminControlCenterPage.jsx:413` const ses = await memberAdminApi('/api/admin/security/sessions', { signal });
- `src/pages/AdminControlCenterPage.jsx:416` const mfa = await memberAdminApi('/api/admin/security/mfa', { signal });
- `src/pages/AdminControlCenterPage.jsx:419` const bkp = await memberAdminApi('/api/admin/backups', { signal });
- `src/pages/AdminControlCenterPage.jsx:422` const hlt = await memberAdminApi('/api/admin/system/health', { signal });
- `src/pages/AdminControlCenterPage.jsx:426` memberAdminApi('/api/admin/reports/catalog', { signal }),
- `src/pages/AdminControlCenterPage.jsx:427` memberAdminApi('/api/admin/dashboard/summary', { signal }).catch(() => null),
- `src/pages/AdminControlCenterPage.jsx:452` await run(signal=>memberAdminApi(`/api/admin/staff/${targetUserId}/revoke-sessions`,{data:{},signal}),{label:`เพิกถอนเซสชัน ${targetName}`,scope:'security
- `src/pages/AdminControlCenterPage.jsx:461` const res = await memberAdminApi('/api/admin/analytics/visitors/export', {
- `src/pages/AdminDashboardPage.jsx:834` <button onClick={() => handleDeleteAnnouncement(ann.id)} className="btn btn-outline btn-sm" style={{ color: 'var(--accent-rose)', borderColor: 'var(--accent-rose)' }}>
- `src/pages/AdminDashboardPage.jsx:882` <button onClick={() => handleDeleteNews(item.id)} className="btn btn-outline btn-sm" style={{ color: 'var(--accent-rose)', borderColor: 'var(--accent-rose)' }}>
- `src/pages/AdminDashboardPage.jsx:906` <form onSubmit={handleSaveHero}>
- `src/pages/AdminDashboardPage.jsx:1017` <form onSubmit={handleSavePopup}>
- `src/pages/AdminDashboardPage.jsx:1325` onClick={() => handleDeleteFeedback(f.id)}
- `src/pages/AdminDashboardPage.jsx:1375` <button onClick={() => handleDeleteFaq(idx)} className="btn btn-outline btn-sm" style={{ color: 'var(--accent-rose)', borderColor: 'var(--accent-rose)', flexShrink: 0 }}>
- `src/pages/AdminDashboardPage.jsx:1666` <form onSubmit={handleAddAnnouncement}>
- `src/pages/AdminDashboardPage.jsx:1702` <form onSubmit={handleAddNews}>
- `src/pages/AdminDashboardPage.jsx:1748` <form onSubmit={handleAddFaq}>
- `src/pages/AdminDashboardPage.jsx:1832` onClick={() => handleSaveAdminReply(selectedFeedback.id, replyInput)}
- `src/pages/AdminDashboardPage.jsx:1846` onClick={() => handleDeleteFeedback(selectedFeedback.id)}
- `src/pages/AdminDashboardPage.jsx:1877` <form onSubmit={handleSaveRate}>
- `src/pages/AdminDividendImportPage.jsx:35` <form onSubmit={e => { e.preventDefault(); submit(false); }}>
- `src/pages/AdminLedBatchPage.jsx:128` <form onSubmit={handleStartBatch} className="space-y-6">
- `src/pages/AdminLedSearchPage.jsx:105` <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3">
- `src/pages/AdminMemberDetailPage.jsx:24` useEffect(() => { const c = new AbortController(); setData(null); setProfile(null); setDividend(null); setReason(''); memberAdminApi(`/api/admin/members/${id}`, { signal: c.signal 
- `src/pages/AdminMemberDetailPage.jsx:30` const result = await memberAdminApi(path, { data: input }); setMessage(result.message); setRefresh(n => n + 1);
- `src/pages/AdminMemberDetailPage.jsx:36` {tab === 'profile' && <form className="member-admin-card" onSubmit={e => save(e, false)}><fieldset disabled={busy || data.read_only}><div className="member-admin-fields">{Object.en
- `src/pages/AdminMemberDetailPage.jsx:38` {dividend && <form onSubmit={e => save(e, true)}><fieldset disabled={busy || data.read_only}><h3>แก้ไขปี {dividend.record.year}</h3><div className="member-admin-field
- `src/pages/AdminMembersDashboardPage.jsx:11` memberAdminApi('/api/admin/members/dashboard', { signal: controller.signal }).then(setData).catch(e => { if (e.name !== 'AbortError') { setError(e); setData(null); } });
- `src/pages/AdminMembersDashboardPage.jsx:15` <div className="member-admin-toolbar"><button className="btn btn-outline" onClick={() => setRefresh(r => r + 1)}>โหลดข้อมูลล่าสุด</button><Link to="
- `src/pages/AdminMembersHealthPage.jsx:9` memberAdminApi('/api/admin/members/health', { signal: controller.signal }).then(r => setData(r.health)).catch(e => { if (e.name !== 'AbortError') setError(e); });
- `src/pages/AdminMembersPage.jsx:16` memberAdminApi(`/api/admin/members?${params}`, { signal: controller.signal }).then(setData).catch(e => { if (e.name !== 'AbortError') setError(e); });
- `src/pages/AdminMembersPage.jsx:24` <section className="member-admin-card member-admin-search-card"><form className="member-admin-search" onSubmit={search}>
- `src/pages/AdminMembersPage.jsx:37` <div className="member-admin-pagination"><button className="btn btn-outline" disabled={data.page <= 1} onClick={() => page(1)}>หน้าแรก</button><button className="btn 
- `src/pages/ContentAdminPage.jsx:17` memberAdminApi(tab==='news'?'/api/admin/news':'/api/public/faqs',{signal:c.signal}).then(r=>setItems((r.data||[]).map(item=>tab==='news'?{...item,gallery_images:normalizeGallery(it
- `src/pages/ContentAdminPage.jsx:20` await memberAdminApi(path,{data:edit});setEdit(null);setMessage(tab==='news'?'บันทึกร่าง / ส่งตรวจแล้ว ยังไม่เผยแพ�
- `src/pages/ContentAdminPage.jsx:22` async function publish(item){if(!window.confirm('ตรวจสอบตัวอย่างข่าวแล้ว และยืนยันเผยแพร่สู่ห�
- `src/pages/ContentAdminPage.jsx:27` {can(`${tab}.create`)&&<button className="btn btn-primary" disabled={busy||readingImages} onClick={()=>setEdit(tab==='news'?{...empty}:{question:'',answer:''})}>เพิ่ม{tab
- `src/pages/ContentAdminPage.jsx:28` {edit&&<form onSubmit={save} style={{display:'grid',gap:12,margin:'24px 0'}}>
- `src/pages/ContentAdminPage.jsx:35` <div><button className="btn btn-primary" disabled={busy||readingImages}>บันทึก</button><button type="button" className="btn btn-outline" disabled={busy||readingImages} 
- `src/pages/ContentAdminPage.jsx:37` <div style={{overflowX:'auto'}}><table style={{width:'100%'}}><thead><tr><th scope="col">หัวข้อ</th><th scope="col">สถานะ</th><th scope="col">ดำเนิ�
- `src/pages/ContentAdminPage.jsx:38` {preview&&<article className="surface-card" style={{padding:24,marginTop:24}}><h2>{preview.title||preview.question}</h2>{tab==='news'&&<ActivitySlideshow images={preview.gallery_im
- `src/pages/PublicContentAdminPage.jsx:43` useEffect(()=>{const controller=new AbortController();memberAdminApi('/api/admin/public-content/sections',{signal:controller.signal}).then(result=>{
- `src/pages/PublicContentAdminPage.jsx:47` memberAdminApi(`/api/admin/public-content/sections/${section}`,{signal:controller.signal}).then(result=>{setData(result);setValue(result.value);}).catch(e=>{if(e.name!=='AbortError
- `src/pages/PublicContentAdminPage.jsx:60` const result=await memberAdminApi(`/api/admin/public-content/sections/${section}/drafts`,{data:{value,version:data.version,note,source_url:source,effective_on:effective,expires_on:
- `src/pages/PublicContentAdminPage.jsx:64` await memberAdminApi(`/api/admin/public-content/revisions/${revision.id}/publish`,{data:{}});await reload();setMessage('อนุมัติแล้ว ข้อมูลจะ�
- `src/pages/PublicContentAdminPage.jsx:72` {section!=='profile'&&<div className="public-content-toolbar"><label>เลือกรายการ <select value={selected} disabled={busy} onChange={e=>setSelected(Number(e.ta
- `src/pages/PublicContentAdminPage.jsx:73` <form onSubmit={save}><fieldset disabled={busy||!definition?.canEdit} className="public-content-fields"><legend>{definition?.label}</legend>
- `src/pages/PublicContentAdminPage.jsx:82` <div className="public-content-toolbar">{revision.state==='draft'&&definition?.canPublish&&<button disabled={busy||dirty} className="btn btn-primary" onClick={()=>publish(revision)
