<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\WorkerPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LeadController::class, 'create'])->name('lead.create');
Route::post('/request-quotation', [LeadController::class, 'store'])->name('lead.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('app')->group(function (): void {
    Route::post('/locale', [LocaleController::class, 'store'])->name('locale.store');

    Route::middleware('permission:access_back_office')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/services', [ServiceController::class, 'index'])->middleware('permission:manage_services')->name('services.index');
        Route::get('/services/create', [ServiceController::class, 'create'])->middleware('permission:manage_services')->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->middleware('permission:manage_services')->name('services.store');
        Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->middleware('permission:manage_services')->name('services.edit');
        Route::patch('/services/{service}', [ServiceController::class, 'update'])->middleware('permission:manage_services')->name('services.update');
        Route::get('/workers', [WorkerController::class, 'index'])->middleware('permission:manage_workers')->name('workers.index');
        Route::get('/workers/create', [WorkerController::class, 'create'])->middleware('permission:manage_workers')->name('workers.create');
        Route::post('/workers', [WorkerController::class, 'store'])->middleware('permission:manage_workers')->name('workers.store');
        Route::get('/workers/{worker}/edit', [WorkerController::class, 'edit'])->middleware('permission:manage_workers')->name('workers.edit');
        Route::patch('/workers/{worker}', [WorkerController::class, 'update'])->middleware('permission:manage_workers')->name('workers.update');
        Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:manage_customers')->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->middleware('permission:manage_customers')->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:manage_customers')->name('customers.store');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->middleware('permission:manage_customers')->name('customers.edit');
        Route::patch('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:manage_customers')->name('customers.update');
        Route::get('/contracts', [ContractController::class, 'index'])->middleware('permission:manage_contracts')->name('contracts.index');
        Route::get('/contracts/create', [ContractController::class, 'create'])->middleware('permission:manage_contracts')->name('contracts.create');
        Route::post('/contracts', [ContractController::class, 'store'])->middleware('permission:manage_contracts')->name('contracts.store');
        Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])->middleware('permission:manage_contracts')->name('contracts.edit');
        Route::patch('/contracts/{contract}', [ContractController::class, 'update'])->middleware('permission:manage_contracts')->name('contracts.update');
        Route::get('/operations', [OperationsController::class, 'index'])->middleware('permission:manage_operations')->name('operations.index');
        Route::post('/operations/assignments', [OperationsController::class, 'storeAssignment'])->middleware('permission:manage_operations')->name('operations.assignments.store');
        Route::post('/operations/generate-visits', [OperationsController::class, 'generateVisits'])->middleware('permission:manage_operations')->name('operations.visits.generate');
        Route::post('/operations/visits/{visit}/check-in', [OperationsController::class, 'checkIn'])->middleware('permission:manage_operations')->name('operations.check-in');
        Route::post('/operations/visits/{visit}/check-out', [OperationsController::class, 'checkOut'])->middleware('permission:manage_operations')->name('operations.check-out');
        Route::patch('/operations/visits/{visit}/execution', [OperationsController::class, 'updateExecution'])->middleware('permission:manage_operations')->name('operations.visits.execution');
        Route::post('/operations/visits/{visit}/checklist', [OperationsController::class, 'completeChecklist'])->middleware('permission:manage_operations')->name('operations.checklist');
        Route::post('/operations/visits/{visit}/issue', [OperationsController::class, 'reportIssue'])->middleware('permission:manage_operations')->name('operations.visits.issue');
        Route::post('/operations/visits/{visit}/missed', [OperationsController::class, 'markMissed'])->middleware('permission:manage_operations')->name('operations.visits.missed');
        Route::post('/operations/visits/{visit}/acknowledge', [OperationsController::class, 'acknowledge'])->middleware('permission:manage_operations')->name('operations.visits.acknowledge');
        Route::patch('/operations/checklist-items/{checklistItem}', [OperationsController::class, 'updateChecklistItem'])->middleware('permission:manage_operations')->name('operations.checklist-items.update');
        Route::get('/finance', [FinanceController::class, 'index'])->middleware('permission:manage_finance')->name('finance.index');
        Route::post('/finance/invoices/{invoice}/payments', [FinanceController::class, 'recordPayment'])->middleware('permission:manage_finance')->name('finance.payments.store');
        Route::patch('/finance/invoices/{invoice}/status', [FinanceController::class, 'updateInvoiceStatus'])->middleware('permission:manage_finance')->name('finance.invoices.status');
        Route::post('/finance/invoices/{invoice}/credit-notes', [FinanceController::class, 'storeCreditNote'])->middleware('permission:manage_finance')->name('finance.credit-notes.store');
        Route::patch('/finance/credit-notes/{creditNote}/approve', [FinanceController::class, 'approveCreditNote'])->middleware('permission:manage_finance')->name('finance.credit-notes.approve');
        Route::post('/finance/cheques', [FinanceController::class, 'storeCheque'])->middleware('permission:manage_finance')->name('finance.cheques.store');
        Route::post('/finance/expenses', [FinanceController::class, 'storeExpense'])->middleware('permission:manage_finance')->name('finance.expenses.store');
        Route::patch('/finance/cheques/{cheque}/status', [FinanceController::class, 'updateChequeStatus'])->middleware('permission:manage_finance')->name('finance.cheques.status');
        Route::get('/finance/invoices/{invoice}/print', [FinanceController::class, 'print'])->middleware('permission:manage_finance')->name('finance.invoice.print');
        Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:manage_expenses')->name('expenses.index');
        Route::get('/expenses/records', [ExpenseController::class, 'records'])->middleware('permission:manage_expenses')->name('expenses.records');
        Route::get('/expenses/categories', [ExpenseController::class, 'categories'])->middleware('permission:manage_expenses')->name('expenses.categories');
        Route::get('/expenses/cost-items', [ExpenseController::class, 'costItems'])->middleware('permission:manage_expenses')->name('expenses.cost-items');
        Route::get('/expenses/service-costs', [ExpenseController::class, 'serviceCosts'])->middleware('permission:manage_expenses')->name('expenses.service-costs');
        Route::post('/expenses/categories', [ExpenseController::class, 'storeCategory'])->middleware('permission:manage_expenses')->name('expenses.categories.store');
        Route::post('/expenses', [ExpenseController::class, 'storeExpense'])->middleware('permission:manage_expenses')->name('expenses.store');
        Route::post('/expenses/cost-items', [ExpenseController::class, 'storeCostItem'])->middleware('permission:manage_expenses')->name('expenses.cost-items.store');
        Route::post('/expenses/service-cost-items', [ExpenseController::class, 'storeServiceCostItem'])->middleware('permission:manage_expenses')->name('expenses.service-cost-items.store');
        Route::get('/reports', [ReportsController::class, 'index'])->middleware('permission:view_dashboard')->name('reports.index');
        Route::get('/reports/export/{report}', [ReportsController::class, 'export'])->middleware('permission:view_dashboard')->name('reports.export');
        Route::get('/settings', [SettingsController::class, 'index'])->middleware('permission:manage_settings')->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'update'])->middleware('permission:manage_settings')->name('settings.update');
        Route::get('/users', [AdminUserController::class, 'index'])->middleware('permission:manage_users')->name('admin.users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->middleware('permission:manage_users')->name('admin.users.store');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->middleware('permission:manage_users')->name('admin.users.update');
    });

    Route::get('/worker/today', [WorkerPortalController::class, 'today'])->middleware('permission:access_worker_portal')->name('worker.today');
    Route::post('/worker/visits/{visit}/start', [WorkerPortalController::class, 'start'])->middleware('permission:access_worker_portal')->name('worker.visits.start');
    Route::post('/worker/visits/{visit}/checklist', [WorkerPortalController::class, 'checklist'])->middleware('permission:access_worker_portal')->name('worker.visits.checklist');
    Route::post('/worker/visits/{visit}/finish', [WorkerPortalController::class, 'finish'])->middleware('permission:access_worker_portal')->name('worker.visits.finish');
    Route::post('/worker/visits/{visit}/issue', [WorkerPortalController::class, 'issue'])->middleware('permission:access_worker_portal')->name('worker.visits.issue');
});
