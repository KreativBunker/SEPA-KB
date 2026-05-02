<?php
declare(strict_types=1);

use App\Support\App;
use App\Support\Router;

/** @var Router $router */

$router->get('/setup', 'App\\Controllers\\SetupController@show');
$router->post('/setup', 'App\\Controllers\\SetupController@install');

$router->get('/login', 'App\\Controllers\\AuthController@showLogin');
$router->post('/login', 'App\\Controllers\\AuthController@login');
$router->get('/logout', 'App\\Controllers\\AuthController@logout');

$router->get('/', 'App\\Controllers\\DashboardController@index', ['auth']);

$router->get('/settings', 'App\\Controllers\\SettingsController@edit', ['auth','role:admin,staff']);
$router->post('/settings', 'App\\Controllers\\SettingsController@update', ['auth','role:admin,staff']);

$router->get('/sevdesk', 'App\\Controllers\\SevdeskController@edit', ['auth','role:admin']);
$router->post('/sevdesk', 'App\\Controllers\\SevdeskController@update', ['auth','role:admin']);
$router->post('/sevdesk/test', 'App\\Controllers\\SevdeskController@test', ['auth','role:admin']);

$router->get('/mandates', 'App\\Controllers\\MandatesController@index', ['auth','role:admin,staff']);
$router->get('/mandates/create', 'App\\Controllers\\MandatesController@create', ['auth','role:admin,staff']);
$router->post('/mandates', 'App\\Controllers\\MandatesController@store', ['auth','role:admin,staff']);
$router->get('/mandates/{id}/edit', 'App\\Controllers\\MandatesController@edit', ['auth','role:admin,staff']);
$router->get('/mandates/{id}/pdf', 'App\\Controllers\\MandatesController@pdf', ['auth','role:admin,staff']);
$router->post('/mandates/{id}', 'App\\Controllers\\MandatesController@update', ['auth','role:admin,staff']);
$router->post('/mandates/{id}/revoke', 'App\\Controllers\\MandatesController@revoke', ['auth','role:admin,staff']);
$router->post('/mandates/{id}/delete', 'App\\Controllers\\MandatesController@delete', ['auth','role:admin,staff']);
$router->post('/mandates/import', 'App\\Controllers\\MandatesController@importCsv', ['auth','role:admin,staff']);
$router->get('/mandates/export', 'App\\Controllers\\MandatesController@exportCsv', ['auth','role:admin,staff']);
$router->get('/mandates/import-sevdesk', 'App\\Controllers\\MandatesController@importSevdesk', ['auth','role:admin,staff']);
$router->post('/mandates/import-sevdesk/load', 'App\\Controllers\\MandatesController@loadSevdeskContacts', ['auth','role:admin,staff']);
$router->post('/mandates/import-sevdesk/run', 'App\\Controllers\\MandatesController@runSevdeskImport', ['auth','role:admin,staff']);



$router->get('/online-mandates', 'App\\Controllers\\OnlineMandatesController@index', ['auth','role:admin,staff']);
$router->get('/online-mandates/create', 'App\\Controllers\\OnlineMandatesController@create', ['auth','role:admin,staff']);
$router->get('/online-mandates/contact/{id}', 'App\\Controllers\\OnlineMandatesController@contact', ['auth','role:admin,staff']);
$router->post('/online-mandates', 'App\\Controllers\\OnlineMandatesController@store', ['auth','role:admin,staff']);
$router->get('/online-mandates/{id}', 'App\\Controllers\\OnlineMandatesController@show', ['auth','role:admin,staff']);
$router->post('/online-mandates/{id}/revoke', 'App\\Controllers\\OnlineMandatesController@revoke', ['auth','role:admin,staff']);
$router->get('/online-mandates/{id}/pdf', 'App\\Controllers\\OnlineMandatesController@downloadPdf', ['auth','role:admin,staff']);

$router->get('/m/{token}', 'App\\Controllers\\PublicMandateController@show');
$router->post('/m/{token}', 'App\\Controllers\\PublicMandateController@sign');
$router->get('/m/{token}/done', 'App\\Controllers\\PublicMandateController@done');
$router->get('/m/{token}/pdf', 'App\\Controllers\\PublicMandateController@pdf');


$router->get('/invoices', 'App\\Controllers\\InvoicesController@index', ['auth','role:admin,staff']);
$router->post('/invoices/load', 'App\\Controllers\\InvoicesController@load', ['auth','role:admin,staff']);
$router->post('/invoices/select', 'App\\Controllers\\InvoicesController@select', ['auth','role:admin,staff']);

$router->get('/exports', 'App\\Controllers\\ExportsController@index', ['auth','role:admin,staff']);
$router->get('/exports/create', 'App\\Controllers\\ExportsController@create', ['auth','role:admin,staff']);
$router->post('/exports', 'App\\Controllers\\ExportsController@store', ['auth','role:admin,staff']);
$router->get('/exports/{id}', 'App\\Controllers\\ExportsController@show', ['auth','role:admin,staff']);
$router->post('/exports/{id}/validate', 'App\\Controllers\\ExportsController@validate', ['auth','role:admin,staff']);
$router->post('/exports/{id}/generate', 'App\\Controllers\\ExportsController@generate', ['auth','role:admin,staff']);
$router->get('/exports/{id}/download', 'App\\Controllers\\ExportsController@download', ['auth','role:admin,staff']);
$router->post('/exports/{id}/finalize', 'App\\Controllers\\ExportsController@finalize', ['auth','role:admin,staff']);

// Contract Templates (Admin only)
$router->get('/contract-templates', 'App\\Controllers\\ContractTemplatesController@index', ['auth','role:admin']);
$router->get('/contract-templates/create', 'App\\Controllers\\ContractTemplatesController@create', ['auth','role:admin']);
$router->post('/contract-templates', 'App\\Controllers\\ContractTemplatesController@store', ['auth','role:admin']);
$router->get('/contract-templates/{id}/edit', 'App\\Controllers\\ContractTemplatesController@edit', ['auth','role:admin']);
$router->post('/contract-templates/{id}', 'App\\Controllers\\ContractTemplatesController@update', ['auth','role:admin']);
$router->post('/contract-templates/{id}/delete', 'App\\Controllers\\ContractTemplatesController@delete', ['auth','role:admin']);

// Contracts (Admin + Staff)
$router->get('/contracts', 'App\\Controllers\\ContractsController@index', ['auth','role:admin,staff']);
$router->get('/contracts/create', 'App\\Controllers\\ContractsController@create', ['auth','role:admin,staff']);
$router->get('/contracts/contact/{id}', 'App\\Controllers\\ContractsController@contact', ['auth','role:admin,staff']);
$router->post('/contracts', 'App\\Controllers\\ContractsController@store', ['auth','role:admin,staff']);
$router->get('/contracts/{id}', 'App\\Controllers\\ContractsController@show', ['auth','role:admin,staff']);
$router->get('/contracts/{id}/edit', 'App\\Controllers\\ContractsController@edit', ['auth','role:admin,staff']);
$router->post('/contracts/{id}', 'App\\Controllers\\ContractsController@update', ['auth','role:admin,staff']);
$router->post('/contracts/{id}/revoke', 'App\\Controllers\\ContractsController@revoke', ['auth','role:admin,staff']);
$router->post('/contracts/{id}/cancel', 'App\\Controllers\\ContractsController@cancel', ['auth','role:admin,staff']);
$router->post('/contracts/{id}/delete', 'App\\Controllers\\ContractsController@delete', ['auth','role:admin,staff']);
$router->get('/contracts/{id}/pdf', 'App\\Controllers\\ContractsController@downloadPdf', ['auth','role:admin,staff']);
$router->get('/contracts/{id}/sepa-pdf', 'App\\Controllers\\ContractsController@downloadSepaPdf', ['auth','role:admin,staff']);
$router->get('/contracts/{id}/cancellation-pdf', 'App\\Controllers\\ContractsController@downloadCancellationPdf', ['auth','role:admin,staff']);

// Public Contract Signing
$router->get('/c/{token}', 'App\\Controllers\\PublicContractController@show');
$router->post('/c/{token}', 'App\\Controllers\\PublicContractController@sign');
$router->get('/c/{token}/done', 'App\\Controllers\\PublicContractController@done');
$router->get('/c/{token}/pdf', 'App\\Controllers\\PublicContractController@pdf');
$router->get('/c/{token}/sepa-pdf', 'App\\Controllers\\PublicContractController@sepaPdf');

// System Update (Admin only)
$router->get('/update', 'App\\Controllers\\UpdateController@show', ['auth','role:admin']);
$router->post('/update', 'App\\Controllers\\UpdateController@run', ['auth','role:admin']);

$router->get('/users', 'App\\Controllers\\UsersController@index', ['auth','role:admin']);
$router->post('/users/{id}/reset-password', 'App\\Controllers\\UsersController@resetPassword', ['auth','role:admin']);
$router->post('/users/{id}/delete', 'App\\Controllers\\UsersController@delete', ['auth','role:admin']);
$router->get('/users/create', 'App\\Controllers\\UsersController@create', ['auth','role:admin']);
$router->post('/users', 'App\\Controllers\\UsersController@store', ['auth','role:admin']);
