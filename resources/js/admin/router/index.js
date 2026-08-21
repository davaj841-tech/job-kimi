import { createRouter, createWebHistory } from 'vue-router';
import { useAdminAuthStore } from '../stores/auth';
import AdminLayout from '../components/layout/AdminLayout.vue';

const children = [
  {
    path: 'dashboard',
    name: 'admin-dashboard',
    component: () => import('../views/DashboardView.vue'),
    meta: { title: 'داشبورد', auth: true },
  },
  {
    path: 'users',
    name: 'admin-users',
    component: () => import('../views/UsersView.vue'),
    meta: { title: 'کاربران', auth: true, permission: 'users' },
  },
  {
    path: 'access-levels',
    name: 'admin-access-levels',
    component: () => import('../views/AccessLevelsView.vue'),
    meta: { title: 'سطح دسترسی', auth: true, permission: 'users' },
  },
  {
    path: 'exams',
    name: 'admin-exams',
    component: () => import('../views/ExamsView.vue'),
    meta: { title: 'آزمون‌ها', auth: true, permission: 'exams' },
  },
  {
    path: 'exams/:id/take',
    name: 'admin-exam-take',
    component: () => import('../views/ExamTakeView.vue'),
    meta: { title: 'آزمون‌گیری', auth: true, permission: 'exams' },
  },
  {
    path: 'exams/:id/result/:attemptId',
    name: 'admin-exam-result',
    component: () => import('../views/ExamResultView.vue'),
    meta: { title: 'نتیجه آزمون', auth: true, permission: 'exams' },
  },
  {
    path: 'questions',
    name: 'admin-questions',
    component: () => import('../views/QuestionsView.vue'),
    meta: { title: 'سوالات', auth: true, permission: 'questions' },
  },
  {
    path: 'job-posts',
    name: 'admin-job-posts',
    component: () => import('../views/JobPostsView.vue'),
    meta: { title: 'آگهی‌ها', auth: true, permission: 'job_posts' },
  },
  {
    path: 'job-sources',
    name: 'admin-job-sources',
    component: () => import('../views/JobSourcesView.vue'),
    meta: { title: 'منابع تجمیع', auth: true, permission: 'aggregation' },
  },
  {
    path: 'aggregation-settings',
    name: 'admin-aggregation-settings',
    component: () => import('../views/AggregationSettingsView.vue'),
    meta: { title: 'زمان‌بندی تجمیع', auth: true, permission: 'aggregation' },
  },
  {
    path: 'crawl-monitoring',
    name: 'admin-crawl-monitoring',
    component: () => import('../views/CrawlMonitoringView.vue'),
    meta: { title: 'پایش خزش', auth: true, permission: 'aggregation' },
  },
  {
    path: 'aggregated-jobs',
    name: 'admin-aggregated-jobs',
    component: () => import('../views/AggregatedJobsView.vue'),
    meta: { title: 'بررسی تجمیع', auth: true, permission: 'aggregation' },
  },
  {
    path: 'blog-posts',
    name: 'admin-blog-posts',
    component: () => import('../views/BlogPostsView.vue'),
    meta: { title: 'بلاگ', auth: true, permission: 'blog' },
  },
  {
    path: 'generated-contents',
    name: 'admin-generated-contents',
    component: () => import('../views/GeneratedContentsView.vue'),
    meta: { title: 'تولید محتوا', auth: true, permission: 'generated_contents' },
  },
  {
    path: 'pdf-products',
    name: 'admin-pdf-products',
    component: () => import('../views/PDFProductsView.vue'),
    meta: { title: 'فایل‌ها', auth: true, permission: 'pdf' },
  },
  {
    path: 'subscriptions',
    name: 'admin-subscriptions',
    component: () => import('../views/SubscriptionsView.vue'),
    meta: { title: 'اشتراک‌ها', auth: true, permission: 'subscriptions' },
  },
  {
    path: 'transactions',
    name: 'admin-transactions',
    component: () => import('../views/TransactionsView.vue'),
    meta: { title: 'تراکنش‌ها', auth: true, permission: 'transactions' },
  },
  {
    path: 'coupons',
    name: 'admin-coupons',
    component: () => import('../views/CouponsView.vue'),
    meta: { title: 'کد تخفیف', auth: true, permission: 'coupons' },
  },
  {
    path: 'wallets',
    name: 'admin-wallets',
    component: () => import('../views/WalletsView.vue'),
    meta: { title: 'کیف پول‌ها', auth: true, permission: 'wallets' },
  },
  {
    path: 'ai',
    name: 'admin-ai',
    component: () => import('../views/AIContentsView.vue'),
    meta: { title: 'هوش مصنوعی', auth: true, permission: 'ai' },
  },
  {
    path: 'settings',
    name: 'admin-settings',
    component: () => import('../views/SettingsView.vue'),
    meta: { title: 'تنظیمات', auth: true, adminOnly: true },
  },
  {
    path: 'performance',
    name: 'admin-performance',
    component: () => import('../views/PerformanceView.vue'),
    meta: { title: 'سرعت سایت', auth: true, adminOnly: true },
  },
  {
    path: 'tickets',
    name: 'admin-tickets',
    component: () => import('../views/TicketsView.vue'),
    meta: { title: 'تیکت‌ها', auth: true, permission: 'tickets' },
  },
  {
    path: 'contact-messages',
    name: 'admin-contact-messages',
    component: () => import('../views/ContactMessagesView.vue'),
    meta: { title: 'پیام‌های تماس', auth: true, permission: 'tickets' },
  },
  {
    path: 'banners',
    name: 'admin-banners',
    component: () => import('../views/BannersView.vue'),
    meta: { title: 'بنرها', auth: true, permission: 'banners' },
  },
  {
    path: 'pages',
    name: 'admin-pages',
    component: () => import('../views/PagesView.vue'),
    meta: { title: 'صفحات', auth: true, permission: 'pages' },
  },
  {
    path: 'backups',
    name: 'admin-backups',
    component: () => import('../views/BackupsView.vue'),
    meta: { title: 'بکاپ', auth: true, adminOnly: true },
  },
  {
    path: 'system-updates',
    name: 'admin-system-updates',
    component: () => import('../views/SystemUpdatesView.vue'),
    meta: { title: 'به‌روزرسانی سیستم', auth: true, adminOnly: true },
  },
  {
    path: 'audit-logs',
    name: 'admin-audit-logs',
    component: () => import('../views/AuditLogsView.vue'),
    meta: { title: 'حسابرسی', auth: true, adminOnly: true },
  },
  {
    path: 'site-errors',
    name: 'admin-site-errors',
    component: () => import('../views/SiteErrorsView.vue'),
    meta: { title: 'خطاهای سایت', auth: true, adminOnly: true },
  },
  {
    path: ':pathMatch(.*)*',
    redirect: { name: 'admin-dashboard' },
  },
];

const routes = [
  {
    path: '/admin/login',
    name: 'admin-login',
    component: () => import('../views/LoginView.vue'),
    meta: { title: 'ورود', guest: true },
  },
  {
    path: '/admin',
    component: AdminLayout,
    redirect: '/admin/dashboard',
    meta: { auth: true },
    children,
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAdminAuthStore();
  document.title = `${to.meta.title || 'پنل مدیریت'} | JobAzmoon`;

  const needsAuth = to.matched.some((r) => r.meta.auth);
  const isGuest = to.matched.some((r) => r.meta.guest);
  const adminOnly = to.matched.some((r) => r.meta.adminOnly);
  const permission = [...to.matched].reverse().find((r) => r.meta.permission)?.meta.permission;

  if (needsAuth && !auth.isAuthenticated) {
    return { name: 'admin-login', query: { redirect: to.fullPath } };
  }

  if (needsAuth && auth.isAuthenticated && !auth.isStaff) {
    await auth.logout();
    return { name: 'admin-login' };
  }

  if (isGuest && auth.isAuthenticated && auth.isStaff) {
    return { name: 'admin-dashboard' };
  }

  if (adminOnly && !auth.isSuperAdmin) {
    return { name: 'admin-dashboard' };
  }

  if (permission && !auth.can(permission)) {
    return { name: 'admin-dashboard' };
  }

  return true;
});

export default router;
