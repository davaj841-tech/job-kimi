import { createRouter, createWebHistory } from 'vue-router';
import { useAdminAuthStore } from '../stores/auth';

const routes = [
  {
    path: '/admin/login',
    name: 'admin-login',
    component: () => import('../views/LoginView.vue'),
    meta: { title: 'ورود', guest: true },
  },
  {
    path: '/admin',
    redirect: '/admin/dashboard',
  },
  {
    path: '/admin/dashboard',
    name: 'admin-dashboard',
    component: () => import('../views/DashboardView.vue'),
    meta: { title: 'داشبورد', auth: true },
  },
  {
    path: '/admin/users',
    name: 'admin-users',
    component: () => import('../views/UsersView.vue'),
    meta: { title: 'کاربران', auth: true },
  },
  {
    path: '/admin/exams',
    name: 'admin-exams',
    component: () => import('../views/ExamsView.vue'),
    meta: { title: 'آزمون‌ها', auth: true },
  },
  {
    path: '/admin/questions',
    name: 'admin-questions',
    component: () => import('../views/QuestionsView.vue'),
    meta: { title: 'سوالات', auth: true },
  },
  {
    path: '/admin/job-posts',
    name: 'admin-job-posts',
    component: () => import('../views/JobPostsView.vue'),
    meta: { title: 'آگهی‌ها', auth: true },
  },
  {
    path: '/admin/blog-posts',
    name: 'admin-blog-posts',
    component: () => import('../views/BlogPostsView.vue'),
    meta: { title: 'بلاگ', auth: true },
  },
  {
    path: '/admin/pdf-products',
    name: 'admin-pdf-products',
    component: () => import('../views/PDFProductsView.vue'),
    meta: { title: 'فایل‌ها', auth: true },
  },
  {
    path: '/admin/subscriptions',
    name: 'admin-subscriptions',
    component: () => import('../views/SubscriptionsView.vue'),
    meta: { title: 'اشتراک‌ها', auth: true },
  },
  {
    path: '/admin/transactions',
    name: 'admin-transactions',
    component: () => import('../views/TransactionsView.vue'),
    meta: { title: 'تراکنش‌ها', auth: true },
  },
  {
    path: '/admin/coupons',
    name: 'admin-coupons',
    component: () => import('../views/CouponsView.vue'),
    meta: { title: 'کد تخفیف', auth: true },
  },
  {
    path: '/admin/wallets',
    name: 'admin-wallets',
    component: () => import('../views/WalletsView.vue'),
    meta: { title: 'کیف پول‌ها', auth: true },
  },
  {
    path: '/admin/ai',
    name: 'admin-ai',
    component: () => import('../views/AIContentsView.vue'),
    meta: { title: 'هوش مصنوعی', auth: true },
  },
  {
    path: '/admin/settings',
    name: 'admin-settings',
    component: () => import('../views/SettingsView.vue'),
    meta: { title: 'تنظیمات', auth: true },
  },
  {
    path: '/admin/tickets',
    name: 'admin-tickets',
    component: () => import('../views/TicketsView.vue'),
    meta: { title: 'تیکت‌ها', auth: true },
  },
  {
    path: '/admin/banners',
    name: 'admin-banners',
    component: () => import('../views/BannersView.vue'),
    meta: { title: 'بنرها', auth: true },
  },
  {
    path: '/admin/pages',
    name: 'admin-pages',
    component: () => import('../views/PagesView.vue'),
    meta: { title: 'صفحات', auth: true },
  },
  {
    path: '/admin/backups',
    name: 'admin-backups',
    component: () => import('../views/BackupsView.vue'),
    meta: { title: 'بکاپ', auth: true },
  },
  {
    path: '/admin/audit-logs',
    name: 'admin-audit-logs',
    component: () => import('../views/AuditLogsView.vue'),
    meta: { title: 'حسابرسی', auth: true },
  },
  {
    path: '/admin/:pathMatch(.*)*',
    redirect: '/admin/dashboard',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAdminAuthStore();
  document.title = `${to.meta.title || 'پنل مدیریت'} | JobAzmoon`;

  if (to.meta.auth && !auth.isAuthenticated) {
    return { name: 'admin-login', query: { redirect: to.fullPath } };
  }

  if (to.meta.auth && auth.isAuthenticated && !auth.isStaff) {
    await auth.logout();
    return { name: 'admin-login' };
  }

  if (to.meta.guest && auth.isAuthenticated && auth.isStaff) {
    return { name: 'admin-dashboard' };
  }

  return true;
});

export default router;
