<?php
declare(strict_types=1);

use App\Controllers\Portal as P;
use App\Controllers\Site as S;
use App\Core\Router;

$r = new Router();

// ----------------------------------------------------------------- Public website
$r->get('/', [S\SiteController::class, 'root']);
$r->get('/robots.txt', [S\SiteController::class, 'robots']);
$r->get('/sitemap.xml', [S\SiteController::class, 'sitemap']);
$r->get('/manifest.webmanifest', [S\SiteController::class, 'manifest']);
$r->get('/media/{id}/{size}', [S\SiteController::class, 'media']);
$r->get('/verify/{token}', [S\SiteController::class, 'verify']);
$r->any('/install', [S\InstallController::class, 'index']);
$r->get('/{lang}', [S\SiteController::class, 'home']);
$r->get('/{lang}/horses', [S\SiteController::class, 'horses']);
$r->get('/{lang}/horses/{slug}', [S\SiteController::class, 'horse']);
$r->get('/{lang}/champions', [S\SiteController::class, 'champions']);
$r->get('/{lang}/breeding', [S\SiteController::class, 'breeding']);
$r->get('/{lang}/news', [S\SiteController::class, 'news']);
$r->get('/{lang}/news/{slug}', [S\SiteController::class, 'newsItem']);
$r->get('/{lang}/about', [S\SiteController::class, 'about']);
$r->get('/{lang}/contact', [S\SiteController::class, 'contact']);
$r->post('/{lang}/inquiry', [S\SiteController::class, 'inquiry']);
$r->post('/{lang}/assistant', [S\AssistantController::class, 'ask']);

// ----------------------------------------------------------------- Portal: auth & account
$r->get('/portal/login', [P\AuthController::class, 'loginForm']);
$r->post('/portal/login', [P\AuthController::class, 'login']);
$r->get('/portal/login/2fa', [P\AuthController::class, 'twofaForm']);
$r->post('/portal/login/2fa', [P\AuthController::class, 'twofa']);
$r->post('/portal/login/2fa/resend', [P\AuthController::class, 'resend']);
$r->any('/portal/password-reset/{token}', [P\AuthController::class, 'passwordReset']);
$r->get('/portal/captcha', [P\AuthController::class, 'captcha']);
$r->post('/portal/logout', [P\AuthController::class, 'logout']);
$r->get('/portal/account', [P\AccountController::class, 'index']);
$r->any('/portal/account/password', [P\AccountController::class, 'password']);
$r->any('/portal/account/2fa', [P\AccountController::class, 'twofa']);
$r->post('/portal/account/lang', [P\AccountController::class, 'lang']);
$r->post('/portal/account/logout-all', [P\AccountController::class, 'logoutAll']);
$r->post('/portal/account/sessions/{id}/end', [P\AccountController::class, 'endSession']);
$r->get('/portal/my-hr', [P\MyHrController::class, 'index']);
$r->post('/portal/my-hr/leave', [P\MyHrController::class, 'requestLeave']);
$r->post('/portal/my-hr/leave/{id}/cancel', [P\MyHrController::class, 'cancelLeave']);

// ----------------------------------------------------------------- Portal: core
$r->get('/portal', [P\DashboardController::class, 'index']);
$r->get('/portal/search', [P\DashboardController::class, 'search']);
$r->get('/portal/my-horses', [P\DashboardController::class, 'myHorses']);
$r->get('/portal/notifications', [P\DashboardController::class, 'notifications']);
$r->post('/portal/notifications/read', [P\DashboardController::class, 'markRead']);
$r->get('/portal/api/picker/{source}', [P\CrudController::class, 'picker']);
$r->get('/portal/api/unique', [P\CrudController::class, 'unique']);
$r->get('/portal/api/notifications', [P\DashboardController::class, 'notificationsJson']);
$r->get('/portal/approvals', [P\ApprovalsController::class, 'index']);
$r->get('/portal/approvals/{id}', [P\ApprovalsController::class, 'show']);
$r->get('/portal/printouts/{token}', [P\PrintoutsController::class, 'show']);
$r->post('/portal/printouts/{token}/void', [P\PrintoutsController::class, 'void']);
$r->post('/portal/approvals/{id}/approve', [P\ApprovalsController::class, 'approve']);
$r->post('/portal/approvals/{id}/reject', [P\ApprovalsController::class, 'reject']);

// Files (stored outside public_html, streamed after a permission check)
$r->post('/portal/files/upload', [P\FilesController::class, 'upload']);
$r->get('/portal/files/{id}', [P\FilesController::class, 'show']);
$r->get('/portal/files/{id}/thumb', [P\FilesController::class, 'thumb']);
$r->post('/portal/files/{id}/delete', [P\FilesController::class, 'delete']);
$r->post('/portal/files/{id}/main', [P\FilesController::class, 'makeMain']);
$r->post('/portal/files/{id}/public', [P\FilesController::class, 'togglePublic']);

// Horses
$r->get('/portal/horses/{id}/qr', [P\HorsesController::class, 'qr']);
$r->get('/portal/horses/qr-sheet', [P\HorsesController::class, 'qrSheet']);
$r->get('/portal/h/{id}', [P\HorsesController::class, 'scan']);
$r->get('/portal/horses/{id}/pedigree', [P\HorsesController::class, 'pedigree']);
$r->any('/portal/horses/{id}/pedigree-edit', [P\HorsesController::class, 'pedigreeEdit']);
$r->get('/portal/horses/{id}/ancestors', [P\HorsesController::class, 'ancestors']);
$r->any('/portal/horses/pedigree-import', [P\HorsesController::class, 'pedigreeImport']);
$r->any('/portal/horses/cards-import', [P\HorsesController::class, 'cardsImport']);
$r->any('/portal/horses/import', [P\HorsesController::class, 'import']);
$r->get('/portal/horses/import/template', [P\HorsesController::class, 'importTemplate']);
$r->post('/portal/horses/import/run', [P\HorsesController::class, 'importRun']);
$r->post('/portal/horses/import/cancel', [P\HorsesController::class, 'importCancel']);
$r->any('/portal/horses/{id}/foaling', [P\HorsesController::class, 'foaling']);
$r->post('/portal/horses/{id}/assign', [P\HorsesController::class, 'assign']);
$r->post('/portal/horses/{id}/unassign/{id2}', [P\HorsesController::class, 'unassign']);
$r->post('/portal/horses/{id}/quick-feed', [P\HorsesController::class, 'quickFeed']);
$r->post('/portal/horses/{id}/website-approve', [P\HorsesController::class, 'requestWebsite']);
$r->post('/portal/horses/recalculate-categories', [P\HorsesController::class, 'recalc']);
$r->any('/portal/embryos/{id}/foaling', [P\HorsesController::class, 'embryoFoaling']);
$r->post('/portal/breeding-records/{id}/check', [P\HorsesController::class, 'addCheck']);
$r->any('/portal/horses/{id}/transfer', [P\HorsesController::class, 'transfer']);
$r->post('/portal/horses/{id}/favorite', [P\HorsesController::class, 'favorite']);
$r->get('/portal/horses/{id}/ownership/{id2}/certificate', [P\HorsesController::class, 'ownershipCertificate']);

// Semen Inventory
$r->get('/portal/semen-stock/import', [P\SemenController::class, 'importForm']);
$r->post('/portal/semen-stock/import', [P\SemenController::class, 'importSubmit']);
$r->get('/portal/semen-stock/count', [P\SemenController::class, 'countForm']);
$r->post('/portal/semen-stock/count', [P\SemenController::class, 'countSubmit']);
$r->get('/portal/semen-stock/report', [P\SemenController::class, 'report']);
$r->get('/portal/semen-stock/{id}/movement', [P\SemenController::class, 'movementForm']);
$r->post('/portal/semen-stock/{id}/movement', [P\SemenController::class, 'recordMovement']);
$r->get('/portal/tanks/map', [P\SemenController::class, 'tankMap']);
$r->post('/portal/tanks/{id}/refill', [P\SemenController::class, 'addRefill']);

// HR
$r->post('/portal/employees/{id}/deactivate-login', [P\HrController::class, 'deactivateLogin']);
$r->post('/portal/leave-requests/{id}/decide', [P\HrController::class, 'decideLeave']);
$r->post('/portal/leave-requests/{id}/cancel', [P\HrController::class, 'cancelLeave']);
$r->any('/portal/attendance/sheet', [P\HrController::class, 'sheet']);
$r->post('/portal/employees/{id}/reissue-card', [P\HrController::class, 'reissueCard']);
// Attendance by QR: the gate scanner (kiosk login), its settings, the monthly check before payroll
$r->get('/portal/attendance/scan', [P\AttendanceScanController::class, 'page']);
$r->post('/portal/attendance/scan', [P\AttendanceScanController::class, 'scan']);
$r->post('/portal/attendance/scan/typed', [P\AttendanceScanController::class, 'typed']);
$r->post('/portal/attendance/scan/ping', [P\AttendanceScanController::class, 'ping']);
$r->get('/portal/attendance/scan/photo/{id}', [P\AttendanceScanController::class, 'photo']);
$r->any('/portal/attendance/settings', [P\AttendanceScanController::class, 'settings']);
$r->get('/portal/attendance/check', [P\AttendanceScanController::class, 'check']);

// Finance
$r->get('/portal/number-register', [P\NumbersController::class, 'index']);
$r->post('/portal/bills/{id}/submit', [P\FinanceController::class, 'submit']);
$r->post('/portal/bills/{id}/cancel', [P\FinanceController::class, 'cancel']);
$r->get('/portal/bills/draft-reports', [P\FinanceController::class, 'draftReports']);
$r->post('/portal/bills/bulk-add-to-report', [P\FinanceController::class, 'bulkAddToReport']);
$r->post('/portal/bills/bulk-pay', [P\FinanceController::class, 'bulkPay']);
$r->post('/portal/bills/bulk-delete', [P\FinanceController::class, 'bulkDelete']);
$r->post('/portal/bill-payments/bulk-delete', [P\FinanceController::class, 'bulkDeletePayments']);
$r->post('/portal/purchase-orders/{id}/submit', [P\FinanceController::class, 'submitPo']);
$r->post('/portal/purchase-orders/{id}/receive', [P\FinanceController::class, 'receivePo']);
$r->post('/portal/purchase-orders/{id}/cancel', [P\FinanceController::class, 'cancelPo']);
$r->any('/portal/purchase-orders/{id}/lines', [P\FinanceController::class, 'poLines']);
$r->any('/portal/invoices/{id}/lines', [P\FinanceController::class, 'invoiceLines']);
$r->post('/portal/invoices/{id}/issue', [P\FinanceController::class, 'issueInvoice']);
$r->post('/portal/invoices/{id}/cancel', [P\FinanceController::class, 'cancelInvoice']);
$r->get('/portal/payroll', [P\PayrollController::class, 'index']);
$r->post('/portal/payroll', [P\PayrollController::class, 'create']);
$r->get('/portal/payroll/{id}', [P\PayrollController::class, 'show']);
$r->post('/portal/payroll/{id}/lines', [P\PayrollController::class, 'saveLines']);
$r->post('/portal/payroll/{id}/submit', [P\PayrollController::class, 'submit']);
$r->post('/portal/payroll/{id}/pay', [P\PayrollController::class, 'pay']);
$r->post('/portal/payroll/{id}/delete', [P\PayrollController::class, 'delete']);
$r->get('/portal/reports', [P\ReportsController::class, 'index']);
$r->get('/portal/reports/{type}', [P\ReportsController::class, 'show']);

// Inventory
$r->any('/portal/items/{id}/stock', [P\InventoryController::class, 'stock']);

// SK Arabian Studio
$r->get('/portal/studio', [P\StudioController::class, 'index']);
$r->any('/portal/studio/new/{type}', [P\StudioController::class, 'create']);
$r->get('/portal/studio/templates', [P\StudioController::class, 'templates']);
$r->any('/portal/studio/templates/{type}', [P\StudioController::class, 'editTemplate']);
$r->post('/portal/studio/log', [P\StudioController::class, 'logUnsaved']);
$r->any('/portal/studio/permissions', [P\StudioController::class, 'permissions']);
$r->get('/portal/studio/fin/bills', [P\StudioController::class, 'finBills']);
$r->any('/portal/studio/profiles', [P\StudioController::class, 'profiles']);
$r->get('/portal/studio/print-batch', [P\StudioController::class, 'printBatch']);
$r->get('/portal/studio/{id}', [P\StudioController::class, 'show']);
$r->get('/portal/studio/{id}/print', [P\StudioController::class, 'print']);
$r->post('/portal/studio/{id}/share', [P\StudioController::class, 'share']);
$r->post('/portal/studio/{id}/void', [P\StudioController::class, 'void']);
$r->post('/portal/studio/{id}/discard', [P\StudioController::class, 'discard']);
$r->post('/portal/studio/drafts/discard-empty', [P\StudioController::class, 'discardEmpty']);
$r->post('/portal/studio/{id}/log', [P\StudioController::class, 'log']);
$r->post('/portal/studio/{id}/add-bills', [P\StudioController::class, 'finAddBills']);
$r->post('/portal/studio/{id}/add-manual', [P\StudioController::class, 'finAddManual']);
$r->post('/portal/studio/{id}/remove-line', [P\StudioController::class, 'finRemoveLine']);
$r->post('/portal/studio/{id}/reorder-lines', [P\StudioController::class, 'finReorder']);
$r->post('/portal/studio/{id}/refresh-bills', [P\StudioController::class, 'finRefresh']);
$r->post('/portal/studio/{id}/save-draft', [P\StudioController::class, 'finSaveDraft']);
$r->post('/portal/studio/{id}/issue', [P\StudioController::class, 'finIssue']);

// Documents & Photos
$r->get('/portal/documents/upload-form', [P\DocumentsController::class, 'uploadForm']);
$r->post('/portal/documents/upload', [P\DocumentsController::class, 'upload']);

// Website CMS & inbox
$r->any('/portal/cms', [P\CmsController::class, 'index']);
$r->any('/portal/cms/website-horses', [P\CmsController::class, 'horses']);
$r->any('/portal/cms/contact', [P\CmsController::class, 'contact']);
$r->get('/portal/cms/visitors', [P\CmsController::class, 'visitors']);
$r->get('/portal/inbox', [P\InboxController::class, 'index']);
$r->get('/portal/inbox/{id}', [P\InboxController::class, 'show']);
$r->post('/portal/inbox/{id}', [P\InboxController::class, 'update']);

// Administration
$r->any('/portal/roles', [P\AdminController::class, 'roles']);
$r->any('/portal/roles/{id}', [P\AdminController::class, 'role']);
$r->post('/portal/roles/{id}/reset', [P\AdminController::class, 'resetRole']);
$r->post('/portal/users/{id}/sessions/end', [P\AdminController::class, 'endUserSessions']);
$r->get('/portal/users/{id}/reset-password', [P\AdminController::class, 'resetPasswordForm']);
$r->post('/portal/users/{id}/reset-password', [P\AdminController::class, 'resetPassword']);
$r->post('/portal/users/{id}/set-password', [P\AdminController::class, 'setPassword']);
$r->post('/portal/users/{id}/reset-2fa', [P\AdminController::class, 'reset2fa']);
$r->post('/portal/users/{id}/unlock', [P\AdminController::class, 'unlock']);
$r->post('/portal/users/{id}/status', [P\AdminController::class, 'toggleStatus']);
$r->post('/portal/users/{id}/grant', [P\AdminController::class, 'grant']);
$r->post('/portal/users/{id}/grant/{id2}/revoke', [P\AdminController::class, 'revokeGrant']);
$r->post('/portal/users/{id}/restore', [P\AdminController::class, 'restoreUser']);
$r->post('/portal/users/{id}/purge', [P\AdminController::class, 'purgeUser']);
$r->get('/portal/sessions', [P\AdminController::class, 'sessions']);
$r->post('/portal/sessions/{id}/end', [P\AdminController::class, 'endSession']);
$r->post('/portal/known-devices/{id}/forget', [P\AdminController::class, 'forgetDevice']);
$r->any('/portal/settings', [P\AdminController::class, 'settings']);
$r->post('/portal/settings/database-update', [P\AdminController::class, 'databaseUpdate']);
$r->any('/portal/settings/checks', [P\ChecksController::class, 'index']);
$r->post('/portal/settings/test-email', [P\AdminController::class, 'testEmail']);
$r->any('/portal/settings/whatsapp', [P\AdminController::class, 'whatsapp']);
$r->any('/portal/settings/ai', [P\AiController::class, 'settings']);
$r->post('/portal/ai/translate', [P\AiController::class, 'translate']);
$r->any('/portal/settings/security', [P\AdminController::class, 'security']);
$r->any('/portal/settings/currencies', [P\AdminController::class, 'currencies']);
$r->get('/portal/settings/backups', [P\AdminController::class, 'backups']);
$r->get('/portal/settings/errors', [P\AdminController::class, 'errors']);
$r->get('/portal/style-guide', [P\AdminController::class, 'styleGuide']); // the design system (Owner only, not in the menu)
$r->post('/portal/settings/backups/run', [P\AdminController::class, 'runBackup']);
$r->post('/portal/settings/backups/test', [P\AdminController::class, 'testRestore']);
$r->post('/portal/settings/backups/drive', [P\AdminController::class, 'saveDrive']);
$r->get('/portal/settings/backups/{name}', [P\AdminController::class, 'downloadBackup']);
$r->get('/portal/trash', [P\AdminController::class, 'trash']);
$r->post('/portal/trash/{id}/restore', [P\AdminController::class, 'restore']);
$r->post('/portal/trash/{id}/purge', [P\AdminController::class, 'purge']);
$r->post('/portal/trash/empty', [P\AdminController::class, 'emptyTrash']);
$r->get('/portal/activity', [P\AdminController::class, 'activity']);
$r->get('/portal/activity/verify', [P\AdminController::class, 'verifyLog']);
$r->get('/portal/activity/{id}', [P\AdminController::class, 'activityItem']);
$r->get('/portal/migration-report', [P\AdminController::class, 'migrationReport']);
$r->post('/portal/migration-report/run', [P\AdminController::class, 'migrationRun']);
$r->post('/portal/migration-report/review-all', [P\AdminController::class, 'migrationReviewAll']);
$r->post('/portal/migration-report/{id}/reviewed', [P\AdminController::class, 'migrationReviewed']);

// ----------------------------------------------------------------- Generic resources (keep last)
$r->get('/portal/{key}', [P\CrudController::class, 'index']);
$r->get('/portal/{key}/create', [P\CrudController::class, 'create']);
$r->post('/portal/{key}', [P\CrudController::class, 'store']);
$r->get('/portal/{key}/{id}', [P\CrudController::class, 'show']);
$r->get('/portal/{key}/{id}/edit', [P\CrudController::class, 'edit']);
$r->post('/portal/{key}/{id}', [P\CrudController::class, 'update']);
$r->post('/portal/{key}/{id}/delete', [P\CrudController::class, 'delete']);
$r->post('/portal/{key}/{id}/archive', [P\CrudController::class, 'archive']);

return $r;
