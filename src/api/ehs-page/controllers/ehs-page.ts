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
    const isExplicitFresh = ctx.query?.fresh === 'true';

    if (!isExplicitFresh && ehsCache && (Date.now() - lastCacheTime < CACHE_TTL)) {
      return ehsCache;
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
      },
    };

    const response = await super.find(ctx);
    
    // Log philosophy items images to confirm population
    if (response?.data?.philosophy) {
      const items = response.data.philosophy;
      const debugSummary = items.map((p: any) => `${p.title}: ${p.image?.url || p.image?.name || 'NO_IMAGE'}`).join(' | ');
      strapi.log.info(`[EHS Controller] philosophy populated: ${debugSummary}`);
    }

    if (response?.data?.resources) {
      const items = response.data.resources;
      const resSummary = items.map((r: any) => `${r.title}: file=${r.file?.url || r.file?.name || 'NO_FILE'}`).join(' | ');
      strapi.log.info(`[EHS Controller] resources populated: ${resSummary}`);
    }

    ehsCache = response;
    lastCacheTime = Date.now();
    return response;
  },
}));
