<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">
          منابع جستجو و تجمیع آگهی
        </h1>
        <p class="mt-1 text-sm text-slate-500">
          منابع رسمی آزمون استخدام، دستگاه‌های دولتی و شرکت‌های معتبر — فقط
          منابع فعال و تاییدشده به‌صورت خودکار جستجو می‌شوند.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="btn-muted" :disabled="seeding" @click="seedDefaults">
          {{ seeding ? '...' : 'بارگذاری منابع پیش‌فرض' }}
        </button>
        <button
          class="btn-dark"
          :disabled="reactivating"
          @click="reactivateDefaults"
        >
          {{ reactivating ? '...' : 'فعال‌سازی جستجوی خودکار' }}
        </button>
        <button class="btn-dark" @click="openCreate">افزودن منبع</button>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-1">
      <button
        type="button"
        class="rounded-t-lg px-4 py-2 text-sm font-bold"
        :class="
          activeTab === 'overview'
            ? 'bg-white text-orange-600 shadow-sm'
            : 'text-slate-500'
        "
        @click="activeTab = 'overview'"
      >
        منابع در حال جستجو
      </button>
      <button
        type="button"
        class="rounded-t-lg px-4 py-2 text-sm font-bold"
        :class="
          activeTab === 'defaults'
            ? 'bg-white text-orange-600 shadow-sm'
            : 'text-slate-500'
        "
        @click="activeTab = 'defaults'"
      >
        منابع پیش‌فرض
        <span
          v-if="defaultCatalog?.total"
          class="mr-1 rounded bg-orange-100 px-1.5 py-0.5 text-[10px] text-orange-700"
          >{{ defaultCatalog.total }}</span
        >
      </button>
      <button
        type="button"
        class="rounded-t-lg px-4 py-2 text-sm font-bold"
        :class="
          activeTab === 'manage'
            ? 'bg-white text-orange-600 shadow-sm'
            : 'text-slate-500'
        "
        @click="activeTab = 'manage'"
      >
        مدیریت منابع
      </button>
      <button
        type="button"
        class="rounded-t-lg px-4 py-2 text-sm font-bold"
        :class="
          activeTab === 'ai'
            ? 'bg-white text-orange-600 shadow-sm'
            : 'text-slate-500'
        "
        @click="activeTab = 'ai'"
      >
        هوش مصنوعی
        <span
          class="mr-1 rounded bg-violet-100 px-1.5 py-0.5 text-[10px] text-violet-700"
          >به‌زودی</span
        >
      </button>
    </div>

    <template v-if="activeTab === 'overview'">
      <div v-if="overview" class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">کل منابع</p>
          <p class="mt-1 text-xl font-bold">{{ overview.totals?.all ?? 0 }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">فعال + تایید</p>
          <p class="mt-1 text-xl font-bold text-blue-700">
            {{ overview.totals?.whitelisted ?? 0 }}
          </p>
        </div>
        <div
          class="rounded-xl bg-emerald-50 p-4 shadow-sm ring-1 ring-emerald-100"
        >
          <p class="text-xs text-emerald-700">در حال جستجو (خزش خودکار)</p>
          <p class="mt-1 text-xl font-bold text-emerald-800">
            {{ overview.totals?.dispatchable ?? 0 }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">غیرفعال / منتظر فعال‌سازی</p>
          <p class="mt-1 text-xl font-bold text-slate-600">
            {{
              (overview.totals?.all ?? 0) - (overview.totals?.dispatchable ?? 0)
            }}
          </p>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-bold text-slate-800">
          منابعی که هم‌اکنون جستجو می‌شوند
        </h2>
        <div
          v-if="!overview?.dispatchable_sources?.length"
          class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500"
        >
          هیچ منبعی برای جستجوی خودکار فعال نیست. از «بارگذاری منابع پیش‌فرض»
          استفاده کنید یا در تب «مدیریت منابع» منبع را فعال و تایید کنید.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="src in overview.dispatchable_sources"
            :key="src.id"
            class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <div class="font-bold text-slate-800">{{ src.name }}</div>
                <div class="text-xs text-slate-500">
                  {{ src.source_type_label }} · {{ src.reliability_label }} ·
                  {{ src.quality_status_label }}
                </div>
              </div>
              <button class="act" @click="openEdit(src)">مدیریت</button>
            </div>
            <ul class="mt-2 space-y-1 text-xs" dir="ltr">
              <li
                v-for="(url, i) in src.search_urls || []"
                :key="i"
                class="truncate rounded bg-white px-2 py-1 text-indigo-700"
              >
                {{ url }}
              </li>
            </ul>
            <p
              v-if="!(src.search_urls || []).length"
              class="mt-2 text-xs text-amber-700"
            >
              Endpoint فعالی تعریف نشده است.
            </p>
          </div>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-bold text-slate-800">فهرست کامل منابع</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500">
              <tr>
                <th class="px-3 py-2 text-right">منبع</th>
                <th class="px-3 py-2 text-right">نوع</th>
                <th class="px-3 py-2 text-right">جستجو</th>
                <th class="px-3 py-2 text-right">Endpoint</th>
                <th class="px-3 py-2 text-right">عملیات</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="src in overview?.catalog || []"
                :key="src.id"
                class="border-t"
              >
                <td class="px-3 py-2">
                  <div class="font-medium">{{ src.name }}</div>
                  <div class="text-xs text-slate-400" dir="ltr">
                    {{ src.domain }}
                  </div>
                </td>
                <td class="px-3 py-2 text-xs">{{ src.source_type_label }}</td>
                <td class="px-3 py-2">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-bold"
                    :class="
                      src.is_dispatchable
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-slate-100 text-slate-500'
                    "
                  >
                    {{ src.is_dispatchable ? 'خودکار' : 'خاموش' }}
                  </span>
                </td>
                <td class="px-3 py-2 text-xs">
                  {{ src.endpoints_count ?? 0 }}
                </td>
                <td class="px-3 py-2">
                  <button class="act" @click="openEdit(src)">ویرایش</button>
                  <button class="act text-red-600" @click="doDelete(src)">
                    حذف
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <template v-else-if="activeTab === 'defaults'">
      <div
        v-if="defaultCatalogError"
        class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
      >
        {{ defaultCatalogError }}
        <button
          type="button"
          class="mr-2 underline"
          @click="refreshDefaultCatalog"
        >
          تلاش مجدد
        </button>
      </div>

      <div
        v-if="defaultCatalogLoading"
        class="rounded-xl bg-white p-6 text-center text-sm text-slate-500"
      >
        در حال بارگذاری منابع پیش‌فرض...
      </div>

      <template v-else-if="defaultCatalog">
        <div
          class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl bg-white px-3 py-2 text-xs shadow-sm"
        >
          <span class="text-slate-500">
            کل <b class="text-slate-800">{{ defaultCatalog.total ?? 0 }}</b>
          </span>
          <span class="text-blue-600">
            بارگذاری <b>{{ defaultCatalog.loaded_count ?? 0 }}</b>
          </span>
          <span class="text-emerald-600">
            فعال <b>{{ defaultCatalog.enabled_count ?? 0 }}</b>
          </span>
          <span class="text-indigo-600">
            جستجو <b>{{ defaultCatalog.dispatchable_count ?? 0 }}</b>
          </span>
          <span class="hidden h-4 w-px bg-slate-200 sm:inline" />
          <button
            class="rounded-lg border border-slate-200 px-2 py-1 hover:bg-slate-50 disabled:opacity-50"
            :disabled="seeding"
            @click="seedDefaults"
          >
            {{ seeding ? '...' : 'بارگذاری همه' }}
          </button>
          <button
            class="rounded-lg border border-slate-200 px-2 py-1 hover:bg-slate-50 disabled:opacity-50"
            :disabled="bulkDisabling || !(defaultCatalog?.enabled_count > 0)"
            @click="disableAllDefaults"
          >
            {{ bulkDisabling ? '...' : 'غیرفعال همه' }}
          </button>
          <input
            v-model="defaultSearch"
            class="field min-w-[10rem] flex-1 py-1 text-xs"
            placeholder="جستجو..."
          />
          <button
            type="button"
            class="text-slate-500 hover:text-slate-700"
            @click="expandAllDefaultGroups"
          >
            باز کردن همه
          </button>
          <button
            type="button"
            class="text-slate-500 hover:text-slate-700"
            @click="collapseAllDefaultGroups"
          >
            جمع کردن
          </button>
        </div>

        <p
          v-if="!filteredDefaultItems.length"
          class="rounded-xl bg-white p-4 text-center text-sm text-slate-500 shadow-sm"
        >
          منبع پیش‌فرضی یافت نشد.
        </p>

        <div v-else class="space-y-2">
          <div
            v-for="group in groupedDefaultItems"
            :key="group.key"
            class="overflow-hidden rounded-xl bg-white shadow-sm"
          >
            <button
              type="button"
              class="flex w-full items-center gap-2 px-3 py-2 text-right text-sm hover:bg-slate-50"
              @click="toggleDefaultGroup(group.key)"
            >
              <span class="text-[10px] text-slate-400">
                {{ isDefaultGroupExpanded(group.key) ? '▼' : '◀' }}
              </span>
              <span class="flex-1 font-bold text-slate-700">{{
                group.label
              }}</span>
              <span
                class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600"
              >
                {{ group.items.length }}
              </span>
              <span
                v-if="group.enabledCount"
                class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] text-emerald-700"
              >
                {{ group.enabledCount }} فعال
              </span>
            </button>

            <div
              v-show="isDefaultGroupExpanded(group.key)"
              class="border-t border-slate-100"
            >
              <div
                v-for="item in group.items"
                :key="item.slug"
                class="flex items-center gap-2 border-t border-slate-50 px-3 py-1.5 text-xs first:border-t-0 hover:bg-slate-50/80"
              >
                <span
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="defaultItemDotClass(item)"
                  :title="defaultItemStatus(item).title"
                />
                <span
                  class="min-w-0 flex-1 truncate font-medium text-slate-800"
                >
                  {{ item.name }}
                </span>
                <span
                  class="hidden max-w-[7rem] shrink-0 truncate text-[10px] text-slate-400 md:inline"
                  dir="ltr"
                >
                  {{ item.domain }}
                </span>
                <span
                  class="shrink-0 text-[10px] font-bold"
                  :class="defaultItemStatus(item).class"
                >
                  {{ defaultItemStatus(item).label }}
                </span>
                <div class="flex shrink-0 gap-1">
                  <template v-if="item.is_loaded">
                    <button
                      v-if="item.is_enabled"
                      class="rounded px-1.5 py-0.5 text-[10px] text-amber-700 hover:bg-amber-50"
                      @click="toggleDefaultItem(item, false)"
                    >
                      خاموش
                    </button>
                    <button
                      v-else
                      class="rounded px-1.5 py-0.5 text-[10px] text-emerald-700 hover:bg-emerald-50"
                      @click="toggleDefaultItem(item, true)"
                    >
                      روشن
                    </button>
                    <button
                      class="rounded px-1.5 py-0.5 text-[10px] text-slate-600 hover:bg-slate-100"
                      @click="openEdit({ id: item.id })"
                    >
                      ویرایش
                    </button>
                  </template>
                  <span v-else class="text-[10px] text-slate-400"
                    >بارگذاری نشده</span
                  >
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </template>

    <template v-else-if="activeTab === 'ai'">
      <div
        class="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-6 shadow-sm"
      >
        <div class="mb-4 flex items-center gap-3">
          <span class="text-3xl">✦</span>
          <div>
            <h2 class="text-lg font-bold text-violet-900">
              کمک هوش مصنوعی برای تجمیع آگهی
            </h2>
            <p class="text-sm text-violet-700">
              {{ overview?.ai?.status_label || 'به‌زودی فعال می‌شود' }}
            </p>
          </div>
        </div>
        <p class="mb-4 text-sm leading-7 text-slate-600">
          این بخش برای استفاده آینده از هوش مصنوعی در کشف منابع جدید، پیشنهاد
          آدرس‌های جستجو و استخراج آگهی از صفحات غیرساختاریافته طراحی شده است.
          فعلاً همه منابع به‌صورت دستی یا از فهرست پیش‌فرض مدیریت می‌شوند.
        </p>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <div
            v-for="item in aiFeatures"
            :key="item.key"
            class="rounded-xl border border-violet-100 bg-white p-4"
          >
            <div class="mb-1 font-bold text-slate-800">{{ item.title }}</div>
            <p class="text-xs leading-6 text-slate-500">{{ item.desc }}</p>
            <span
              class="mt-2 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500"
              >{{ item.status }}</span
            >
          </div>
        </div>
        <p class="mt-4 text-xs text-slate-400">
          برای فعال‌سازی: AGGREGATION_AI_ENABLED=true و تنظیم provider در .env
        </p>
      </div>
    </template>

    <template v-else>
      <div
        v-if="quality"
        class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8"
      >
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">سالم (ACTIVE)</p>
          <p class="mt-1 text-xl font-bold text-emerald-700">
            {{ quality.source_health?.healthy ?? 0 }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">محدود</p>
          <p class="mt-1 text-xl font-bold text-amber-600">
            {{ quality.source_health?.limited ?? 0 }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">موقتاً قطع</p>
          <p class="mt-1 text-xl font-bold text-red-600">
            {{ quality.source_health?.temporarily_unavailable ?? 0 }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">فقط دستی</p>
          <p class="mt-1 text-xl font-bold text-slate-600">
            {{ quality.source_health?.manual_only ?? 0 }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">خزش موفق</p>
          <p class="mt-1 text-xl font-bold">
            {{ quality.crawl_quality?.successful_crawls ?? 0 }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">خزش ناموفق</p>
          <p class="mt-1 text-xl font-bold text-red-600">
            {{ quality.crawl_quality?.failed_crawls ?? 0 }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">خالیِ موفق</p>
          <p class="mt-1 text-xl font-bold text-indigo-700">
            {{ quality.crawl_quality?.empty_successful_crawls ?? 0 }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">هشدارها</p>
          <p class="mt-1 text-xl font-bold text-orange-600">
            {{ (quality.alerts || []).length }}
          </p>
        </div>
      </div>

      <div
        v-if="quality?.alerts?.length"
        class="rounded-xl border border-amber-200 bg-amber-50 p-4"
      >
        <h3 class="mb-2 text-sm font-bold text-amber-900">
          هشدارهای سلامت منابع
        </h3>
        <ul class="space-y-1 text-sm text-amber-900">
          <li v-for="(a, i) in quality.alerts.slice(0, 8)" :key="i">
            <span class="font-medium">{{ a.name }}</span> — {{ a.message }}
          </li>
        </ul>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
          <input
            v-model="store.filters.search"
            class="field md:col-span-2"
            placeholder="جستجو نام/دامنه"
            @keyup.enter="apply"
          />
          <select v-model="store.filters.source_type" class="field">
            <option value="">همه انواع</option>
            <option
              v-for="t in store.options.source_types"
              :key="t.value"
              :value="t.value"
            >
              {{ t.label }}
            </option>
          </select>
          <select v-model="store.filters.quality_status" class="field">
            <option value="">کیفیت: همه</option>
            <option
              v-for="t in store.options.quality_statuses || []"
              :key="t.value"
              :value="t.value"
            >
              {{ t.label }}
            </option>
          </select>
          <select v-model="store.filters.is_approved" class="field">
            <option value="">تایید: همه</option>
            <option value="1">تایید شده</option>
            <option value="0">تایید نشده</option>
          </select>
          <select v-model="store.filters.is_enabled" class="field">
            <option value="">فعال: همه</option>
            <option value="1">فعال</option>
            <option value="0">غیرفعال</option>
          </select>
        </div>
        <div class="mt-3 flex gap-2">
          <button class="btn-orange" @click="apply">اعمال</button>
          <button class="btn-muted" @click="clear">پاک کردن</button>
        </div>
      </div>

      <DataTable
        :columns="columns"
        :rows="store.sources"
        :loading="store.loading"
        actions
      >
        <template #cell-name="{ row }">
          <button
            class="text-right font-medium hover:text-orange-600"
            @click="openEdit(row)"
          >
            {{ row.name }}
          </button>
          <div class="text-xs text-slate-400" dir="ltr">{{ row.domain }}</div>
        </template>
        <template #cell-flags="{ row }">
          <span
            class="mr-1 rounded-full px-2 py-0.5 text-xs"
            :class="
              row.is_enabled
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-slate-100 text-slate-500'
            "
            >{{ row.is_enabled ? 'فعال' : 'خاموش' }}</span
          >
          <span
            class="mr-1 rounded-full px-2 py-0.5 text-xs"
            :class="
              row.is_approved
                ? 'bg-blue-100 text-blue-700'
                : 'bg-amber-100 text-amber-700'
            "
            >{{ row.is_approved ? 'تایید' : 'تاییدنشده' }}</span
          >
          <span
            class="rounded-full px-2 py-0.5 text-xs"
            :class="qualityBadgeClass(row.quality_status)"
            >{{ row.quality_status_label || row.quality_status || '—' }}</span
          >
        </template>
        <template #cell-last_crawled_at="{ row }">
          <div>{{ formatDate(row.last_crawled_at) }}</div>
          <div v-if="row.consecutive_failures" class="text-xs text-red-600">
            شکست متوالی: {{ row.consecutive_failures }}
          </div>
          <div v-else-if="row.last_success_at" class="text-xs text-slate-400">
            موفق: {{ formatDate(row.last_success_at) }}
          </div>
        </template>
        <template #actions="{ row }">
          <div class="flex flex-wrap justify-end gap-1">
            <button class="act" @click="openEdit(row)">مدیریت</button>
            <button
              v-if="!row.is_approved"
              class="act text-blue-700"
              @click="doApprove(row)"
            >
              تایید
            </button>
            <button v-else class="act" @click="doUnapprove(row)">
              لغو تایید
            </button>
            <button
              v-if="!row.is_enabled"
              class="act text-emerald-700"
              @click="doEnable(row)"
            >
              فعال
            </button>
            <button v-else class="act text-amber-700" @click="doDisable(row)">
              غیرفعال
            </button>
            <button class="act" @click="doResetHealth(row)">
              بازنشانی سلامت
            </button>
            <button
              class="act text-indigo-700"
              :disabled="
                !(row.is_enabled && row.is_approved) || testingId === row.id
              "
              @click="doTest(row)"
            >
              {{ testingId === row.id ? '...' : 'تست خزش' }}
            </button>
            <button class="act text-red-600" @click="doDelete(row)">حذف</button>
          </div>
        </template>
      </DataTable>
      <PaginationBar :meta="store.meta" @page="(p) => store.fetchSources(p)" />
    </template>
  </div>

  <div
    v-if="modalOpen"
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4"
  >
    <div class="mt-8 w-full max-w-3xl rounded-2xl bg-white p-5 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold">
          {{ form.id ? 'ویرایش منبع' : 'منبع جدید' }}
        </h2>
        <button class="btn-muted" @click="modalOpen = false">بستن</button>
      </div>
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <input v-model="form.name" class="field" placeholder="نام رسمی" />
        <input
          v-model="form.slug"
          class="field"
          placeholder="slug (اختیاری)"
          dir="ltr"
        />
        <input
          v-model="form.official_url"
          class="field md:col-span-2"
          placeholder="آدرس رسمی"
          dir="ltr"
        />
        <input
          v-model="form.domain"
          class="field"
          placeholder="دامنه (اختیاری)"
          dir="ltr"
        />
        <select v-model="form.source_type" class="field">
          <option
            v-for="t in store.options.source_types"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
        <select v-model="form.reliability_level" class="field">
          <option
            v-for="t in store.options.reliability_levels"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
        <select v-model="form.crawler_type" class="field">
          <option
            v-for="t in store.options.crawler_types"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
        <input
          v-model.number="form.priority"
          type="number"
          class="field"
          placeholder="اولویت"
        />
        <input
          v-model="form.crawl_frequency"
          class="field"
          placeholder="تناوب خزش"
        />
        <select v-model="form.quality_status" class="field">
          <option
            v-for="t in store.options.quality_statuses || []"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
        <select v-model="form.schedule_mode" class="field">
          <option value="global">زمان‌بندی سراسری</option>
          <option value="custom">زمان‌بندی سفارشی</option>
        </select>
        <label class="flex items-center gap-2 text-sm"
          ><input v-model="form.is_enabled" type="checkbox" /> فعال</label
        >
        <label class="flex items-center gap-2 text-sm"
          ><input v-model="form.is_approved" type="checkbox" /> تایید شده</label
        >
        <textarea
          v-model="form.notes"
          class="field md:col-span-2"
          rows="2"
          placeholder="یادداشت"
        />
        <textarea
          v-model="form.quality_notes"
          class="field md:col-span-2"
          rows="2"
          placeholder="یادداشت کیفیت / محدودیت منبع"
        />
      </div>

      <div
        v-if="form.schedule_mode === 'custom'"
        class="mt-4 rounded-xl border border-dashed p-3"
      >
        <div class="mb-2 flex items-center justify-between">
          <h3 class="text-sm font-bold">زمان‌های سفارشی منبع</h3>
          <button type="button" class="btn-muted" @click="addCustomTime">
            افزودن
          </button>
        </div>
        <div
          v-for="(t, idx) in form.custom_schedule_times"
          :key="idx"
          class="mb-2 grid grid-cols-1 gap-2 md:grid-cols-4"
        >
          <input v-model="t.time" class="field" dir="ltr" placeholder="HH:MM" />
          <input v-model="t.label" class="field" placeholder="برچسب" />
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="t.enabled" type="checkbox" /> فعال</label
          >
          <button
            type="button"
            class="act text-red-600"
            @click="form.custom_schedule_times.splice(idx, 1)"
          >
            حذف
          </button>
        </div>
        <p class="text-xs text-slate-400">
          اگر خالی باشد، این منبع در هیچ اسلاتی اجرا نمی‌شود.
        </p>
      </div>
      <p v-if="formError" class="mt-2 text-sm text-red-600">
        {{ formError }}
      </p>
      <div class="mt-4 flex justify-between gap-2">
        <button
          v-if="form.id"
          type="button"
          class="rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-600"
          @click="doDelete({ id: form.id, name: form.name })"
        >
          حذف منبع
        </button>
        <div class="ml-auto flex gap-2">
          <button class="btn-muted" @click="modalOpen = false">انصراف</button>
          <button class="btn-orange" :disabled="saving" @click="save">
            ذخیره
          </button>
        </div>
      </div>

      <div v-if="form.id" class="mt-8 border-t pt-4">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="font-bold">Endpointها</h3>
          <button class="btn-muted" @click="addEndpoint">
            افزودن Endpoint
          </button>
        </div>
        <div
          v-for="ep in endpoints"
          :key="ep.id || ep._tmp"
          class="mb-3 grid grid-cols-1 gap-2 rounded-xl border p-3 md:grid-cols-2"
        >
          <input
            v-model="ep.url"
            class="field md:col-span-2"
            placeholder="URL"
            dir="ltr"
          />
          <select v-model="ep.endpoint_type" class="field">
            <option
              v-for="t in store.options.endpoint_types"
              :key="t.value"
              :value="t.value"
            >
              {{ t.value }}
            </option>
          </select>
          <select v-model="ep.parser_type" class="field">
            <option value="">بدون پارسر اختصاصی</option>
            <option
              v-for="t in store.options.parser_types"
              :key="t.value"
              :value="t.value"
            >
              {{ t.value }}
            </option>
          </select>
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="ep.is_enabled" type="checkbox" /> فعال</label
          >
          <div class="flex justify-end gap-2">
            <button class="act" @click="saveEndpoint(ep)">ذخیره</button>
            <button
              v-if="ep.id"
              class="act text-red-600"
              @click="removeEndpoint(ep)"
            >
              حذف
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div
    v-if="testResult"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
      <h3 class="mb-3 font-bold">نتیجه تست خزش</h3>
      <div
        v-if="testResult.summary"
        class="mb-3 grid grid-cols-2 gap-2 text-sm"
      >
        <div>
          HTTP:
          <span dir="ltr">{{ testResult.summary.http_status ?? '—' }}</span>
        </div>
        <div>
          زمان:
          <span dir="ltr">{{ testResult.summary.execution_ms ?? '—' }} ms</span>
        </div>
        <div>کشف‌شده: {{ testResult.summary.found }}</div>
        <div>
          پذیرفته:
          {{
            (testResult.summary.created || 0) +
            (testResult.summary.updated || 0)
          }}
        </div>
        <div>رد شده: {{ testResult.summary.rejected }}</div>
        <div>خطا: {{ testResult.summary.errors }}</div>
        <div class="col-span-2">
          وضعیت کیفیت:
          {{
            testResult.quality_status_label || testResult.quality_status || '—'
          }}
        </div>
        <div class="col-span-2">
          نتیجه سلامت:
          <span dir="ltr">{{
            testResult.health?.outcome || testResult.summary.outcome || '—'
          }}</span>
        </div>
      </div>
      <pre class="overflow-auto rounded-xl bg-slate-50 p-3 text-xs" dir="ltr">{{
        JSON.stringify(testResult, null, 2)
      }}</pre>
      <p class="mt-2 text-xs text-slate-500">
        آگهی‌های جدید در وضعیت pending می‌مانند و منتشر نمی‌شوند.
      </p>
      <button class="btn-orange mt-4" @click="testResult = null">باشه</button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import { useAggregationStore } from '../stores/aggregation'
import { useJobSourcesStore } from '../stores/jobSources'
import { formatDate } from '../../utils/format'

const store = useJobSourcesStore()
const aggregation = useAggregationStore()
const quality = ref(null)
const overview = ref(null)
const defaultCatalog = ref(null)
const defaultSearch = ref('')
const defaultCatalogLoading = ref(false)
const defaultCatalogError = ref('')
const defaultGroupsExpanded = ref({})
const bulkDisabling = ref(false)
const activeTab = ref('defaults')
const modalOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const testingId = ref(null)
const testResult = ref(null)
const seeding = ref(false)
const reactivating = ref(false)
const endpoints = ref([])

const columns = [
  { key: 'name', label: 'منبع' },
  { key: 'source_type_label', label: 'نوع' },
  { key: 'reliability_label', label: 'اعتماد' },
  { key: 'crawler_type_label', label: 'خزنده' },
  { key: 'schedule_mode', label: 'زمان‌بندی' },
  { key: 'flags', label: 'وضعیت' },
  { key: 'last_crawled_at', label: 'آخرین خزش' },
]

const aiFeatures = computed(() => [
  {
    key: 'source_discovery',
    title: 'کشف منابع جدید',
    desc: 'پیشنهاد سایت‌های رسمی استخدام بر اساس دستگاه یا حوزه فعالیت.',
    status: 'به‌زودی',
  },
  {
    key: 'endpoint_suggestion',
    title: 'پیشنهاد آدرس جستجو',
    desc: 'تشخیص صفحات اعلام فراخوان، RSS یا API مناسب برای هر منبع.',
    status: 'به‌زودی',
  },
  {
    key: 'job_extraction',
    title: 'استخراج آگهی',
    desc: 'خواندن آگهی از صفحات HTML غیراستاندارد با بازبینی ادمین.',
    status: 'به‌زودی',
  },
  {
    key: 'duplicate_review',
    title: 'بررسی تکراری',
    desc: 'مقایسه هوشمند آگهی‌های مشابه قبل از انتشار.',
    status: 'به‌زودی',
  },
])

async function refreshOverview() {
  overview.value = await store.fetchCrawlOverview()
}

async function refreshDefaultCatalog() {
  defaultCatalogLoading.value = true
  defaultCatalogError.value = ''
  try {
    const payload = await store.fetchDefaultCatalog()
    defaultCatalog.value = payload || store.defaultCatalog
  } catch (e) {
    defaultCatalog.value =
      store.defaultCatalog || store.options?.default_catalog || null
    if (!defaultCatalog.value?.items?.length) {
      defaultCatalogError.value =
        e?.response?.data?.message ||
        e?.message ||
        'بارگذاری منابع پیش‌فرض ناموفق بود.'
    }
  } finally {
    defaultCatalogLoading.value = false
  }
}

watch(activeTab, (tab) => {
  if (tab === 'defaults' && !defaultCatalog.value?.items?.length) {
    refreshDefaultCatalog()
  }
})

const filteredDefaultItems = computed(() => {
  const items = defaultCatalog.value?.items || []
  const q = defaultSearch.value.trim().toLowerCase()
  if (!q) return items
  return items.filter(
    (item) =>
      String(item.name || '')
        .toLowerCase()
        .includes(q) ||
      String(item.domain || '')
        .toLowerCase()
        .includes(q) ||
      String(item.slug || '')
        .toLowerCase()
        .includes(q) ||
      String(item.source_type_label || '')
        .toLowerCase()
        .includes(q)
  )
})

const groupedDefaultItems = computed(() => {
  const groups = new Map()
  for (const item of filteredDefaultItems.value) {
    const key = item.source_type || item.source_type_label || 'other'
    const label = item.source_type_label || 'سایر'
    if (!groups.has(key)) {
      groups.set(key, { key, label, items: [], enabledCount: 0 })
    }
    const group = groups.get(key)
    group.items.push(item)
    if (item.is_enabled) group.enabledCount += 1
  }
  return Array.from(groups.values()).sort((a, b) =>
    a.label.localeCompare(b.label, 'fa')
  )
})

function isDefaultGroupExpanded(key) {
  return !!defaultGroupsExpanded.value[key]
}

function toggleDefaultGroup(key) {
  defaultGroupsExpanded.value[key] = !defaultGroupsExpanded.value[key]
}

function expandAllDefaultGroups() {
  const next = { ...defaultGroupsExpanded.value }
  for (const group of groupedDefaultItems.value) {
    next[group.key] = true
  }
  defaultGroupsExpanded.value = next
}

function collapseAllDefaultGroups() {
  defaultGroupsExpanded.value = {}
}

function defaultItemStatus(item) {
  if (!item.is_loaded) {
    return { label: '—', class: 'text-slate-400', title: 'بارگذاری نشده' }
  }
  if (item.is_dispatchable) {
    return {
      label: 'جستجو',
      class: 'text-indigo-600',
      title: 'جستجوی خودکار فعال',
    }
  }
  if (item.is_enabled) {
    return { label: 'فعال', class: 'text-emerald-600', title: 'فعال' }
  }
  return { label: 'خاموش', class: 'text-slate-500', title: 'غیرفعال' }
}

function defaultItemDotClass(item) {
  if (!item.is_loaded) return 'bg-slate-300'
  if (item.is_dispatchable) return 'bg-indigo-500'
  if (item.is_enabled) return 'bg-emerald-500'
  return 'bg-slate-400'
}

watch(defaultSearch, (q) => {
  if (q.trim()) expandAllDefaultGroups()
})

async function toggleDefaultItem(item, enable) {
  if (!item.id) return
  if (enable) {
    await store.enable(item.id)
  } else {
    await store.disable(item.id)
  }
  await store.fetchSources(store.filters.page || 1)
  await refreshDefaultCatalog()
  await refreshOverview()
}

async function disableAllDefaults() {
  if (!confirm('همه منابع پیش‌فرض بارگذاری‌شده غیرفعال شوند؟')) return
  bulkDisabling.value = true
  try {
    const res = await store.bulkDisableDefaults()
    await store.fetchSources(store.filters.page || 1)
    await refreshDefaultCatalog()
    await refreshOverview()
    alert(
      res.disabled > 0 ? `${res.disabled} منبع غیرفعال شد.` : 'منبع فعالی نبود.'
    )
  } catch (e) {
    alert(e.response?.data?.message || 'عملیات ناموفق بود.')
  } finally {
    bulkDisabling.value = false
  }
}

const form = reactive({
  id: null,
  name: '',
  slug: '',
  official_url: '',
  domain: '',
  source_type: 'government',
  reliability_level: 'official',
  crawler_type: 'html',
  priority: 50,
  crawl_frequency: 'daily',
  schedule_mode: 'global',
  custom_schedule_times: [],
  quality_status: 'active',
  quality_notes: '',
  is_enabled: false,
  is_approved: false,
  notes: '',
})

function resetForm() {
  Object.assign(form, {
    id: null,
    name: '',
    slug: '',
    official_url: '',
    domain: '',
    source_type: 'government',
    reliability_level: 'official',
    crawler_type: 'html',
    priority: 50,
    crawl_frequency: 'daily',
    schedule_mode: 'global',
    custom_schedule_times: [],
    quality_status: 'active',
    quality_notes: '',
    is_enabled: false,
    is_approved: false,
    notes: '',
  })
  endpoints.value = []
  formError.value = ''
}

function openCreate() {
  activeTab.value = 'manage'
  resetForm()
  modalOpen.value = true
}

async function openEdit(row) {
  activeTab.value = 'manage'
  resetForm()
  const full = await store.fetchSource(row.id)
  Object.assign(form, {
    id: full.id,
    name: full.name,
    slug: full.slug,
    official_url: full.official_url,
    domain: full.domain,
    source_type: full.source_type,
    reliability_level: full.reliability_level,
    crawler_type: full.crawler_type,
    priority: full.priority,
    crawl_frequency: full.crawl_frequency,
    schedule_mode: full.schedule_mode || 'global',
    custom_schedule_times: (full.custom_schedule_times || []).map((t) => ({
      ...t,
    })),
    quality_status: full.quality_status || 'active',
    quality_notes: full.quality_notes || '',
    is_enabled: full.is_enabled,
    is_approved: full.is_approved,
    notes: full.notes || '',
  })
  endpoints.value = (full.endpoints || []).map((e) => ({ ...e }))
  modalOpen.value = true
}

function addCustomTime() {
  form.custom_schedule_times.push({ time: '', enabled: true, label: '' })
}

async function save() {
  saving.value = true
  formError.value = ''
  try {
    const payload = { ...form }
    delete payload.id
    const saved = await store.saveSource(payload, form.id)
    form.id = saved.id
    await store.fetchSources(store.filters.page || 1)
    if (form.id) {
      const full = await store.fetchSource(form.id)
      endpoints.value = (full.endpoints || []).map((e) => ({ ...e }))
    }
    await refreshOverview()
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در ذخیره منبع'
  } finally {
    saving.value = false
  }
}

function addEndpoint() {
  endpoints.value.push({
    _tmp: Date.now(),
    url: '',
    endpoint_type: 'html',
    http_method: 'GET',
    parser_type: '',
    is_enabled: true,
    sort_order: endpoints.value.length,
  })
}

async function saveEndpoint(ep) {
  formError.value = ''
  try {
    await store.saveEndpoint(
      form.id,
      {
        url: ep.url,
        endpoint_type: ep.endpoint_type,
        http_method: 'GET',
        parser_type: ep.parser_type || null,
        is_enabled: ep.is_enabled,
        sort_order: ep.sort_order || 0,
      },
      ep.id || null
    )
    const full = await store.fetchSource(form.id)
    endpoints.value = (full.endpoints || []).map((e) => ({ ...e }))
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در ذخیره endpoint'
  }
}

async function removeEndpoint(ep) {
  await store.destroyEndpoint(form.id, ep.id)
  endpoints.value = endpoints.value.filter((x) => x.id !== ep.id)
}

async function doApprove(row) {
  await store.approve(row.id)
  await store.fetchSources(store.filters.page || 1)
  await refreshOverview()
  await refreshDefaultCatalog()
}
async function doUnapprove(row) {
  await store.unapprove(row.id)
  await store.fetchSources(store.filters.page || 1)
  await refreshOverview()
  await refreshDefaultCatalog()
}
async function doEnable(row) {
  await store.enable(row.id)
  await store.fetchSources(store.filters.page || 1)
  await refreshOverview()
  await refreshDefaultCatalog()
}
async function doDisable(row) {
  await store.disable(row.id)
  await store.fetchSources(store.filters.page || 1)
  await refreshOverview()
  await refreshDefaultCatalog()
}

async function doDelete(row) {
  if (
    !confirm(
      `منبع «${row.name}» حذف شود؟\n(اگر آگهی تجمیع‌شده داشته باشد حذف نمی‌شود.)`
    )
  ) {
    return
  }
  try {
    await store.destroySource(row.id)
    modalOpen.value = false
    await store.fetchSources(store.filters.page || 1)
    await refreshOverview()
    await refreshDefaultCatalog()
    quality.value = await aggregation.fetchStats()
  } catch (e) {
    alert(e.response?.data?.message || 'حذف منبع ناموفق بود.')
  }
}

async function doResetHealth(row) {
  try {
    await store.resetHealth(row.id)
    await store.fetchSources(store.filters.page || 1)
    quality.value = await aggregation.fetchStats()
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در بازنشانی سلامت'
  }
}

async function doTest(row) {
  testingId.value = row.id
  try {
    const res = await store.testCrawl(row.id)
    testResult.value = res.data
    await store.fetchSources(store.filters.page || 1)
    quality.value = await aggregation.fetchStats()
  } catch (e) {
    testResult.value = { error: e.response?.data?.message || e.message }
  } finally {
    testingId.value = null
  }
}

async function seedDefaults() {
  if (
    !confirm(
      'منابع رسمی پیش‌فرض (سنجش، بانک‌ها، ایران‌استخدام و …) بارگذاری شوند؟'
    )
  ) {
    return
  }
  seeding.value = true
  formError.value = ''
  try {
    const res = await store.seedDefaults()
    await store.fetchSources(1)
    quality.value = await aggregation.fetchStats()
    await refreshOverview()
    await refreshDefaultCatalog()
    alert(
      `منابع بارگذاری شد: ${res.before} → ${res.after} (قابل خزش: ${res.dispatchable}${res.reactivated != null ? `، فعال‌شده: ${res.reactivated}` : ''})`
    )
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در بارگذاری منابع'
  } finally {
    seeding.value = false
  }
}

async function reactivateDefaults() {
  if (
    !confirm(
      'همه منابع رسمی فعال، تایید و برای جستجوی خودکار آماده شوند؟ (خطاهای سلامت هم پاک می‌شود)'
    )
  ) {
    return
  }
  reactivating.value = true
  formError.value = ''
  try {
    const res = await store.reactivateDefaults()
    await store.fetchSources(1)
    quality.value = await aggregation.fetchStats()
    await refreshOverview()
    await refreshDefaultCatalog()
    alert(
      `فعال شد: ${res.reactivated ?? 0} منبع · قابل جستجو: ${res.dispatchable ?? 0}`
    )
  } catch (e) {
    formError.value =
      e.response?.data?.message || 'خطا در فعال‌سازی جستجوی خودکار'
  } finally {
    reactivating.value = false
  }
}

function apply() {
  store.fetchSources(1)
}
function clear() {
  store.filters.search = ''
  store.filters.source_type = ''
  store.filters.quality_status = ''
  store.filters.is_enabled = ''
  store.filters.is_approved = ''
  apply()
}

function qualityBadgeClass(status) {
  switch (status) {
    case 'active':
      return 'bg-emerald-100 text-emerald-800'
    case 'limited':
      return 'bg-amber-100 text-amber-800'
    case 'temporarily_unavailable':
      return 'bg-red-100 text-red-700'
    case 'manual_only':
      return 'bg-slate-200 text-slate-700'
    default:
      return 'bg-slate-100 text-slate-600'
  }
}

onMounted(async () => {
  await store.fetchOptions()
  defaultCatalog.value =
    store.defaultCatalog || store.options?.default_catalog || null
  await store.fetchSources(1)
  quality.value = await aggregation.fetchStats()
  await refreshOverview()
  if (!defaultCatalog.value?.items?.length) {
    await refreshDefaultCatalog()
  }
})
</script>
