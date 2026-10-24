import { factories } from '@strapi/strapi';

export default factories.createCoreController('api::policy-document.policy-document', () => ({
  // Public site only ever sees published content, even if ?status=draft is passed
  async find(ctx) {
    ctx.query = { ...ctx.query, status: 'published' };
    return super.find(ctx);
  },
  async findOne(ctx) {
    ctx.query = { ...ctx.query, status: 'published' };
    return super.findOne(ctx);
  },
}));
