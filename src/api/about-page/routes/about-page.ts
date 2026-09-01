import { factories } from '@strapi/strapi';
export default factories.createCoreRouter('api::about-page.about-page', {
  config: {
    find: { auth: false },
    findOne: { auth: false }
  }
});
