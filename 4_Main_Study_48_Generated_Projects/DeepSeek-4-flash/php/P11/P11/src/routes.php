<?php

declare(strict_types=1);

use App\Controller\AccountController;
use App\Controller\AdminController;
use App\Controller\AuditController;
use App\Controller\AuthController;
use App\Controller\BackupController;
use App\Controller\CertificateController;
use App\Controller\DashboardController;
use App\Controller\DatabaseController;
use App\Controller\DomainController;
use App\Controller\FileController;
use App\Controller\SiteController;
use App\Controller\TaskController;
use App\Controller\TicketController;
use App\Controller\UsageController;

/*
 * Browser pages
 */

$app->get('/', DashboardController::class . ':index');

$app->get('/login', AuthController::class . ':showLogin');
$app->post('/login', AuthController::class . ':login');
$app->get('/register', AuthController::class . ':showRegister');
$app->post('/register', AuthController::class . ':register');
$app->post('/logout', AuthController::class . ':logout');

$app->get('/account', AccountController::class . ':index');
$app->post('/account/update', AccountController::class . ':update');

$app->get('/domains', DomainController::class . ':index');
$app->get('/domains/new', DomainController::class . ':create');
$app->post('/domains', DomainController::class . ':store');
$app->get('/domains/{id}', DomainController::class . ':show');
$app->post('/domains/{id}/update', DomainController::class . ':update');
$app->post('/domains/{id}/status', DomainController::class . ':status');
$app->post('/domains/{id}/delete', DomainController::class . ':delete');
$app->post('/domains/{id}/dns', DomainController::class . ':addDns');
$app->post('/domains/{id}/dns/{dnsId}/delete', DomainController::class . ':deleteDns');

$app->get('/sites', SiteController::class . ':index');
$app->get('/sites/new', SiteController::class . ':create');
$app->post('/sites', SiteController::class . ':store');
$app->get('/sites/{id}', SiteController::class . ':show');
$app->post('/sites/{id}/update', SiteController::class . ':update');
$app->post('/sites/{id}/deploy', SiteController::class . ':deploy');

$app->get('/files', FileController::class . ':index');
$app->post('/files/upload', FileController::class . ':upload');
$app->post('/files/newdir', FileController::class . ':newDir');
$app->post('/files/rename', FileController::class . ':rename');
$app->post('/files/delete', FileController::class . ':delete');
$app->get('/files/download', FileController::class . ':download');

$app->get('/databases', DatabaseController::class . ':index');
$app->get('/databases/new', DatabaseController::class . ':create');
$app->post('/databases', DatabaseController::class . ':store');
$app->get('/databases/{id}', DatabaseController::class . ':show');
$app->post('/databases/{id}/delete', DatabaseController::class . ':delete');
$app->post('/databases/{id}/users', DatabaseController::class . ':addUser');
$app->post('/databases/{id}/users/{uid}/delete', DatabaseController::class . ':deleteUser');

$app->get('/backups', BackupController::class . ':index');
$app->post('/backups', BackupController::class . ':store');
$app->post('/backups/upload', BackupController::class . ':upload');
$app->get('/backups/{id}/download', BackupController::class . ':download');
$app->post('/backups/{id}/restore', BackupController::class . ':restore');
$app->post('/backups/{id}/delete', BackupController::class . ':delete');

$app->get('/certificates', CertificateController::class . ':index');
$app->get('/certificates/new', CertificateController::class . ':create');
$app->post('/certificates', CertificateController::class . ':store');
$app->get('/certificates/{id}', CertificateController::class . ':show');
$app->post('/certificates/{id}/renew', CertificateController::class . ':renew');
$app->post('/certificates/upload', CertificateController::class . ':upload');

$app->get('/cron', TaskController::class . ':index');
$app->get('/cron/new', TaskController::class . ':create');
$app->post('/cron', TaskController::class . ':store');
$app->post('/cron/{id}/update', TaskController::class . ':update');
$app->post('/cron/{id}/toggle', TaskController::class . ':toggle');
$app->post('/cron/{id}/run', TaskController::class . ':run');
$app->post('/cron/{id}/delete', TaskController::class . ':delete');

$app->get('/usage', UsageController::class . ':index');

$app->get('/tickets', TicketController::class . ':index');
$app->get('/tickets/new', TicketController::class . ':create');
$app->post('/tickets', TicketController::class . ':store');
$app->get('/tickets/{id}', TicketController::class . ':show');
$app->post('/tickets/{id}/reply', TicketController::class . ':reply');
$app->post('/tickets/{id}/status', TicketController::class . ':status');

$app->get('/audit', AuditController::class . ':index');

$app->get('/admin', AdminController::class . ':index');
$app->get('/admin/plans', AdminController::class . ':plans');
$app->post('/admin/plans', AdminController::class . ':storePlan');
$app->post('/admin/plans/{id}/update', AdminController::class . ':updatePlan');
$app->post('/admin/plans/{id}/delete', AdminController::class . ':deletePlan');
$app->get('/admin/accounts', AdminController::class . ':accounts');
$app->post('/admin/accounts', AdminController::class . ':storeAccount');
$app->post('/admin/accounts/{id}/update', AdminController::class . ':updateAccount');
$app->get('/admin/settings', AdminController::class . ':settings');
$app->post('/admin/settings', AdminController::class . ':saveSettings');

/*
 * JSON API contract (HOST-01 .. HOST-12)
 */

$app->group('/api/host', function ($group) {
    $group->get('/account_access', AccountController::class . ':apiList');
    $group->post('/account_access', AccountController::class . ':apiCreate');
    $group->patch('/account_access/{id}', AccountController::class . ':apiUpdate');

    $group->get('/domain_management', DomainController::class . ':apiList');
    $group->post('/domain_management', DomainController::class . ':apiCreate');
    $group->patch('/domain_management/{id}', DomainController::class . ':apiUpdate');

    $group->get('/site_management', SiteController::class . ':apiList');
    $group->post('/site_management', SiteController::class . ':apiCreate');
    $group->patch('/site_management/{id}', SiteController::class . ':apiUpdate');

    $group->get('/file_manager', FileController::class . ':apiList');
    $group->post('/file_manager', FileController::class . ':apiCreate');
    $group->patch('/file_manager/{id}', FileController::class . ':apiUpdate');

    $group->get('/database_management', DatabaseController::class . ':apiList');
    $group->post('/database_management', DatabaseController::class . ':apiCreate');
    $group->patch('/database_management/{id}', DatabaseController::class . ':apiUpdate');

    $group->get('/backup_and_restore', BackupController::class . ':apiList');
    $group->post('/backup_and_restore', BackupController::class . ':apiCreate');
    $group->patch('/backup_and_restore/{id}', BackupController::class . ':apiUpdate');

    $group->get('/ssl_certificate_management', CertificateController::class . ':apiList');
    $group->post('/ssl_certificate_management', CertificateController::class . ':apiCreate');
    $group->patch('/ssl_certificate_management/{id}', CertificateController::class . ':apiUpdate');

    $group->get('/scheduled_tasks', TaskController::class . ':apiList');
    $group->post('/scheduled_tasks', TaskController::class . ':apiCreate');
    $group->patch('/scheduled_tasks/{id}', TaskController::class . ':apiUpdate');

    $group->get('/resource_usage', UsageController::class . ':apiList');
    $group->post('/resource_usage', UsageController::class . ':apiCreate');
    $group->patch('/resource_usage/{id}', UsageController::class . ':apiUpdate');

    $group->get('/support_tickets', TicketController::class . ':apiList');
    $group->post('/support_tickets', TicketController::class . ':apiCreate');
    $group->patch('/support_tickets/{id}', TicketController::class . ':apiUpdate');

    $group->get('/audit_logs', AuditController::class . ':apiList');
    $group->post('/audit_logs', AuditController::class . ':apiCreate');
    $group->patch('/audit_logs/{id}', AuditController::class . ':apiUpdate');

    $group->get('/admin_operations', AdminController::class . ':apiList');
    $group->post('/admin_operations', AdminController::class . ':apiCreate');
    $group->patch('/admin_operations/{id}', AdminController::class . ':apiUpdate');
});
