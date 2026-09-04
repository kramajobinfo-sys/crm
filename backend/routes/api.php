<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Middleware\ValidateSignature;
use App\Http\Controllers\Api\V1\System\AttachmentController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\Chat\ChatController;
use App\Http\Controllers\Api\V1\Chat\ChannelSettingsController;
use App\Http\Controllers\Api\V1\Customers\CustomerController;
use App\Http\Controllers\Api\V1\Contacts\ContactController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Dashboard\MyWorkController;
use App\Http\Controllers\Api\V1\Deals\DealController;
use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\Projects\ProjectOperationsController;
use App\Http\Controllers\Api\V1\Activities\ActivityController;
use App\Http\Controllers\Api\V1\Documents\DocumentController;
use App\Http\Controllers\Api\V1\Manufacturing\ManufacturingController;
use App\Http\Controllers\Api\V1\Kb\KbController;
use App\Http\Controllers\Api\V1\Portal\PortalKbController;
use App\Http\Controllers\Api\V1\Sales\PriceBookController;
use App\Http\Controllers\Api\V1\Sales\ProductController;
use App\Http\Controllers\Api\V1\Sales\QuotationController;
use App\Http\Controllers\Api\V1\Sales\SalesOrderController;
use App\Http\Controllers\Api\V1\Sales\CustomerCreditController;
use App\Http\Controllers\Api\V1\Sales\InvoiceController;
use App\Http\Controllers\Api\V1\Sales\PaymentController;
use App\Http\Controllers\Api\V1\Inventory\WarehouseController;
use App\Http\Controllers\Api\V1\Inventory\InventoryController;
use App\Http\Controllers\Api\V1\Purchase\VendorController;
use App\Http\Controllers\Api\V1\Purchase\PurchaseRequestController;
use App\Http\Controllers\Api\V1\Purchase\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Purchase\ApprovalController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketController;
use App\Http\Controllers\Api\V1\Email\EmailController;
use App\Http\Controllers\Api\V1\Marketing\CampaignController;
use App\Http\Controllers\Api\V1\Settings\UserManagementController;
use App\Http\Controllers\Api\V1\Settings\RoleController;
use App\Http\Controllers\Api\V1\Settings\OrganizationController;
use App\Http\Controllers\Api\V1\Settings\ApiKeyController;
use App\Http\Controllers\Api\V1\Reports\ReportController;
use App\Http\Controllers\Api\V1\Forecasts\ForecastController;
use App\Http\Controllers\Api\V1\Workflow\WorkflowController;
use App\Http\Controllers\Api\V1\Ai\AiController;
use App\Http\Controllers\Api\V1\Hr\EmployeeController;
use App\Http\Controllers\Api\V1\Hr\AttendanceController;
use App\Http\Controllers\Api\V1\Hr\LeaveController;
use App\Http\Controllers\Api\V1\Integrations\DynamicsController;
use App\Http\Controllers\Api\V1\Leads\LeadController;
use App\Http\Controllers\Api\V1\System\NotificationController;
use App\Http\Controllers\Api\V1\System\GlobalSearchController;
use App\Http\Controllers\Api\V1\System\DuplicateController;
use App\Http\Controllers\Api\V1\System\AuditLogController;
use App\Http\Controllers\Api\V1\Platform\PlatformController;
use App\Http\Controllers\Api\V1\System\TenantInfoController;
use App\Http\Controllers\Api\V1\Visits\VisitController;
use App\Http\Controllers\Api\V1\Visits\VisitIngestController;
use App\Http\Controllers\Api\V1\Portal\PortalAuthController;
use App\Http\Controllers\Api\V1\Portal\PortalInvoiceController;
use App\Http\Controllers\Api\V1\Portal\PortalTicketController;
use App\Http\Controllers\Api\V1\Portal\PortalQuotationController;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('auth/login',           [AuthController::class, 'login']);
        Route::post('auth/register',        [AuthController::class, 'register']);
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password',  [AuthController::class, 'resetPassword']);
    });

    // Public branding lookup for the login/register screens — read-only, advisory (see
    // TenantResolver). Covered by the default 60/min API throttle, not the strict auth one.
    Route::get('tenant-info', [TenantInfoController::class, 'show']);

    // Website visitor tracking — the app's ONLY public write endpoint (docs/VISITS_SCOPE.md).
    // The beacon answers 204 for every outcome, including an unknown site key, so it is never
    // a tenant-enumeration oracle. Throttled per IP well above real page-view rates but far
    // below what a script could push; `identify` carries its own much tighter limit inside the
    // controller, since a form submit and a page view have nothing like the same frequency.
    Route::get ('visits/t.js',    [VisitIngestController::class, 'script']);
    Route::post('visits/collect', [VisitIngestController::class, 'collect'])->middleware('throttle:300,1');

    // Authenticated, tenant-safe attachment delivery. Files live on the PRIVATE disk and
    // are never web-served; the attachment models hand out short-lived RELATIVE signed
    // links (host/scheme-agnostic, so they survive the reverse proxy). The signature is
    // minted only for rows the authorized API caller could already see.
    Route::get('attachments/{type}/{id}', [AttachmentController::class, 'show'])
        ->middleware(ValidateSignature::relative())
        ->whereIn('type', ['att', 'chat', 'email'])->whereNumber('id')
        ->name('attachments.show');

    // `2fa` gates every route in this group for users who have the second factor enabled
    // (it is a no-op for everyone else). The four routes below opt out with
    // withoutMiddleware because a pending token must be able to complete or abandon the
    // challenge: verify exchanges it for a verified token, logout ends the session, and
    // me/refresh keep the client alive while it does so. Everything else stays gated —
    // change-password in particular, since it takes only the current password and would
    // otherwise let a password-only attacker rotate the credential and strand the factor.
    Route::middleware(['jwt.auth', 'scope.company', 'audit', '2fa'])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout'])->withoutMiddleware('2fa');
            Route::post('refresh',         [AuthController::class, 'refresh'])->withoutMiddleware('2fa');
            Route::get ('me',              [AuthController::class, 'me'])->withoutMiddleware('2fa');
            Route::put ('profile',         [AuthController::class, 'updateProfile']);
            Route::post('avatar',          [AuthController::class, 'updateAvatar']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
            Route::prefix('2fa')->group(function () {
                Route::post('enable',  [TwoFactorController::class, 'enable']);
                Route::post('confirm', [TwoFactorController::class, 'confirm']);
                Route::post('disable', [TwoFactorController::class, 'disable']);
                Route::post('verify',  [TwoFactorController::class, 'verify'])->withoutMiddleware('2fa');
            });
        });

        Route::prefix('dashboard')->middleware('feature:dashboard')->group(function () {
            Route::get('summary',         [DashboardController::class, 'summary']);
            Route::get('kpis',            [DashboardController::class, 'kpis']);
            Route::get('sales-chart',     [DashboardController::class, 'salesChart']);
            Route::get('purchase-chart',  [DashboardController::class, 'purchaseChart']);
            Route::get('revenue-chart',   [DashboardController::class, 'revenueChart']);
            Route::get('pipeline',        [DashboardController::class, 'pipeline']);
            Route::get('top-performers',  [DashboardController::class, 'topPerformers']);
            Route::get('tasks-summary',   [DashboardController::class, 'tasksSummary']);
            Route::get('recent-activity', [DashboardController::class, 'recentActivity']);
            Route::get('ai-insights',     [DashboardController::class, 'aiInsights']);
        });
        Route::get('my-work', MyWorkController::class)->middleware(['feature:dashboard','permission:dashboard.view']);

        // Module 2 — Leads
        Route::prefix('leads')->middleware('feature:leads')->group(function () {
            Route::get   ('/',      [LeadController::class, 'index'])->middleware('permission:leads.view');
            Route::get   ('stats',  [LeadController::class, 'stats'])->middleware('permission:leads.view');
            Route::get   ('meta',   [LeadController::class, 'meta'])->middleware('permission:leads.view');
            Route::post  ('/',      [LeadController::class, 'store'])->middleware('permission:leads.create');
            Route::get   ('{id}',   [LeadController::class, 'show'])->middleware('permission:leads.view')->whereNumber('id');
            Route::put   ('{id}',   [LeadController::class, 'update'])->middleware('permission:leads.update')->whereNumber('id');
            Route::delete('{id}',   [LeadController::class, 'destroy'])->middleware('permission:leads.delete')->whereNumber('id');
            Route::get   ('{id}/score',   [LeadController::class, 'score'])->middleware('permission:leads.score')->whereNumber('id');
            // What this prospect read before they got in touch. Needs BOTH permissions: it is
            // lead data reached through the visits module.
            Route::get   ('{id}/visits',  [VisitController::class, 'forLead'])
                ->middleware(['permission:leads.view', 'permission:visits.view', 'feature:visits'])->whereNumber('id');
            Route::post  ('{id}/notes',   [LeadController::class, 'addNote'])->middleware('permission:leads.update')->whereNumber('id');
            Route::post  ('{id}/assign',  [LeadController::class, 'assign'])->middleware('permission:leads.assign')->whereNumber('id');
            Route::post  ('{id}/convert', [LeadController::class, 'convert'])->middleware('permission:leads.convert')->whereNumber('id');
            Route::post  ('{id}/attachments',      [LeadController::class, 'storeAttachment'])->middleware('permission:leads.update')->whereNumber('id');
            Route::delete('{id}/attachments/{att}', [LeadController::class, 'destroyAttachment'])->middleware('permission:leads.update')->whereNumber('id')->whereNumber('att');
        });

        // Module 3 — Customers
        Route::prefix('customers')->middleware('feature:customers')->group(function () {
            Route::get   ('/',      [CustomerController::class, 'index'])->middleware('permission:customers.view');
            Route::get   ('stats',  [CustomerController::class, 'stats'])->middleware('permission:customers.view');
            Route::get   ('meta',   [CustomerController::class, 'meta'])->middleware('permission:customers.view');
            Route::post  ('/',      [CustomerController::class, 'store'])->middleware('permission:customers.create');
            Route::get   ('{id}',   [CustomerController::class, 'show'])->middleware('permission:customers.view')->whereNumber('id');
            Route::get   ('{id}/timeline', [CustomerController::class, 'timeline'])->middleware('permission:customers.view')->whereNumber('id');
            Route::put   ('{id}',   [CustomerController::class, 'update'])->middleware('permission:customers.update')->whereNumber('id');
            Route::delete('{id}',   [CustomerController::class, 'destroy'])->middleware('permission:customers.delete')->whereNumber('id');
            Route::post  ('{id}/notes',              [CustomerController::class, 'addNote'])->middleware('permission:customers.update')->whereNumber('id');
            Route::post  ('{id}/contacts',           [CustomerController::class, 'storeContact'])->middleware('permission:customers.update')->whereNumber('id');
            Route::put   ('{id}/contacts/{contact}', [CustomerController::class, 'updateContact'])->middleware('permission:customers.update')->whereNumber('id')->whereNumber('contact');
            Route::delete('{id}/contacts/{contact}', [CustomerController::class, 'destroyContact'])->middleware('permission:customers.update')->whereNumber('id')->whereNumber('contact');
            Route::put   ('{id}/contacts/{contact}/portal', [CustomerController::class, 'updateContactPortal'])->middleware('permission:customers.update')->whereNumber('id')->whereNumber('contact');
        });

        // Core CRM — Contacts (people), independently searchable from their Account.
        Route::prefix('contacts')->middleware('feature:contacts')->group(function () {
            Route::get   ('/',    [ContactController::class, 'index'])->middleware('permission:contacts.view');
            Route::get   ('meta', [ContactController::class, 'meta'])->middleware('permission:contacts.view');
            Route::post  ('/',    [ContactController::class, 'store'])->middleware('permission:contacts.create');
            Route::get   ('{id}', [ContactController::class, 'show'])->middleware('permission:contacts.view')->whereNumber('id');
            Route::put   ('{id}', [ContactController::class, 'update'])->middleware('permission:contacts.update')->whereNumber('id');
            Route::delete('{id}', [ContactController::class, 'destroy'])->middleware('permission:contacts.delete')->whereNumber('id');
        });

        // Module 4 — Pipeline / Deals
        Route::prefix('deals')->middleware('feature:deals')->group(function () {
            Route::get   ('/',      [DealController::class, 'index'])->middleware('permission:deals.view');
            Route::get   ('board',  [DealController::class, 'board'])->middleware('permission:deals.view');
            Route::get   ('stats',  [DealController::class, 'stats'])->middleware('permission:deals.view');
            Route::get   ('meta',   [DealController::class, 'meta'])->middleware('permission:deals.view');
            Route::post  ('/',      [DealController::class, 'store'])->middleware('permission:deals.create');
            Route::get   ('{id}',   [DealController::class, 'show'])->middleware('permission:deals.view')->whereNumber('id');
            Route::put   ('{id}',   [DealController::class, 'update'])->middleware('permission:deals.update')->whereNumber('id');
            Route::delete('{id}',   [DealController::class, 'destroy'])->middleware('permission:deals.delete')->whereNumber('id');
            Route::post  ('{id}/move',      [DealController::class, 'move'])->middleware('permission:deals.change_stage')->whereNumber('id');
            Route::post  ('{id}/lost',      [DealController::class, 'markLost'])->middleware('permission:deals.change_stage')->whereNumber('id');
            Route::post  ('{id}/notes',     [DealController::class, 'addNote'])->middleware('permission:deals.update')->whereNumber('id');
            Route::post  ('{id}/attachments',       [DealController::class, 'storeAttachment'])->middleware('permission:deals.update')->whereNumber('id');
            Route::delete('{id}/attachments/{att}', [DealController::class, 'destroyAttachment'])->middleware('permission:deals.update')->whereNumber('id')->whereNumber('att');
        });

        // Blueprint config for pipeline stages — required fields + allowed transitions only,
        // no stage create/delete/rename here.
        Route::prefix('pipelines')->middleware('feature:deals')->group(function () {
            Route::put('{pipelineId}/stages/{stageId}', [DealController::class, 'updateStageBlueprint'])
                ->middleware('permission:pipelines.manage')->whereNumber('pipelineId')->whereNumber('stageId');
        });

        // Project Management — delivery work, separated from CRM follow-up activities.
        Route::prefix('projects')->middleware('feature:projects')->group(function () {
            Route::get('templates', [ProjectOperationsController::class, 'templates'])->middleware('permission:project_templates.view');
            Route::post('templates/from-project/{projectId}', [ProjectOperationsController::class, 'storeTemplate'])->middleware('permission:project_templates.create')->whereNumber('projectId');
            Route::put('templates/{id}', [ProjectOperationsController::class, 'updateTemplate'])->middleware('permission:project_templates.update')->whereNumber('id');
            Route::delete('templates/{id}', [ProjectOperationsController::class, 'destroyTemplate'])->middleware('permission:project_templates.delete')->whereNumber('id');
            Route::post('templates/{id}/create-project', [ProjectOperationsController::class, 'createFromTemplate'])->middleware('permission:projects.create')->whereNumber('id');
            Route::get('automation-rules', [ProjectOperationsController::class, 'rules'])->middleware('permission:project_automation.view');
            Route::post('automation-rules', [ProjectOperationsController::class, 'storeRule'])->middleware('permission:project_automation.create');
            Route::put('automation-rules/{id}', [ProjectOperationsController::class, 'updateRule'])->middleware('permission:project_automation.update')->whereNumber('id');
            Route::delete('automation-rules/{id}', [ProjectOperationsController::class, 'destroyRule'])->middleware('permission:project_automation.delete')->whereNumber('id');
            Route::get('management-report', [ProjectOperationsController::class, 'report'])->middleware('permission:project_reports.view');
            Route::get('/', [ProjectController::class, 'index'])->middleware('permission:projects.view');
            Route::get('stats', [ProjectController::class, 'stats'])->middleware('permission:projects.view');
            Route::get('meta', [ProjectController::class, 'meta'])->middleware('permission:projects.view');
            Route::get('workload', [ProjectController::class, 'workload'])->middleware('permission:projects.view');
            Route::post('from-deal/{dealId}', [ProjectController::class, 'fromDeal'])->middleware('permission:projects.create')->whereNumber('dealId');
            Route::post('/', [ProjectController::class, 'store'])->middleware('permission:projects.create');
            Route::get('{id}', [ProjectController::class, 'show'])->middleware('permission:projects.view')->whereNumber('id');
            Route::put('{id}', [ProjectController::class, 'update'])->middleware('permission:projects.update')->whereNumber('id');
            Route::delete('{id}', [ProjectController::class, 'destroy'])->middleware('permission:projects.delete')->whereNumber('id');
            Route::post('{id}/members', [ProjectController::class, 'addMember'])->middleware('permission:projects.manage_members')->whereNumber('id');
            Route::delete('{id}/members/{userId}', [ProjectController::class, 'removeMember'])->middleware('permission:projects.manage_members')->whereNumber('id')->whereNumber('userId');
            Route::post('{id}/milestones', [ProjectController::class, 'storeMilestone'])->middleware('permission:projects.update')->whereNumber('id');
            Route::put('{id}/milestones/{milestoneId}', [ProjectController::class, 'updateMilestone'])->middleware('permission:projects.update')->whereNumber('id')->whereNumber('milestoneId');
            Route::delete('{id}/milestones/{milestoneId}', [ProjectController::class, 'destroyMilestone'])->middleware('permission:projects.update')->whereNumber('id')->whereNumber('milestoneId');
            Route::post('{id}/tasks', [ProjectController::class, 'storeTask'])->middleware('permission:project_tasks.create')->whereNumber('id');
            Route::get('{id}/tasks/{taskId}', [ProjectController::class, 'showTask'])->middleware('permission:project_tasks.view')->whereNumber('id')->whereNumber('taskId');
            Route::put('{id}/tasks/{taskId}', [ProjectController::class, 'updateTask'])->middleware('permission:project_tasks.update')->whereNumber('id')->whereNumber('taskId');
            Route::delete('{id}/tasks/{taskId}', [ProjectController::class, 'destroyTask'])->middleware('permission:project_tasks.delete')->whereNumber('id')->whereNumber('taskId');
            Route::post('{id}/tasks/{taskId}/comments', [ProjectController::class, 'storeComment'])->middleware('permission:project_tasks.comment')->whereNumber('id')->whereNumber('taskId');
            Route::post('{id}/tasks/{taskId}/dependencies', [ProjectController::class, 'storeDependency'])->middleware('permission:project_tasks.update')->whereNumber('id')->whereNumber('taskId');
            Route::delete('{id}/tasks/{taskId}/dependencies/{dependsOnId}', [ProjectController::class, 'destroyDependency'])->middleware('permission:project_tasks.update')->whereNumber('id')->whereNumber('taskId')->whereNumber('dependsOnId');
            Route::post('{id}/tasks/{taskId}/attachments', [ProjectController::class, 'storeTaskAttachment'])->middleware('permission:project_tasks.upload')->whereNumber('id')->whereNumber('taskId');
            Route::delete('{id}/tasks/{taskId}/attachments/{attachmentId}', [ProjectController::class, 'destroyTaskAttachment'])->middleware('permission:project_tasks.upload')->whereNumber('id')->whereNumber('taskId')->whereNumber('attachmentId');
            Route::get('{id}/time-entries', [ProjectController::class, 'timeEntries'])->middleware('permission:timesheets.view')->whereNumber('id');
            Route::post('{id}/time-entries', [ProjectController::class, 'storeTimeEntry'])->middleware('permission:timesheets.create')->whereNumber('id');
            Route::put('{id}/time-entries/{entryId}', [ProjectController::class, 'updateTimeEntry'])->middleware('permission:timesheets.update')->whereNumber('id')->whereNumber('entryId');
            Route::delete('{id}/time-entries/{entryId}', [ProjectController::class, 'destroyTimeEntry'])->middleware('permission:timesheets.delete')->whereNumber('id')->whereNumber('entryId');
            Route::post('{id}/time-entries/{entryId}/decision', [ProjectController::class, 'decideTimeEntry'])->middleware('permission:timesheets.approve')->whereNumber('id')->whereNumber('entryId');
        });

        // Module 5 — Activities (tasks, meetings, calls, reminders)
        Route::prefix('activities')->middleware('feature:activities')->group(function () {
            Route::get ('feed',   [ActivityController::class, 'feed'])->middleware('permission:activities.view');
            Route::get ('stats',  [ActivityController::class, 'stats'])->middleware('permission:activities.view');
            Route::get ('meta',   [ActivityController::class, 'meta'])->middleware('permission:activities.view');

            Route::get   ('tasks',              [ActivityController::class, 'tasks'])->middleware('permission:activities.view');
            Route::post  ('tasks',              [ActivityController::class, 'storeTask'])->middleware('permission:activities.create');
            Route::put   ('tasks/{id}',         [ActivityController::class, 'updateTask'])->middleware('permission:activities.update')->whereNumber('id');
            Route::post  ('tasks/{id}/complete',[ActivityController::class, 'completeTask'])->middleware('permission:activities.update')->whereNumber('id');
            Route::delete('tasks/{id}',         [ActivityController::class, 'destroyTask'])->middleware('permission:activities.delete')->whereNumber('id');

            Route::get   ('meetings',      [ActivityController::class, 'meetings'])->middleware('permission:activities.view');
            Route::post  ('meetings',      [ActivityController::class, 'storeMeeting'])->middleware('permission:activities.create');
            Route::put   ('meetings/{id}', [ActivityController::class, 'updateMeeting'])->middleware('permission:activities.update')->whereNumber('id');
            Route::delete('meetings/{id}', [ActivityController::class, 'destroyMeeting'])->middleware('permission:activities.delete')->whereNumber('id');

            Route::get   ('calls',      [ActivityController::class, 'calls'])->middleware('permission:activities.view');
            Route::post  ('calls',      [ActivityController::class, 'storeCall'])->middleware('permission:activities.create');
            Route::put   ('calls/{id}', [ActivityController::class, 'updateCall'])->middleware('permission:activities.update')->whereNumber('id');
            Route::delete('calls/{id}', [ActivityController::class, 'destroyCall'])->middleware('permission:activities.delete')->whereNumber('id');

            Route::get   ('reminders',              [ActivityController::class, 'reminders'])->middleware('permission:activities.view');
            Route::post  ('reminders',              [ActivityController::class, 'storeReminder'])->middleware('permission:activities.create');
            Route::post  ('reminders/{id}/complete',[ActivityController::class, 'completeReminder'])->middleware('permission:activities.update')->whereNumber('id');
            Route::delete('reminders/{id}',         [ActivityController::class, 'destroyReminder'])->middleware('permission:activities.delete')->whereNumber('id');
        });

        // Module 7 — Sales: catalog
        Route::prefix('products')->middleware('feature:products')->group(function () {
            Route::get   ('/',     [ProductController::class, 'index'])->middleware('permission:products.view');
            Route::get   ('stats', [ProductController::class, 'stats'])->middleware('permission:products.view');
            Route::get   ('meta',  [ProductController::class, 'meta'])->middleware('permission:products.view');
            Route::post  ('/',     [ProductController::class, 'store'])->middleware('permission:products.create');
            Route::post  ('categories', [ProductController::class, 'storeCategory'])->middleware('permission:products.create');
            Route::post  ('tax-rates',  [ProductController::class, 'storeTaxRate'])->middleware('permission:products.create');
            Route::get   ('{id}',  [ProductController::class, 'show'])->middleware('permission:products.view')->whereNumber('id');
            Route::put   ('{id}',  [ProductController::class, 'update'])->middleware('permission:products.update')->whereNumber('id');
            Route::delete('{id}',  [ProductController::class, 'destroy'])->middleware('permission:products.delete')->whereNumber('id');
        });

        // Zoho gap #7 — Price Books (per-customer/segment/currency price lists)
        Route::prefix('price-books')->middleware('feature:price_books')->group(function () {
            Route::get   ('/',        [PriceBookController::class, 'index'])->middleware('permission:price_books.view');
            Route::get   ('meta',     [PriceBookController::class, 'meta'])->middleware('permission:price_books.view');
            Route::post  ('resolve',  [PriceBookController::class, 'resolve'])->middleware('permission:price_books.view');
            Route::post  ('/',        [PriceBookController::class, 'store'])->middleware('permission:price_books.create');
            Route::get   ('{id}',     [PriceBookController::class, 'show'])->middleware('permission:price_books.view')->whereNumber('id');
            Route::put   ('{id}',     [PriceBookController::class, 'update'])->middleware('permission:price_books.update')->whereNumber('id');
            Route::put   ('{id}/entries', [PriceBookController::class, 'syncEntries'])->middleware('permission:price_books.update')->whereNumber('id');
            Route::delete('{id}',     [PriceBookController::class, 'destroy'])->middleware('permission:price_books.delete')->whereNumber('id');
        });

        // Module 7 — Sales: quotations
        Route::prefix('quotations')->middleware('feature:quotations')->group(function () {
            Route::get   ('/',    [QuotationController::class, 'index'])->middleware('permission:quotations.view');
            Route::post  ('/',    [QuotationController::class, 'store'])->middleware('permission:quotations.create');
            Route::get   ('{id}', [QuotationController::class, 'show'])->middleware('permission:quotations.view')->whereNumber('id');
            Route::put   ('{id}', [QuotationController::class, 'update'])->middleware('permission:quotations.update')->whereNumber('id');
            Route::delete('{id}', [QuotationController::class, 'destroy'])->middleware('permission:quotations.delete')->whereNumber('id');
            Route::post  ('{id}/status',  [QuotationController::class, 'setStatus'])->middleware('permission:quotations.update')->whereNumber('id');
            Route::post  ('{id}/send',    [QuotationController::class, 'send'])->middleware('permission:quotations.send')->whereNumber('id');
            Route::post  ('{id}/convert', [QuotationController::class, 'convert'])->middleware('permission:orders.create')->whereNumber('id');
        });

        // Module 7 — Sales: orders
        Route::prefix('sales-orders')->middleware('feature:orders')->group(function () {
            Route::get   ('/',    [SalesOrderController::class, 'index'])->middleware('permission:orders.view');
            Route::post  ('/',    [SalesOrderController::class, 'store'])->middleware('permission:orders.create');
            Route::get   ('{id}', [SalesOrderController::class, 'show'])->middleware('permission:orders.view')->whereNumber('id');
            Route::put   ('{id}', [SalesOrderController::class, 'update'])->middleware('permission:orders.update')->whereNumber('id');
            Route::delete('{id}', [SalesOrderController::class, 'destroy'])->middleware('permission:orders.delete')->whereNumber('id');
            Route::post  ('{id}/status',  [SalesOrderController::class, 'setStatus'])->middleware('permission:orders.update')->whereNumber('id');
            Route::post  ('{id}/convert', [SalesOrderController::class, 'convert'])->middleware('permission:invoices.create')->whereNumber('id');
        });

        // Module 7 — Sales: invoices + payments
        Route::prefix('invoices')->middleware('feature:invoices')->group(function () {
            Route::get   ('/',    [InvoiceController::class, 'index'])->middleware('permission:invoices.view');
            Route::get   ('stats',[InvoiceController::class, 'stats'])->middleware('permission:invoices.view');
            Route::post  ('/',    [InvoiceController::class, 'store'])->middleware('permission:invoices.create');
            Route::get   ('{id}', [InvoiceController::class, 'show'])->middleware('permission:invoices.view')->whereNumber('id');
            Route::put   ('{id}', [InvoiceController::class, 'update'])->middleware('permission:invoices.update')->whereNumber('id');
            Route::delete('{id}', [InvoiceController::class, 'destroy'])->middleware('permission:invoices.delete')->whereNumber('id');
            Route::post  ('{id}/status', [InvoiceController::class, 'setStatus'])->middleware('permission:invoices.update')->whereNumber('id');
            Route::post  ('{id}/pay',    [InvoiceController::class, 'pay'])->middleware('permission:payments.create')->whereNumber('id');
            // Customer credits applied to this invoice (see the credits prefix below).
            Route::get ('{id}/available-credits', [CustomerCreditController::class, 'availableForInvoice'])
                ->middleware('permission:credits.view')->whereNumber('id');
            Route::post('{id}/apply-credit',      [CustomerCreditController::class, 'applyToInvoice'])
                ->middleware('permission:credits.apply')->whereNumber('id');
        });
        Route::prefix('payments')->middleware('feature:payments')->group(function () {
            Route::get   ('/',    [PaymentController::class, 'index'])->middleware('permission:payments.view');
            Route::delete('{id}', [PaymentController::class, 'destroy'])->middleware('permission:payments.delete')->whereNumber('id');
        });

        // Customer credits (credit notes) — money owed back to a customer, from an
        // overpayment, an invoice reduced after payment, or issued by hand.
        Route::prefix('customer-credits')->middleware('feature:credits')->group(function () {
            Route::get ('/',          [CustomerCreditController::class, 'index'])->middleware('permission:credits.view');
            Route::get ('stats',      [CustomerCreditController::class, 'stats'])->middleware('permission:credits.view');
            Route::get ('meta',       [CustomerCreditController::class, 'meta'])->middleware('permission:credits.view');
            Route::post('/',          [CustomerCreditController::class, 'store'])->middleware('permission:credits.create');
            Route::get ('{id}',       [CustomerCreditController::class, 'show'])->middleware('permission:credits.view')->whereNumber('id');
            Route::post('{id}/void',  [CustomerCreditController::class, 'void'])->middleware('permission:credits.delete')->whereNumber('id');
        });

        // Module 9 — Inventory: warehouses
        Route::prefix('warehouses')->middleware('feature:warehouses')->group(function () {
            Route::get   ('/',    [WarehouseController::class, 'index'])->middleware('permission:warehouses.view');
            Route::post  ('/',    [WarehouseController::class, 'store'])->middleware('permission:warehouses.create');
            Route::put   ('{id}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.update')->whereNumber('id');
            Route::delete('{id}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete')->whereNumber('id');
        });

        // Module 9 — Inventory: stock, movements, transfers, barcodes
        Route::prefix('inventory')->middleware('feature:inventory')->group(function () {
            Route::get ('stock',     [InventoryController::class, 'stock'])->middleware('permission:inventory.view');
            Route::get ('stats',     [InventoryController::class, 'stats'])->middleware('permission:inventory.view');
            Route::get ('meta',      [InventoryController::class, 'meta'])->middleware('permission:inventory.view');
            Route::get ('movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.view');
            Route::post('adjust',    [InventoryController::class, 'adjust'])->middleware('permission:inventory.adjust');
            Route::post('move',      [InventoryController::class, 'move'])->middleware('permission:inventory.adjust');

            Route::get ('transfers',        [InventoryController::class, 'transfers'])->middleware('permission:inventory.view');
            Route::post('transfers',        [InventoryController::class, 'storeTransfer'])->middleware('permission:inventory.transfer');
            Route::get ('transfers/{id}',   [InventoryController::class, 'showTransfer'])->middleware('permission:inventory.view')->whereNumber('id');
            Route::post('transfers/{id}/action', [InventoryController::class, 'transferAction'])->middleware('permission:inventory.transfer')->whereNumber('id');

            Route::get   ('barcodes/{productId}', [InventoryController::class, 'barcodes'])->middleware('permission:inventory.view')->whereNumber('productId');
            Route::post  ('barcodes',             [InventoryController::class, 'storeBarcode'])->middleware('permission:inventory.update');
            Route::delete('barcodes/{id}',        [InventoryController::class, 'destroyBarcode'])->middleware('permission:inventory.update')->whereNumber('id');
        });

        // Phase B — Manufacturing / BOM (Enterprise-only). Immediate atomic builds via the stock ledger.
        Route::prefix('manufacturing')->middleware('feature:manufacturing')->group(function () {
            Route::get ('boms',   [ManufacturingController::class, 'boms'])->middleware('permission:manufacturing.view');
            Route::get ('meta',   [ManufacturingController::class, 'meta'])->middleware('permission:manufacturing.view');
            Route::get ('builds', [ManufacturingController::class, 'builds'])->middleware('permission:manufacturing.view');
            Route::post('builds', [ManufacturingController::class, 'storeBuild'])->middleware('permission:manufacturing.build');
            Route::get ('builds/{id}', [ManufacturingController::class, 'showBuild'])->middleware('permission:manufacturing.view')->whereNumber('id');
            Route::get ('products/{id}/bom',          [ManufacturingController::class, 'getBom'])->middleware('permission:manufacturing.view')->whereNumber('id');
            Route::put ('products/{id}/bom',          [ManufacturingController::class, 'setBom'])->middleware('permission:manufacturing.manage')->whereNumber('id');
            Route::get ('products/{id}/availability', [ManufacturingController::class, 'availability'])->middleware('permission:manufacturing.view')->whereNumber('id');
        });

        // Module 8 — Purchase: vendors
        Route::prefix('vendors')->middleware('feature:vendors')->group(function () {
            Route::get   ('/',    [VendorController::class, 'index'])->middleware('permission:vendors.view');
            Route::post  ('/',    [VendorController::class, 'store'])->middleware('permission:vendors.create');
            Route::get   ('{id}', [VendorController::class, 'show'])->middleware('permission:vendors.view')->whereNumber('id');
            Route::put   ('{id}', [VendorController::class, 'update'])->middleware('permission:vendors.update')->whereNumber('id');
            Route::delete('{id}', [VendorController::class, 'destroy'])->middleware('permission:vendors.delete')->whereNumber('id');
        });

        // Module 8 — Purchase: requests
        Route::prefix('purchase-requests')->middleware('feature:purchase_requests')->group(function () {
            Route::get   ('/',    [PurchaseRequestController::class, 'index'])->middleware('permission:purchase_requests.view');
            Route::post  ('/',    [PurchaseRequestController::class, 'store'])->middleware('permission:purchase_requests.create');
            Route::get   ('{id}', [PurchaseRequestController::class, 'show'])->middleware('permission:purchase_requests.view')->whereNumber('id');
            Route::put   ('{id}', [PurchaseRequestController::class, 'update'])->middleware('permission:purchase_requests.update')->whereNumber('id');
            Route::delete('{id}', [PurchaseRequestController::class, 'destroy'])->middleware('permission:purchase_requests.delete')->whereNumber('id');
            Route::post  ('{id}/submit',  [PurchaseRequestController::class, 'submit'])->middleware('permission:purchase_requests.update')->whereNumber('id');
            Route::post  ('{id}/convert', [PurchaseRequestController::class, 'convert'])->middleware('permission:purchase_orders.create')->whereNumber('id');
        });

        // Module 8 — Purchase: orders
        Route::prefix('purchase-orders')->middleware('feature:purchase_orders')->group(function () {
            Route::get   ('/',    [PurchaseOrderController::class, 'index'])->middleware('permission:purchase_orders.view');
            Route::get   ('stats',[PurchaseOrderController::class, 'stats'])->middleware('permission:purchase_orders.view');
            Route::post  ('/',    [PurchaseOrderController::class, 'store'])->middleware('permission:purchase_orders.create');
            Route::get   ('{id}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchase_orders.view')->whereNumber('id');
            Route::put   ('{id}', [PurchaseOrderController::class, 'update'])->middleware('permission:purchase_orders.update')->whereNumber('id');
            Route::delete('{id}', [PurchaseOrderController::class, 'destroy'])->middleware('permission:purchase_orders.delete')->whereNumber('id');
            Route::post  ('{id}/confirm', [PurchaseOrderController::class, 'confirm'])->middleware('permission:purchase_orders.update')->whereNumber('id');
            Route::post  ('{id}/receive', [PurchaseOrderController::class, 'receive'])->middleware('permission:inventory.adjust')->whereNumber('id');
            Route::post  ('{id}/close',   [PurchaseOrderController::class, 'close'])->middleware('permission:purchase_orders.update')->whereNumber('id');
            Route::post  ('{id}/cancel',  [PurchaseOrderController::class, 'cancel'])->middleware('permission:purchase_orders.update')->whereNumber('id');
        });

        // Module 8 — Purchase: approvals
        Route::prefix('approvals')->middleware('feature:purchase_orders')->group(function () {
            Route::get ('mine',       [ApprovalController::class, 'mine']);
            Route::post('{id}/act',   [ApprovalController::class, 'act'])->whereNumber('id');
            Route::get   ('workflows',      [ApprovalController::class, 'workflows'])->middleware('permission:purchase_orders.approve');
            Route::post  ('workflows',      [ApprovalController::class, 'storeWorkflow'])->middleware('permission:purchase_orders.approve');
            Route::put   ('workflows/{id}', [ApprovalController::class, 'updateWorkflow'])->middleware('permission:purchase_orders.approve')->whereNumber('id');
            Route::delete('workflows/{id}', [ApprovalController::class, 'destroyWorkflow'])->middleware('permission:purchase_orders.approve')->whereNumber('id');
        });

        // Module 10 — Helpdesk
        Route::prefix('tickets')->middleware('feature:tickets')->group(function () {
            Route::get   ('/',     [TicketController::class, 'index'])->middleware('permission:tickets.view');
            Route::get   ('stats', [TicketController::class, 'stats'])->middleware('permission:tickets.view');
            Route::get   ('meta',  [TicketController::class, 'meta'])->middleware('permission:tickets.view');
            Route::post  ('/',     [TicketController::class, 'store'])->middleware('permission:tickets.create');
            Route::get   ('{id}',  [TicketController::class, 'show'])->middleware('permission:tickets.view')->whereNumber('id');
            Route::put   ('{id}',  [TicketController::class, 'update'])->middleware('permission:tickets.update')->whereNumber('id');
            Route::delete('{id}',  [TicketController::class, 'destroy'])->middleware('permission:tickets.delete')->whereNumber('id');
            Route::post  ('{id}/replies',  [TicketController::class, 'reply'])->middleware('permission:tickets.update')->whereNumber('id');
            Route::post  ('{id}/assign',   [TicketController::class, 'assign'])->middleware('permission:tickets.assign')->whereNumber('id');
            Route::post  ('{id}/status',   [TicketController::class, 'setStatus'])->middleware('permission:tickets.update')->whereNumber('id');
            Route::post  ('{id}/escalate', [TicketController::class, 'escalate'])->middleware('permission:tickets.update')->whereNumber('id');
        });

        // Zoho gap #8 — Knowledge Base ("Solutions")
        Route::prefix('kb')->middleware('feature:kb')->group(function () {
            Route::get   ('articles',      [KbController::class, 'index'])->middleware('permission:kb.view');
            Route::get   ('articles/meta', [KbController::class, 'meta'])->middleware('permission:kb.view');
            Route::post  ('articles',      [KbController::class, 'store'])->middleware('permission:kb.create');
            Route::post  ('categories',    [KbController::class, 'storeCategory'])->middleware('permission:kb.create');
            Route::get   ('articles/{id}', [KbController::class, 'show'])->middleware('permission:kb.view')->whereNumber('id');
            Route::put   ('articles/{id}', [KbController::class, 'update'])->middleware('permission:kb.update')->whereNumber('id');
            Route::delete('articles/{id}', [KbController::class, 'destroy'])->middleware('permission:kb.delete')->whereNumber('id');
        });

        // Zoho gap #9 — Documents library (private disk, authenticated download)
        Route::prefix('documents')->middleware('feature:documents')->group(function () {
            Route::get   ('/',            [DocumentController::class, 'index'])->middleware('permission:documents.view');
            Route::get   ('meta',         [DocumentController::class, 'meta'])->middleware('permission:documents.view');
            Route::post  ('/',            [DocumentController::class, 'store'])->middleware('permission:documents.create');
            Route::post  ('folders',      [DocumentController::class, 'storeFolder'])->middleware('permission:documents.create');
            Route::put   ('folders/{id}', [DocumentController::class, 'updateFolder'])->middleware('permission:documents.update')->whereNumber('id');
            Route::delete('folders/{id}', [DocumentController::class, 'destroyFolder'])->middleware('permission:documents.delete')->whereNumber('id');
            Route::get   ('{id}',          [DocumentController::class, 'show'])->middleware('permission:documents.view')->whereNumber('id');
            Route::get   ('{id}/download', [DocumentController::class, 'download'])->middleware('permission:documents.view')->whereNumber('id');
            Route::put   ('{id}',          [DocumentController::class, 'update'])->middleware('permission:documents.update')->whereNumber('id');
            Route::post  ('{id}/file',     [DocumentController::class, 'replaceFile'])->middleware('permission:documents.update')->whereNumber('id');
            Route::delete('{id}',          [DocumentController::class, 'destroy'])->middleware('permission:documents.delete')->whereNumber('id');
        });

        // Module 6 — Email
        Route::prefix('emails')->middleware('feature:email')->group(function () {
            Route::get   ('/',     [EmailController::class, 'index'])->middleware('permission:email.view');
            Route::get   ('stats', [EmailController::class, 'stats'])->middleware('permission:email.view');
            Route::get   ('meta',  [EmailController::class, 'meta'])->middleware('permission:email.view');
            Route::post  ('/',     [EmailController::class, 'store'])->middleware('permission:email.send');
            // SalesInbox — ingest a received message (provider webhook or manual push). Idempotent.
            Route::post  ('inbound', [EmailController::class, 'inbound'])->middleware('permission:email.send');
            Route::get   ('{id}',  [EmailController::class, 'show'])->middleware('permission:email.view')->whereNumber('id');
            Route::put   ('{id}',  [EmailController::class, 'update'])->middleware('permission:email.send')->whereNumber('id');
            Route::post  ('{id}/send', [EmailController::class, 'send'])->middleware('permission:email.send')->whereNumber('id');
            Route::delete('{id}',  [EmailController::class, 'destroy'])->middleware('permission:email.send')->whereNumber('id');
        });
        Route::prefix('email-templates')->middleware('feature:email')->group(function () {
            Route::post  ('/',     [EmailController::class, 'storeTemplate'])->middleware('permission:email.manage_templates');
            Route::put   ('{id}',  [EmailController::class, 'updateTemplate'])->middleware('permission:email.manage_templates')->whereNumber('id');
            Route::delete('{id}',  [EmailController::class, 'destroyTemplate'])->middleware('permission:email.manage_templates')->whereNumber('id');
        });
        Route::prefix('email-accounts')->middleware('feature:email')->group(function () {
            Route::get ('/',  [EmailController::class, 'accounts'])->middleware('permission:email.view');
            Route::post('/',  [EmailController::class, 'storeAccount'])->middleware('permission:email.manage_templates');
            Route::get   ('{id}',      [EmailController::class, 'showAccount'])->middleware('permission:email.view')->whereNumber('id');
            Route::put   ('{id}',      [EmailController::class, 'updateAccount'])->middleware('permission:email.manage_templates')->whereNumber('id');
            Route::post  ('{id}/test', [EmailController::class, 'testAccount'])->middleware('permission:email.manage_templates')->whereNumber('id');
            // SalesInbox — pull new mail over IMAP for this account now (manual trigger).
            Route::post  ('{id}/fetch', [EmailController::class, 'fetch'])->middleware('permission:email.manage_templates')->whereNumber('id');
        });

        // Module 11 — Marketing
        Route::prefix('campaigns')->middleware('feature:campaigns')->group(function () {
            Route::get   ('/',     [CampaignController::class, 'index'])->middleware('permission:campaigns.view');
            Route::get   ('stats', [CampaignController::class, 'stats'])->middleware('permission:campaigns.view');
            Route::get   ('meta',  [CampaignController::class, 'meta'])->middleware('permission:campaigns.view');
            Route::post  ('preview-audience', [CampaignController::class, 'preview'])->middleware('permission:campaigns.view');
            Route::post  ('/',     [CampaignController::class, 'store'])->middleware('permission:campaigns.create');
            Route::get   ('{id}',  [CampaignController::class, 'show'])->middleware('permission:campaigns.view')->whereNumber('id');
            Route::put   ('{id}',  [CampaignController::class, 'update'])->middleware('permission:campaigns.update')->whereNumber('id');
            Route::delete('{id}',  [CampaignController::class, 'destroy'])->middleware('permission:campaigns.delete')->whereNumber('id');
            Route::get   ('{id}/recipients', [CampaignController::class, 'recipients'])->middleware('permission:campaigns.view')->whereNumber('id');
            Route::post  ('{id}/launch',     [CampaignController::class, 'launch'])->middleware('permission:campaigns.launch')->whereNumber('id');
        });
        Route::prefix('sms-providers')->middleware('feature:campaigns')->group(function () {
            Route::post('/', [CampaignController::class, 'storeSmsProvider'])->middleware('permission:campaigns.create');
        });

        // Settings — user management, roles, organization
        Route::prefix('settings')->middleware('feature:settings')->group(function () {
            Route::get('company',        [OrganizationController::class, 'company'])->middleware('permission:settings.view');
            Route::put('company',        [OrganizationController::class, 'updateCompany'])->middleware('permission:settings.update');
            Route::get('branches',       [OrganizationController::class, 'branches'])->middleware('permission:settings.view');
            Route::post('branches',      [OrganizationController::class, 'storeBranch'])->middleware('permission:settings.update');
            Route::put('branches/{id}',  [OrganizationController::class, 'updateBranch'])->middleware('permission:settings.update')->whereNumber('id');
            Route::delete('branches/{id}', [OrganizationController::class, 'destroyBranch'])->middleware('permission:settings.update')->whereNumber('id');
            Route::get('departments',      [OrganizationController::class, 'departments'])->middleware('permission:settings.view');
            Route::post('departments',     [OrganizationController::class, 'storeDepartment'])->middleware('permission:settings.update');
            Route::put('departments/{id}', [OrganizationController::class, 'updateDepartment'])->middleware('permission:settings.update')->whereNumber('id');
            Route::delete('departments/{id}', [OrganizationController::class, 'destroyDepartment'])->middleware('permission:settings.update')->whereNumber('id');
        });

        Route::prefix('users')->middleware('feature:users')->group(function () {
            Route::get   ('/',    [UserManagementController::class, 'index'])->middleware('permission:users.view');
            Route::get   ('meta', [UserManagementController::class, 'meta'])->middleware('permission:users.view');
            Route::post  ('/',    [UserManagementController::class, 'store'])->middleware('permission:users.create');
            Route::put   ('{id}', [UserManagementController::class, 'update'])->middleware('permission:users.update')->whereNumber('id');
            Route::delete('{id}', [UserManagementController::class, 'destroy'])->middleware('permission:users.delete')->whereNumber('id');
        });

        Route::prefix('roles')->middleware('feature:roles')->group(function () {
            Route::get   ('/',           [RoleController::class, 'index'])->middleware('permission:roles.view');
            Route::get   ('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');
            Route::get   ('{id}',        [RoleController::class, 'show'])->middleware('permission:roles.view')->whereNumber('id');
            Route::post  ('/',           [RoleController::class, 'store'])->middleware('permission:roles.create');
            Route::put   ('{id}',        [RoleController::class, 'update'])->middleware('permission:roles.update')->whereNumber('id');
            Route::delete('{id}',        [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->whereNumber('id');
            Route::post  ('{id}/clone',  [RoleController::class, 'clone'])->middleware('permission:roles.create')->whereNumber('id');
        });

        Route::prefix('api-keys')->middleware('feature:api_keys')->group(function () {
            Route::get   ('/',    [ApiKeyController::class, 'index'])->middleware('permission:api_keys.view');
            Route::post  ('/',    [ApiKeyController::class, 'store'])->middleware('permission:api_keys.create');
            Route::get   ('{id}', [ApiKeyController::class, 'show'])->middleware('permission:api_keys.view')->whereNumber('id');
            Route::put   ('{id}', [ApiKeyController::class, 'update'])->middleware('permission:api_keys.update')->whereNumber('id');
            Route::delete('{id}', [ApiKeyController::class, 'destroy'])->middleware('permission:api_keys.delete')->whereNumber('id');
        });

        // Module 13 — Reports
        Route::prefix('reports')->middleware('feature:reports')->group(function () {
            Route::get ('datasets',    [ReportController::class, 'datasets'])->middleware('permission:reports.view');
            Route::post('run',         [ReportController::class, 'run'])->middleware('permission:reports.view');
            Route::get ('/',           [ReportController::class, 'index'])->middleware('permission:reports.view');
            Route::post('/',           [ReportController::class, 'store'])->middleware('permission:reports.create');
            Route::get ('dashboards',  [ReportController::class, 'dashboards'])->middleware('permission:reports.view');
            Route::post('dashboards',  [ReportController::class, 'storeDashboard'])->middleware('permission:reports.create');
            Route::put ('dashboards/{id}',    [ReportController::class, 'updateDashboard'])->middleware('permission:reports.create')->whereNumber('id');
            Route::delete('dashboards/{id}',  [ReportController::class, 'destroyDashboard'])->middleware('permission:reports.create')->whereNumber('id');
            // Before the {id} routes so the literal `exports` prefix wins. Exports live on the
            // private disk now; this is the only way to read one.
            Route::get ('exports/{exportId}/download', [ReportController::class, 'downloadExport'])
                ->middleware('permission:reports.export')->whereNumber('exportId');
            Route::get ('{id}',        [ReportController::class, 'show'])->middleware('permission:reports.view')->whereNumber('id');
            Route::put ('{id}',        [ReportController::class, 'update'])->middleware('permission:reports.create')->whereNumber('id');
            Route::delete('{id}',      [ReportController::class, 'destroy'])->middleware('permission:reports.create')->whereNumber('id');
            Route::post('{id}/export', [ReportController::class, 'export'])->middleware('permission:reports.export')->whereNumber('id');
            Route::get ('{id}/exports',[ReportController::class, 'exports'])->middleware('permission:reports.view')->whereNumber('id');
        });

        // Zoho gap #10 — Forecasts (quota vs. achieved per user/period)
        Route::prefix('forecasts')->middleware('feature:forecasts')->group(function () {
            Route::get('/',       [ForecastController::class, 'index'])->middleware('permission:forecasts.view');
            Route::get('meta',    [ForecastController::class, 'meta'])->middleware('permission:forecasts.view');
            Route::get('targets', [ForecastController::class, 'targets'])->middleware('permission:forecasts.view');
            Route::put('targets', [ForecastController::class, 'setTargets'])->middleware('permission:forecasts.manage');
        });

        // Module 14 — Workflow
        Route::prefix('workflows')->middleware('feature:workflows')->group(function () {
            Route::get   ('/',    [WorkflowController::class, 'index'])->middleware('permission:workflows.view');
            Route::get   ('stats',[WorkflowController::class, 'stats'])->middleware('permission:workflows.view');
            Route::get   ('meta', [WorkflowController::class, 'meta'])->middleware('permission:workflows.view');
            Route::post  ('/',    [WorkflowController::class, 'store'])->middleware('permission:workflows.create');
            Route::get   ('{id}', [WorkflowController::class, 'show'])->middleware('permission:workflows.view')->whereNumber('id');
            Route::put   ('{id}', [WorkflowController::class, 'update'])->middleware('permission:workflows.update')->whereNumber('id');
            Route::delete('{id}', [WorkflowController::class, 'destroy'])->middleware('permission:workflows.delete')->whereNumber('id');
            Route::post  ('{id}/run', [WorkflowController::class, 'run'])->middleware('permission:workflows.update')->whereNumber('id');
        });

        // Module 15 — AI Assistant
        Route::prefix('ai')->middleware(['permission:ai.use', 'feature:ai'])->group(function () {
            Route::get   ('conversations',        [AiController::class, 'conversations']);
            Route::post  ('conversations',        [AiController::class, 'createConversation']);
            Route::get   ('conversations/{id}',   [AiController::class, 'conversation'])->whereNumber('id');
            Route::post  ('conversations/{id}/messages', [AiController::class, 'send'])->whereNumber('id');
            Route::delete('conversations/{id}',   [AiController::class, 'destroyConversation'])->whereNumber('id');
            Route::get   ('insights',             [AiController::class, 'insights']);
            Route::post  ('insights/generate',    [AiController::class, 'generateInsights']);
            Route::post  ('insights/{id}/dismiss',[AiController::class, 'dismissInsight'])->whereNumber('id');
            Route::get   ('predictions',          [AiController::class, 'predictions']);
            Route::post  ('predictions/generate', [AiController::class, 'generatePredictions']);
        });

        // Module 12 — HR: employees
        Route::prefix('employees')->middleware('feature:employees')->group(function () {
            Route::get   ('/',    [EmployeeController::class, 'index'])->middleware('permission:employees.view');
            Route::get   ('stats',[EmployeeController::class, 'stats'])->middleware('permission:employees.view');
            Route::get   ('meta', [EmployeeController::class, 'meta'])->middleware('permission:employees.view');
            Route::post  ('/',    [EmployeeController::class, 'store'])->middleware('permission:employees.create');
            Route::get   ('{id}', [EmployeeController::class, 'show'])->middleware('permission:employees.view')->whereNumber('id');
            Route::put   ('{id}', [EmployeeController::class, 'update'])->middleware('permission:employees.update')->whereNumber('id');
            Route::delete('{id}', [EmployeeController::class, 'destroy'])->middleware('permission:employees.delete')->whereNumber('id');
        });

        // Module 12 — HR: attendance
        Route::prefix('attendance')->middleware('feature:attendance')->group(function () {
            Route::get ('/', [AttendanceController::class, 'index'])->middleware('permission:attendance.view');
            Route::post('/', [AttendanceController::class, 'store'])->middleware('permission:attendance.create');
        });

        // Module 12 — HR: leave
        Route::prefix('leave')->middleware('feature:leave')->group(function () {
            Route::get ('types',      [LeaveController::class, 'types'])->middleware('permission:leave.view');
            Route::post('types',      [LeaveController::class, 'storeType'])->middleware('permission:leave.create');
            Route::get ('requests',   [LeaveController::class, 'index'])->middleware('permission:leave.view');
            Route::post('requests',   [LeaveController::class, 'store'])->middleware('permission:leave.create');
            Route::post('requests/{id}/decide', [LeaveController::class, 'decide'])->middleware('permission:leave.approve')->whereNumber('id');
        });

        // Module 16 — Social / Chat Inbox
        Route::prefix('chat')->middleware('feature:chat')->group(function () {
            Route::get ('channels',                  [ChatController::class, 'channels'])->middleware('permission:chat.view');
            Route::get ('canned-responses',          [ChatController::class, 'cannedResponses'])->middleware('permission:chat.view');
            Route::get ('conversations',             [ChatController::class, 'index'])->middleware('permission:chat.view');
            Route::get ('conversations/counts',      [ChatController::class, 'counts'])->middleware('permission:chat.view');
            Route::get ('conversations/{id}',        [ChatController::class, 'show'])->middleware('permission:chat.view')->whereNumber('id');
            Route::post('conversations/{id}/reply',  [ChatController::class, 'reply'])->middleware('permission:chat.reply')->whereNumber('id');
            Route::post('conversations/{id}/assign', [ChatController::class, 'assign'])->middleware('permission:chat.assign')->whereNumber('id');
            Route::post('conversations/{id}/status', [ChatController::class, 'status'])->middleware('permission:chat.close')->whereNumber('id');
            Route::get ('conversations/{id}/crm-context', [ChatController::class, 'crmContext'])->middleware('permission:chat.view')->whereNumber('id');
            Route::post('conversations/{id}/crm-link', [ChatController::class, 'linkCrm'])->middleware('permission:chat.link_crm')->whereNumber('id');
            Route::delete('conversations/{id}/crm-link', [ChatController::class, 'unlinkCrm'])->middleware('permission:chat.link_crm')->whereNumber('id');
            Route::post('conversations/{id}/create-lead', [ChatController::class, 'createLead'])->middleware(['permission:chat.link_crm','permission:leads.create'])->whereNumber('id');

            // Social Inbox Settings — connect each channel's API credentials
            Route::middleware('permission:chat.manage_channels')->group(function () {
                Route::get   ('settings/channels',       [ChannelSettingsController::class, 'index']);
                Route::get   ('settings/channels/meta',  [ChannelSettingsController::class, 'meta']);
                Route::post  ('settings/channels',       [ChannelSettingsController::class, 'store']);
                Route::get   ('settings/channels/{id}',  [ChannelSettingsController::class, 'show'])->whereNumber('id');
                Route::put   ('settings/channels/{id}',  [ChannelSettingsController::class, 'update'])->whereNumber('id');
                Route::post  ('settings/channels/{id}/test', [ChannelSettingsController::class, 'test'])->whereNumber('id');
                Route::delete('settings/channels/{id}',  [ChannelSettingsController::class, 'destroy'])->whereNumber('id');
            });
        });

        // Dynamics 365 Business Central integration
        // Website visits — authenticated read side + site-key management. The ingest itself
        // is public, above.
        Route::prefix('visits')->middleware('feature:visits')->group(function () {
            Route::get ('/',            [VisitController::class, 'index'])->middleware('permission:visits.view');
            Route::get ('stats',        [VisitController::class, 'stats'])->middleware('permission:visits.view');
            Route::get ('tracker',      [VisitController::class, 'tracker'])->middleware('permission:visits.manage');
            Route::post('rotate-key',   [VisitController::class, 'rotate'])->middleware('permission:visits.manage');
            Route::get ('{id}',         [VisitController::class, 'show'])->middleware('permission:visits.view')->whereNumber('id');
        });

        Route::prefix('integrations/dynamics')->middleware('feature:integrations')->group(function () {
            Route::get   ('/',                              [DynamicsController::class, 'index'])->middleware('permission:integrations.view');
            Route::post  ('/',                              [DynamicsController::class, 'store'])->middleware('permission:integrations.manage');
            Route::put   ('{id}',                           [DynamicsController::class, 'update'])->middleware('permission:integrations.manage')->whereNumber('id');
            Route::delete('{id}',                           [DynamicsController::class, 'destroy'])->middleware('permission:integrations.manage')->whereNumber('id');
            Route::post  ('{id}/test',                      [DynamicsController::class, 'test'])->middleware('permission:integrations.manage')->whereNumber('id');
            Route::get   ('{id}/runs',                      [DynamicsController::class, 'runs'])->middleware('permission:integrations.view')->whereNumber('id');
            Route::get   ('{id}/runs/{runId}',              [DynamicsController::class, 'run'])->middleware('permission:integrations.view')->whereNumber('id')->whereNumber('runId');
            Route::post  ('{id}/mappings',                  [DynamicsController::class, 'storeMapping'])->middleware('permission:integrations.manage')->whereNumber('id');
            Route::put   ('{id}/mappings/{mappingId}',      [DynamicsController::class, 'updateMapping'])->middleware('permission:integrations.manage')->whereNumber('id')->whereNumber('mappingId');
            Route::delete('{id}/mappings/{mappingId}',      [DynamicsController::class, 'destroyMapping'])->middleware('permission:integrations.manage')->whereNumber('id')->whereNumber('mappingId');
            Route::post  ('{id}/mappings/{mappingId}/sync', [DynamicsController::class, 'sync'])->middleware('permission:integrations.sync')->whereNumber('id')->whereNumber('mappingId');
        });

        // Platform console — cross-tenant, platform admins only (no permission:/feature: gate)
        Route::prefix('platform')->middleware('platform.admin')->group(function () {
            Route::get ('companies',              [PlatformController::class, 'companies']);
            Route::get ('companies/{id}',         [PlatformController::class, 'company'])->whereNumber('id');
            Route::put ('companies/{id}/plan',    [PlatformController::class, 'updatePlan'])->whereNumber('id');
            Route::post('companies/{id}/access-grants', [PlatformController::class, 'grantAccess'])->whereNumber('id');
            Route::get ('access-grants',          [PlatformController::class, 'grants']);
            Route::post('access-grants/{id}/revoke', [PlatformController::class, 'revokeGrant'])->whereNumber('id');
        });

        Route::get ('notifications',                [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read',      [NotificationController::class, 'markRead']);
        Route::post('notifications/read-all',       [NotificationController::class, 'markAllRead']);
        Route::get ('search',                       [GlobalSearchController::class, 'search']);
        Route::post('duplicates/check',             [DuplicateController::class, 'check']);
        Route::get ('duplicates/scan',              [DuplicateController::class, 'scan']);
        Route::post('duplicates/merge-preview',     [DuplicateController::class, 'previewMerge']);
        Route::post('duplicates/merge',             [DuplicateController::class, 'merge']);
        Route::get ('audit-logs',                   [AuditLogController::class, 'index'])->middleware(['permission:audit.view', 'feature:audit']);
    });

    // Customer self-service portal — a second, parallel auth boundary (guard: `portal`,
    // identity: Contact, not User). Deliberately outside the jwt.auth/scope.company/audit group
    // above: that group assumes a staff User on the default `api` guard. Portal requests are not
    // audit-logged yet (see docs/API.md).
    Route::prefix('portal')->middleware('throttle:60,1')->group(function () {
        Route::post('login', [PortalAuthController::class, 'login'])->middleware('throttle:5,1');

        Route::middleware('portal.auth')->group(function () {
            Route::get ('me',     [PortalAuthController::class, 'me']);
            Route::post('logout', [PortalAuthController::class, 'logout']);

            Route::get('invoices',      [PortalInvoiceController::class, 'index']);
            Route::get('invoices/{id}', [PortalInvoiceController::class, 'show'])->whereNumber('id');

            Route::get ('tickets',                 [PortalTicketController::class, 'index']);
            Route::get ('tickets/{id}',             [PortalTicketController::class, 'show'])->whereNumber('id');
            Route::post('tickets',                 [PortalTicketController::class, 'store']);
            Route::post('tickets/{id}/replies',     [PortalTicketController::class, 'reply'])->whereNumber('id');

            Route::get ('quotations',               [PortalQuotationController::class, 'index']);
            Route::get ('quotations/{id}',          [PortalQuotationController::class, 'show'])->whereNumber('id');
            Route::post('quotations/{id}/sign',     [PortalQuotationController::class, 'sign'])->whereNumber('id');

            // Knowledge base — published + public articles only (see PortalKbController)
            Route::get ('kb/articles',      [PortalKbController::class, 'index']);
            Route::get ('kb/articles/{id}', [PortalKbController::class, 'show'])->whereNumber('id');
        });
    });
});
