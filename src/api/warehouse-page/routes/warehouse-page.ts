import { factories } from '@strapi/strapi';

export default factories.createCoreRouter('api::warehouse-page.warehouse-page', {
  config: {
    find: {
      auth: false,
    },
    findOne: {
      auth: false,
    },
  },
});
