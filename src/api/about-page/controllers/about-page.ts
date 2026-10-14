import { factories } from '@strapi/strapi';

export default factories.createCoreController('api::about-page.about-page', ({ strapi }) => ({
  async find(ctx) {
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
        capabilities: true,
        values: true,
        companyProfile: {
          populate: '*',
        },
        resources: {
          populate: '*',
        },
      },
    };

    const response = await super.find(ctx);
    return response;
  },
}));
