import { factories } from "@strapi/strapi";

export default factories.createCoreController("api::home-page.home-page", ({ strapi }) => ({
  async find(ctx) {
    ctx.query = {
      ...ctx.query,
      populate: {
        stats: true,
        hseImage: true,
        mapMarkers: true,
        ...(typeof ctx.query?.populate === "object" ? ctx.query.populate : {}),
      },
    };
    return await super.find(ctx);
  },
}));
