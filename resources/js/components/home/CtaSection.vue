<template>
  <section class="border-t border-surface-line bg-white py-10 sm:py-14">
    <div class="mx-auto max-w-2xl px-4 text-center">
      <h2
        class="animate-on-scroll text-xl font-black text-desk-dark sm:text-2xl"
      >
        مسیر استخدامی‌ات را همین‌جا شروع کن
      </h2>
      <p
        class="animate-on-scroll mx-auto mt-3 max-w-md text-sm leading-7 text-desk-muted"
      >
        ثبت‌نام رایگان، اولین آزمون، و دسترسی به آگهی‌ها و منابع آموزشی — ساده و
        شفاف.
      </p>
      <div
        class="animate-on-scroll mt-6 flex flex-col justify-center gap-2.5 sm:flex-row"
      >
        <RouterLink
          to="/login"
          class="rounded-xl bg-brand px-8 py-3.5 text-sm font-bold text-white transition hover:bg-brand-dark"
        >
          شروع رایگان
        </RouterLink>
        <RouterLink
          to="/subscription"
          class="rounded-xl border border-desk-dark/15 bg-white px-8 py-3.5 text-sm font-bold text-desk-dark transition hover:bg-surface-page"
        >
          مشاهده اشتراک
        </RouterLink>
      </div>
    </div>
  </section>
</template>
