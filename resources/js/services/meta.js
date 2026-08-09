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

export function setPageMeta({ title, description, image, url, type = 'website', schema, breadcrumbs } = {}) {
  if (typeof document === 'undefined') {
    return;
  }

  const canonical = url || (typeof window !== 'undefined' ? window.location.href : undefined);

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

  if (canonical) {
    upsertMeta('property', 'og:url', canonical);
    upsertLink('canonical', canonical);
  }

  upsertMeta('property', 'og:type', type);
  upsertMeta('name', 'twitter:card', image ? 'summary_large_image' : 'summary');

  if (schema) {
    upsertJsonLd('jsonld-main', schema);
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

  const url = typeof window !== 'undefined' ? window.location.href : undefined;
  const plain = (job.description || '').replace(/<[^>]+>/g, '').slice(0, 160);
  const keywords = job.seo?.keywords || (job.seo_tag ? String(job.seo_tag).replace(/_/g, ' ') : '');

  setPageMeta({
    title: `${job.title} | ${job.classification_name || job.company_name || 'جاب‌آزمون'}`,
    description: plain || keywords,
    url,
    type: 'article',
    schema: job.schema || null,
    breadcrumbs: [
      { name: 'خانه', url: typeof window !== 'undefined' ? `${window.location.origin}/` : '/' },
      { name: 'آگهی‌ها', url: typeof window !== 'undefined' ? `${window.location.origin}/jobs` : '/jobs' },
      { name: job.title, url },
    ],
  });

  if (keywords) {
    upsertMeta('name', 'keywords', keywords);
  }
}

export function setBlogPostMeta(post) {
  if (!post) return;

  const url = typeof window !== 'undefined' ? window.location.href : undefined;
  setPageMeta({
    title: post.meta_title || post.title,
    description: post.meta_description || post.excerpt || '',
    image: post.featured_image_url || post.featured_image,
    url,
    type: 'article',
    schema: post.schema || null,
    breadcrumbs: [
      { name: 'خانه', url: typeof window !== 'undefined' ? `${window.location.origin}/` : '/' },
      { name: 'بلاگ', url: typeof window !== 'undefined' ? `${window.location.origin}/blog` : '/blog' },
      { name: post.title, url },
    ],
  });
}

export function setExamMeta(exam) {
  if (!exam) return;
  const url = typeof window !== 'undefined' ? window.location.href : undefined;
  const keywords = exam.seo_tag ? String(exam.seo_tag).replace(/_/g, ' ') : '';
  setPageMeta({
    title: `${exam.title} | جاب‌آزمون`,
    description: (exam.description || '').replace(/<[^>]+>/g, '').slice(0, 160) || keywords,
    url,
    type: 'website',
    breadcrumbs: [
      { name: 'خانه', url: typeof window !== 'undefined' ? `${window.location.origin}/` : '/' },
      { name: 'آزمون‌ها', url: typeof window !== 'undefined' ? `${window.location.origin}/exams` : '/exams' },
      { name: exam.title, url },
    ],
  });
  if (keywords) {
    upsertMeta('name', 'keywords', keywords);
  }
}

export default {
  setPageMeta,
  setJobPostMeta,
  setBlogPostMeta,
  setExamMeta,
};
