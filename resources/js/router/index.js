import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/',
        name: 'home',
        component: () => import('../views/HomeView.vue'),
        meta: { title: 'جاب‌آزمون' },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/auth/LoginView.vue'),
        meta: { title: 'ورود', guest: true, hideNav: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('../views/auth/ForgotPasswordView.vue'),
        meta: { title: 'فراموشی رمز', guest: true, hideNav: true },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('../views/auth/ResetPasswordView.vue'),
        meta: { title: 'بازنشانی رمز', guest: true, hideNav: true },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('../views/DashboardView.vue'),
        meta: { title: 'داشبورد', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/user/exams',
        name: 'user-exams',
        component: () => import('../views/user/Exams.vue'),
        meta: { title: 'تاریخچه آزمون', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/exams',
        name: 'exams',
        component: () => import('../views/exams/ExamListView.vue'),
        meta: { title: 'آزمون‌ها' },
    },
    {
        path: '/exams/:slug',
        name: 'exam-detail',
        component: () => import('../views/exams/ExamDetailView.vue'),
        meta: { title: 'جزئیات آزمون' },
    },
    {
        path: '/exams/:slug/start',
        name: 'exam-start',
        component: () => import('../views/exams/ExamStartView.vue'),
        meta: { title: 'شروع آزمون', auth: true, hideNav: true },
    },
    {
        path: '/exams/:id/take',
        name: 'exam-take',
        component: () => import('../views/exams/ExamTakeView.vue'),
        meta: { title: 'در حال آزمون', auth: true, hideNav: true },
    },
    {
        path: '/exams/:id/result/:attemptId',
        name: 'exam-result',
        component: () => import('../views/exams/ExamResultView.vue'),
        meta: { title: 'نتیجه آزمون', auth: true, hideNav: true },
    },
    {
        path: '/jobs',
        name: 'jobs',
        component: () => import('../views/jobs/JobListView.vue'),
        meta: { title: 'آگهی‌های شغلی' },
    },
    {
        path: '/jobs/submit',
        name: 'job-submit',
        component: () => import('../views/jobs/JobSubmitView.vue'),
        meta: { title: 'ثبت آگهی', auth: true },
    },
    {
        path: '/jobs/:id',
        name: 'job-detail',
        component: () => import('../views/jobs/JobDetailView.vue'),
        meta: { title: 'جزئیات آگهی' },
    },
    {
        path: '/blog',
        name: 'blog',
        component: () => import('../views/blog/BlogListView.vue'),
        meta: { title: 'بلاگ' },
    },
    {
        path: '/blog/:slug',
        name: 'blog-detail',
        component: () => import('../views/blog/BlogDetailView.vue'),
        meta: { title: 'مقاله' },
    },
    {
        path: '/articles',
        name: 'articles',
        component: () => import('../views/articles/ArticleListView.vue'),
        meta: { title: 'مقالات استخدامی' },
    },
    {
        path: '/articles/:slug',
        name: 'article-detail',
        component: () => import('../views/articles/ArticleDetailView.vue'),
        meta: { title: 'مقاله استخدامی' },
    },
    {
        path: '/pdfs',
        name: 'pdfs',
        component: () => import('../views/pdf/PdfListView.vue'),
        meta: { title: 'فروشگاه PDF' },
    },
    {
        path: '/pdfs/:id',
        name: 'pdf-detail',
        component: () => import('../views/pdf/PdfDetailView.vue'),
        meta: { title: 'جزئیات PDF', auth: true },
    },
    {
        path: '/my-purchases',
        name: 'my-purchases',
        component: () => import('../views/pdf/MyPurchasesView.vue'),
        meta: { title: 'خریدهای من', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/resumes',
        name: 'resumes',
        component: () => import('../views/resume/ResumeListView.vue'),
        meta: { title: 'رزومه‌ها', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/resumes/:id',
        name: 'resume-edit',
        component: () => import('../views/resume/ResumeEditorView.vue'),
        meta: { title: 'ویرایش رزومه', auth: true, hideNav: true },
    },
    {
        path: '/wallet',
        name: 'wallet',
        component: () => import('../views/wallet/WalletView.vue'),
        meta: { title: 'کیف پول', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/subscription',
        name: 'subscription',
        component: () => import('../views/subscription/PlansView.vue'),
        meta: { title: 'اشتراک', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/profile',
        name: 'profile',
        component: () => import('../views/profile/ProfileView.vue'),
        meta: { title: 'پروفایل', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/notifications',
        name: 'notifications',
        component: () => import('../views/NotificationsView.vue'),
        meta: { title: 'اعلان‌ها', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/terms',
        name: 'terms',
        component: () => import('../views/legal/TermsView.vue'),
        meta: { title: 'قوانین و مقررات' },
    },
    {
        path: '/privacy',
        name: 'privacy',
        component: () => import('../views/legal/PrivacyView.vue'),
        meta: { title: 'حریم خصوصی' },
    },
    {
        path: '/about',
        name: 'about',
        component: () => import('../views/legal/AboutView.vue'),
        meta: { title: 'درباره ما' },
    },
    {
        path: '/contact',
        name: 'contact',
        component: () => import('../views/legal/ContactView.vue'),
        meta: { title: 'تماس با ما' },
    },
    {
        path: '/support',
        name: 'support',
        component: () => import('../views/support/SupportView.vue'),
        meta: { title: 'پشتیبانی', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/support/new',
        name: 'support-new',
        component: () => import('../views/support/SupportNewView.vue'),
        meta: { title: 'تیکت جدید', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/support/:id',
        name: 'support-detail',
        component: () => import('../views/support/SupportDetailView.vue'),
        meta: { title: 'جزئیات تیکت', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/leaderboard',
        name: 'leaderboard',
        component: () => import('../views/LeaderboardView.vue'),
        meta: { title: 'رتبه‌بندی' },
    },
    {
        path: '/page/:slug',
        name: 'cms-page',
        component: () => import('../views/PageView.vue'),
        meta: { title: 'صفحه' },
    },
    {
        path: '/profile/notifications',
        name: 'profile-notifications',
        component: () => import('../views/profile/NotificationSettingsView.vue'),
        meta: { title: 'تنظیمات اعلان', auth: true, userPanel: true, hideNav: true },
    },
    {
        path: '/payment/wallet',
        name: 'payment-wallet',
        component: () => import('../views/PaymentResultView.vue'),
        meta: { title: 'نتیجه پرداخت', hideNav: true, paymentType: 'wallet' },
    },
    {
        path: '/payment/subscription',
        name: 'payment-subscription',
        component: () => import('../views/PaymentResultView.vue'),
        meta: { title: 'نتیجه پرداخت اشتراک', hideNav: true, paymentType: 'subscription' },
    },
    {
        path: '/payment/pdf',
        name: 'payment-pdf',
        component: () => import('../views/PaymentResultView.vue'),
        meta: { title: 'نتیجه خرید PDF', hideNav: true, paymentType: 'pdf' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('../views/NotFoundView.vue'),
        meta: { title: '۴۰۴', hideNav: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

router.beforeEach((to) => {
    const auth = useAuthStore();
    document.title = `${to.meta.title || 'جاب‌آزمون'} | جاب‌آزمون`;

    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'home' };
    }

    return true;
});

export default router;
