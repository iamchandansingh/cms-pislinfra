import { factories } from '@strapi/strapi';

let ehsCache: any = null;
let lastCacheTime = 0;
const CACHE_TTL = 120 * 1000; // 2 minutes in memory

(global as any).clearEhsCache = () => {
  ehsCache = null;
  lastCacheTime = 0;
};

export default factories.createCoreController('api::ehs-page.ehs-page', ({ strapi }) => ({
  async find(ctx) {
    const isExplicitFresh = ctx.query?.fresh === 'true' || Boolean(ctx.headers['cache-control']?.includes('no-cache'));
    if (ctx.query && 'fresh' in ctx.query) {
      delete ctx.query.fresh;
    }

    const isDevelopment = process.env.NODE_ENV === 'development' || !process.env.NODE_ENV;
    if (!isExplicitFresh && !isDevelopment && ehsCache && (Date.now() - lastCacheTime < CACHE_TTL)) {
      return ehsCache;
    }

    // Ensure PISL Profile component link is removed from EHS page resources in database
    // (PISL Profile file & component remain intact in Strapi for About Page companyProfile)
    try {
      await strapi.db.connection('ehs_pages_cmps')
        .where({ entity_id: 1, field: 'resources' })
        .whereIn('cmp_id', function() {
          this.select('id').from('components_shared_resource_items').whereILike('title', '%PISL Profile%');
        })
        .del();
    } catch (e) {
      // Ignore if table or relation differs
    }

    // Explicitly guarantee deep populates for all components with media and nested fields
    ctx.query = {
      ...ctx.query,
      populate: {
        heroImage: true,
        seo: {
          populate: {
            ogImage: true,
            twitterImage: true,
          },
        },
        stats: true,
        features: true,
        philosophy: {
          populate: {
            image: true,
          },
        },
        approaches: true,
        rules: true,
        pillars: true,
        programs: true,
        gallery: {
          populate: {
            image: true,
          },
        },
        awards: {
          populate: {
            image: true,
          },
        },
        resources: {
          populate: {
            file: true,
            coverImage: true,
          },
        },
        resourcesCoverImage: true,
      },
    };

    const response = await super.find(ctx);
    
    // Process resources: Exclude PISL Profile and order the 4 required policies
    if (response?.data?.resources && Array.isArray(response.data.resources)) {
      const orderKeys = ['hse', 'risk', 'vigil', 'csr'];
      const getPriority = (title: string = '') => {
        const t = title.toLowerCase();
        if (t.includes('hse') || t.includes('health') || t.includes('safety policy')) return 1;
        if (t.includes('risk')) return 2;
        if (t.includes('vigil') || t.includes('whistle')) return 3;
        if (t.includes('csr') || t.includes('social responsibility')) return 4;
        return 99;
      };

      response.data.resources = response.data.resources
        .filter((r: any) => !r.title?.toLowerCase().includes('pisl profile'))
        .sort((a: any, b: any) => getPriority(a.title) - getPriority(b.title));

      const resSummary = response.data.resources.map((r: any) => `${r.title}: file=${r.file?.url || r.file?.name || 'NO_FILE'}`).join(' | ');
      strapi.log.info(`[EHS Controller] resources populated (${response.data.resources.length}): ${resSummary}`);
    }

    ehsCache = response;
    lastCacheTime = Date.now();
    return response;
  },
}));
