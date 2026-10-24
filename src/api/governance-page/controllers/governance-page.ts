import { factories } from '@strapi/strapi';

export default factories.createCoreController('api::governance-page.governance-page', () => ({
  // Public site only ever sees published content, even if ?status=draft is passed
  async find(ctx) {
    ctx.query = { ...ctx.query, status: 'published' };
    return super.find(ctx);
  },
}));
