const slugify = (str) =>
  (str || '')
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '');

module.exports = {
  async beforeCreate(event) {
    const { data } = event.params;
    if (!data) return;

    // 1. Auto-generate slug from title if empty
    if (!data.slug && data.title) {
      data.slug = slugify(data.title);
    }

    // 2. Auto-fill publishDate if empty
    if (!data.publishDate) {
      data.publishDate = new Date().toISOString().split('T')[0];
    }

    // 3. Auto-populate Author if empty
    if (!data.author) {
      data.author = { name: "Pislinfra Team" };
    }

    // 4. Auto-populate SEO from 1-Time Title, Excerpt & Featured Image
    if (!data.seo) {
      data.seo = {};
    }

    if (!data.seo.seoTitle && data.title) {
      data.seo.seoTitle = `${data.title} | Pislinfra`;
    }

    const desc = data.excerpt || data.title || "Pislinfra Industrial & Infrastructure Excellence";
    if (!data.seo.seoDescription) {
      data.seo.seoDescription = desc;
    }

    if (!data.seo.ogTitle && data.title) {
      data.seo.ogTitle = data.title;
    }

    if (!data.seo.ogDescription) {
      data.seo.ogDescription = desc;
    }

    if (!data.seo.twitterTitle && data.title) {
      data.seo.twitterTitle = data.title;
    }

    if (!data.seo.twitterDescription) {
      data.seo.twitterDescription = desc;
    }

    // Auto-link Featured Image to OG & Twitter images
    if (data.featuredImage) {
      if (!data.seo.ogImage) {
        data.seo.ogImage = data.featuredImage;
      }
      if (!data.seo.twitterImage) {
        data.seo.twitterImage = data.featuredImage;
      }
    }
  },

  async beforeUpdate(event) {
    const { data } = event.params;
    if (!data) return;

    if (!data.slug && data.title) {
      data.slug = slugify(data.title);
    }

    if (data.seo && data.featuredImage) {
      if (!data.seo.ogImage) {
        data.seo.ogImage = data.featuredImage;
      }
      if (!data.seo.twitterImage) {
        data.seo.twitterImage = data.featuredImage;
      }
    }
  }
};
