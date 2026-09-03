/**
 * Dynamic meta tags helper for Vue frontend.
 */

function upsertMeta(attr, key, content) {
  if (typeof document === 'undefined' || content == null || content === '') {
    return;
  }

  let el = document.head.querySelector(`meta[${attr}="${key}"]`);
  if (!el) {
    el = document.createElement('meta');
    el.setAttribute(attr, key);
    document.head.appendChild(el);
  }
  el.setAttribute('content', String(content));
}

function upsertLink(rel, href) {
  if (typeof document === 'undefined' || !href) return;
  let el = document.head.querySelector(`link[rel="${rel}"]`);
  if (!el) {
    el = document.createElement('link');
    el.setAttribute('rel', rel);
    document.head.appendChild(el);
  }
  el.setAttribute('href', href);
}

function upsertJsonLd(id, data) {
  if (typeof document === 'undefined' || !data) return;
  let el = document.getElementById(id);
  if (!el) {
    el = document.createElement('script');
    el.type = 'application/ld+json';
    el.id = id;
    document.head.appendChild(el);
  }
  el.textContent = JSON.stringify(data);
}

function buildRobots(index, follow) {
  const i = index !== false ? 'index' : 'noindex';
  const f = follow !== false ? 'follow' : 'nofollow';
  return `${i}, ${f}`;
}

function resolveSchemas(payload) {
  if (payload?.schemas?.length) return payload.schemas;
  if (payload?.schema) {
    return Array.isArray(payload.schema) ? payload.schema : [payload.schema];
  }
  return [];
}

export function applySeoPayload(payload = {}, { breadcrumbs } = {}) {
  const meta = payload.meta || payload;
  let schemas = resolveSchemas(payload);
  const title = meta.meta_title || meta.title;
  const description = meta.meta_description || meta.description;
  const image = meta.og_image || meta.twitter_image || meta.image;
  const url = meta.canonical_url || meta.canonical;
  const robots =
    meta.robots ||
    buildRobots(meta.robots_index, meta.robots_follow);
  const type = meta.og_type || 'website';

  // Avoid duplicate BreadcrumbList JSON-LD:
  // setPageMeta() will render breadcrumb JSON-LD from the `breadcrumbs` argument.
  // If the backend also provided BreadcrumbList inside `schemas`, we filter it out.
  if (breadcrumbs?.length) {
    schemas = schemas.filter((s) => s?.['@type'] !== 'BreadcrumbList');
  }

  setPageMeta({
    title,
    description,
    image,
    url,
    type,
    robots,
    schema: schemas.length === 1 ? schemas[0] : schemas.length ? schemas : null,
    breadcrumbs,
  });

  if (meta.og_title && meta.og_title !== title) {
    upsertMeta('property', 'og:title', meta.og_title);
  }
  if (meta.og_description && meta.og_description !== description) {
    upsertMeta('property', 'og:description', meta.og_description);
  }
  if (meta.twitter_title && meta.twitter_title !== title) {
    upsertMeta('name', 'twitter:title', meta.twitter_title);
  }
  if (meta.twitter_description && meta.twitter_description !== description) {
    upsertMeta('name', 'twitter:description', meta.twitter_description);
  }
  if (meta.twitter_card) {
    upsertMeta('name', 'twitter:card', meta.twitter_card);
  }
  if (meta.focus_keyword) {
    upsertMeta('name', 'keywords', meta.focus_keyword);
  }
}

export function setPageMeta({ title, description, image, url, type = 'website', robots = 'index, follow', schema, breadcrumbs } = {}) {
  if (typeof document === 'undefined') {
    return;
  }

  if (robots) {
    upsertMeta('name', 'robots', robots);
  }

  if (title) {
    document.title = title;
    upsertMeta('property', 'og:title', title);
    upsertMeta('name', 'twitter:title', title);
  }

  if (description) {
    upsertMeta('name', 'description', description);
    upsertMeta('property', 'og:description', description);
    upsertMeta('name', 'twitter:description', description);
  }

  if (image) {
    upsertMeta('property', 'og:image', image);
    upsertMeta('name', 'twitter:image', image);
  }

  if (url) {
    upsertMeta('property', 'og:url', url);
    upsertLink('canonical', url);
  }

  upsertMeta('property', 'og:type', type);
  upsertMeta('name', 'twitter:card', image ? 'summary_large_image' : 'summary');

  if (schema) {
    if (Array.isArray(schema)) {
      schema.forEach((item, index) => upsertJsonLd(`jsonld-${index}`, item));
    } else {
      upsertJsonLd('jsonld-main', schema);
    }
  }

  if (breadcrumbs?.length) {
    upsertJsonLd('jsonld-breadcrumb', {
      '@context': 'https://schema.org',
      '@type': 'BreadcrumbList',
      itemListElement: breadcrumbs.map((b, i) => ({
        '@type': 'ListItem',
        position: i + 1,
        name: b.name,
        item: b.url,
      })),
    });
  }
}

export function setJobPostMeta(job) {
  if (!job) return;

  const breadcrumbs = [
    { name: 'خانه', url: typeof window !== 'undefined' ? `${window.location.origin}/` : '/' },
    { name: 'آگهی‌ها', url: typeof window !== 'undefined' ? `${window.location.origin}/jobs` : '/jobs' },
    {
      name: job.title,
      url: job.seo?.meta?.canonical_url || (typeof window !== 'undefined' ? window.location.href : undefined),
    },
  ];

  if (job.seo?.meta) {
    applySeoPayload(job.seo, { breadcrumbs });
    const keywords = job.seo.keywords || (job.seo_tag ? String(job.seo_tag).replace(/_/g, ' ') : '');
    if (keywords) upsertMeta('name', 'keywords', keywords);
    return;
  }

  const plain = (job.description || '').replace(/<[^>]+>/g, '').slice(0, 160);
  const keywords = job.seo?.keywords || (job.seo_tag ? String(job.seo_tag).replace(/_/g, ' ') : '');

  setPageMeta({
    title: `${job.title} | ${job.classification_name || job.company_name || 'جاب‌آزمون'}`,
    description: plain || keywords,
    url: typeof window !== 'undefined' ? `${window.location.origin}/jobs/${job.id}` : undefined,
    type: 'article',
    schema: job.schema || null,
    breadcrumbs,
  });

  if (keywords) {
    upsertMeta('name', 'keywords', keywords);
  }
}

export function setBlogPostMeta(post) {
  if (!post) return;

  const breadcrumbs = [
    { name: 'خانه', url: typeof window !== 'undefined' ? `${window.location.origin}/` : '/' },
    { name: 'بلاگ', url: typeof window !== 'undefined' ? `${window.location.origin}/blog` : '/blog' },
    {
      name: post.title,
      url: post.seo?.meta?.canonical_url || (typeof window !== 'undefined' ? window.location.href : undefined),
    },
  ];

  if (post.seo?.meta) {
    applySeoPayload(post.seo, { breadcrumbs });
    return;
  }

  setPageMeta({
    title: post.meta_title || post.title,
    description: post.meta_description || post.excerpt || '',
    image: post.featured_image_url || post.featured_image,
    url: typeof window !== 'undefined' ? window.location.href : undefined,
    type: 'article',
    schema: post.schema || null,
    breadcrumbs,
  });
}

export function setExamMeta(exam) {
  if (!exam) return;

  const breadcrumbs = [
    { name: 'خانه', url: typeof window !== 'undefined' ? `${window.location.origin}/` : '/' },
    { name: 'آزمون‌ها', url: typeof window !== 'undefined' ? `${window.location.origin}/exams` : '/exams' },
    {
      name: exam.title,
      url: exam.seo?.meta?.canonical_url || (typeof window !== 'undefined' ? window.location.href : undefined),
    },
  ];

  if (exam.seo?.meta) {
    applySeoPayload(exam.seo, { breadcrumbs });
    const keywords = exam.seo_tag ? String(exam.seo_tag).replace(/_/g, ' ') : '';
    if (keywords) upsertMeta('name', 'keywords', keywords);
    return;
  }

  const keywords = exam.seo_tag ? String(exam.seo_tag).replace(/_/g, ' ') : '';
  setPageMeta({
    title: `${exam.title} | جاب‌آزمون`,
    description: (exam.description || '').replace(/<[^>]+>/g, '').slice(0, 160) || keywords,
    url: typeof window !== 'undefined' ? window.location.href : undefined,
    type: 'website',
    schema: exam.schema || null,
    breadcrumbs,
  });
  if (keywords) {
    upsertMeta('name', 'keywords', keywords);
  }
}

export function setArticleMeta(article) {
  if (!article) return;

  const breadcrumbs = [
    { name: 'خانه', url: typeof window !== 'undefined' ? `${window.location.origin}/` : '/' },
    { name: 'مقالات', url: typeof window !== 'undefined' ? `${window.location.origin}/articles` : '/articles' },
    {
      name: article.title,
      url: article.seo?.meta?.canonical_url || article.canonical_url || article.url,
    },
  ];

  if (article.seo?.meta) {
    applySeoPayload(article.seo, { breadcrumbs });
    return;
  }

  applySeoPayload(
    {
      meta: {
        meta_title: article.meta?.title || article.title,
        meta_description: article.meta?.description || article.excerpt,
        canonical_url: article.meta?.canonical || article.canonical_url || article.url,
        og_title: article.meta?.og_title || article.title,
        og_description: article.meta?.og_description || article.excerpt,
      },
      schema: article.schema,
    },
    { breadcrumbs },
  );
}

export function setListPageMeta({ title, description, path, breadcrumbs } = {}) {
  const origin = typeof window !== 'undefined' ? window.location.origin : '';
  const url = path ? `${origin}${path.startsWith('/') ? path : `/${path}`}` : undefined;
  setPageMeta({
    title,
    description,
    url,
    type: 'website',
    breadcrumbs:
      breadcrumbs ??
      (title
        ? [{ name: 'خانه', url: `${origin}/` }, { name: title, url }]
        : undefined),
  });
}

export function setPdfMeta(pdf) {
  if (!pdf) return;

  const breadcrumbs = [
    { name: 'خانه', url: typeof window !== 'undefined' ? `${window.location.origin}/` : '/' },
    { name: 'فروشگاه', url: typeof window !== 'undefined' ? `${window.location.origin}/pdfs` : '/pdfs' },
    {
      name: pdf.title,
      url: pdf.seo?.meta?.canonical_url || (typeof window !== 'undefined' ? window.location.href : undefined),
    },
  ];

  if (pdf.seo?.meta) {
    applySeoPayload(pdf.seo, { breadcrumbs });
    return;
  }

  setPageMeta({
    title: `${pdf.title} | جاب‌آزمون`,
    description: (pdf.description || '').replace(/<[^>]+>/g, '').slice(0, 160),
    image: pdf.cover || pdf.thumbnail_url,
    url: typeof window !== 'undefined' ? window.location.href : undefined,
    type: 'product',
    breadcrumbs,
  });
}

export default {
  applySeoPayload,
  setPageMeta,
  setJobPostMeta,
  setBlogPostMeta,
  setExamMeta,
  setArticleMeta,
  setListPageMeta,
  setPdfMeta,
};
