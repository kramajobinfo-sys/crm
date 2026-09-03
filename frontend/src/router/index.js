import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { usePortalAuthStore } from '@/stores/portalAuth';

const AuthLayout = () => import('@/layouts/AuthLayout.vue');
const AppLayout  = () => import('@/layouts/AppLayout.vue');
const PortalLayout = () => import('@/layouts/PortalLayout.vue');

const routes = [
  {
    path: '/',
    component: AuthLayout,
    children: [
      { path: '',                name: 'home',         component: () => import('@/pages/auth/LoginPage.vue') },
      { path: 'login',           name: 'login',        component: () => import('@/pages/auth/LoginPage.vue') },
      { path: 'register',        name: 'register',     component: () => import('@/pages/auth/RegisterPage.vue') },
      { path: '2fa',             name: '2fa',          component: () => import('@/pages/auth/TwoFactorPage.vue'), meta: { requiresAuth: true } },
      { path: 'forgot-password', name: 'forgot',       component: () => import('@/pages/auth/ForgotPasswordPage.vue') },
    ],
  },
  {
    path: '/app',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '',           redirect: { name: 'dashboard' } },
      { path: 'dashboard',  name: 'dashboard',  component: () => import('@/pages/dashboard/DashboardPage.vue'), meta: { permission: 'dashboard.view' } },
      { path: 'my-work',    name: 'my-work',    component: () => import('@/pages/dashboard/MyWorkPage.vue'),   meta: { permission: 'dashboard.view' } },
      { path: 'profile',    name: 'profile',    component: () => import('@/pages/settings/ProfilePage.vue') },
      { path: 'leads',      name: 'leads',      component: () => import('@/pages/leads/LeadsPage.vue'),         meta: { permission: 'leads.view' } },
      { path: 'accounts',   name: 'accounts',   component: () => import('@/pages/customers/CustomersPage.vue'), meta: { permission: 'customers.view' } },
      { path: 'customers',  redirect: { name: 'accounts' } },
      { path: 'contacts',   name: 'contacts',   component: () => import('@/pages/contacts/ContactsPage.vue'),   meta: { permission: 'contacts.view' } },
      { path: 'deals',      name: 'deals',      component: () => import('@/pages/pipeline/PipelinePage.vue'),   meta: { permission: 'deals.view' } },
      { path: 'pipeline',   redirect: { name: 'deals' } },
      { path: 'activities', name: 'activities', component: () => import('@/pages/activities/ActivitiesPage.vue'),meta: { permission: 'activities.view' } },
      { path: 'projects',   name: 'projects',   component: () => import('@/pages/projects/ProjectsPage.vue'),    meta: { permission: 'projects.view' } },
      { path: 'email',      name: 'email',      component: () => import('@/pages/email/EmailPage.vue'),         meta: { permission: 'email.view' } },
      { path: 'inbox',      name: 'inbox',      component: () => import('@/pages/inbox/InboxPage.vue'),         meta: { permission: 'chat.view' } },
      { path: 'inbox-settings', name: 'inbox-settings', component: () => import('@/pages/inbox/InboxSettingsPage.vue'), meta: { permission: 'chat.manage_channels' } },
      { path: 'sales',      name: 'sales',      component: () => import('@/pages/sales/SalesPage.vue'),         meta: { permission: 'quotations.view' } },
      { path: 'price-books', name: 'price-books', component: () => import('@/pages/sales/PriceBooksPage.vue'),   meta: { permission: 'price_books.view' } },
      { path: 'credits',    name: 'credits',    component: () => import('@/pages/sales/CreditsPage.vue'),      meta: { permission: 'credits.view' } },
      { path: 'purchase',   name: 'purchase',   component: () => import('@/pages/purchase/PurchasePage.vue'),   meta: { permission: 'purchase_orders.view' } },
      { path: 'inventory',  name: 'inventory',  component: () => import('@/pages/inventory/InventoryPage.vue'), meta: { permission: 'inventory.view' } },
      { path: 'manufacturing', name: 'manufacturing', component: () => import('@/pages/manufacturing/ManufacturingPage.vue'), meta: { permission: 'manufacturing.view' } },
      { path: 'helpdesk',   name: 'helpdesk',   component: () => import('@/pages/helpdesk/HelpdeskPage.vue'),   meta: { permission: 'tickets.view' } },
      { path: 'knowledge',  name: 'knowledge',  component: () => import('@/pages/kb/KnowledgePage.vue'),        meta: { permission: 'kb.view' } },
      { path: 'documents',  name: 'documents',  component: () => import('@/pages/documents/DocumentsPage.vue'), meta: { permission: 'documents.view' } },
      { path: 'marketing',  name: 'marketing',  component: () => import('@/pages/marketing/MarketingPage.vue'), meta: { permission: 'campaigns.view' } },
      { path: 'hr',         name: 'hr',         component: () => import('@/pages/hr/HrPage.vue'),               meta: { permission: 'employees.view' } },
      { path: 'reports',    name: 'reports',    component: () => import('@/pages/reports/ReportsPage.vue'),     meta: { permission: 'reports.view' } },
      { path: 'forecasts',  name: 'forecasts',  component: () => import('@/pages/forecasts/ForecastsPage.vue'), meta: { permission: 'forecasts.view' } },
      { path: 'workflows',  name: 'workflows',  component: () => import('@/pages/workflows/WorkflowsPage.vue'), meta: { permission: 'workflows.view' } },
      { path: 'ai',         name: 'ai',         component: () => import('@/pages/ai/AiPage.vue'),               meta: { permission: 'ai.use' } },
      { path: 'settings',   name: 'settings',   component: () => import('@/pages/settings/SettingsPage.vue'),   meta: { permission: 'settings.view' } },
      { path: 'platform',   name: 'platform',   component: () => import('@/pages/platform/PlatformPage.vue'),   meta: { platformOnly: true } },
      { path: 'integrations/dynamics', name: 'dynamics', component: () => import('@/pages/integrations/DynamicsPage.vue'), meta: { permission: 'integrations.view' } },
    ],
  },
  {
    path: '/portal',
    children: [
      { path: 'login', name: 'portal-login', component: () => import('@/pages/portal/PortalLoginPage.vue') },
      {
        path: '',
        component: PortalLayout,
        meta: { requiresPortalAuth: true },
        children: [
          { path: '', redirect: { name: 'portal-invoices' } },
          { path: 'invoices', name: 'portal-invoices', component: () => import('@/pages/portal/PortalInvoicesPage.vue') },
          { path: 'invoices/:id', name: 'portal-invoice-detail', component: () => import('@/pages/portal/PortalInvoiceDetailPage.vue') },
          { path: 'tickets', name: 'portal-tickets', component: () => import('@/pages/portal/PortalTicketsPage.vue') },
          { path: 'tickets/:id', name: 'portal-ticket-detail', component: () => import('@/pages/portal/PortalTicketDetailPage.vue') },
          { path: 'quotations', name: 'portal-quotations', component: () => import('@/pages/portal/PortalQuotationsPage.vue') },
          { path: 'quotations/:id', name: 'portal-quotation-detail', component: () => import('@/pages/portal/PortalQuotationDetailPage.vue') },
          { path: 'kb', name: 'portal-kb', component: () => import('@/pages/portal/PortalKbPage.vue') },
          { path: 'kb/:id', name: 'portal-kb-article', component: () => import('@/pages/portal/PortalKbArticlePage.vue') },
        ],
      },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/app/dashboard' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach((to, _from, next) => {
  // Portal routes use their own store/guard entirely — never fall through to the staff checks
  // below, which read stores/auth.js (roles/permissions concepts a Contact doesn't have).
  if (to.path.startsWith('/portal')) {
    const portalAuth = usePortalAuthStore();
    if (to.meta.requiresPortalAuth && !portalAuth.isAuthenticated) {
      return next({ name: 'portal-login' });
    }
    if (to.name === 'portal-login' && portalAuth.isAuthenticated) {
      return next({ name: 'portal-invoices' });
    }
    return next();
  }

  const auth = useAuthStore();

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } });
  }

  if (['home', 'login', 'register'].includes(to.name) && auth.isAuthenticated) {
    return next({ name: 'dashboard' });
  }

  if (auth.isAuthenticated && auth.requires2fa && !auth.twoFactorVerified && to.name !== '2fa') {
    return next({ name: '2fa' });
  }

  if (to.meta.permission && auth.isAuthenticated && !auth.can(to.meta.permission)) {
    return next({ name: 'dashboard' });
  }

  if (to.meta.platformOnly && auth.isAuthenticated && !auth.isPlatformAdmin) {
    return next({ name: 'dashboard' });
  }

  next();
});

export default router;
