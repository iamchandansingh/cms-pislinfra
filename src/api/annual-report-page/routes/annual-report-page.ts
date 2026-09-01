import { factories } from '@strapi/strapi';
export default factories.createCoreRouter('api::annual-report-page.annual-report-page', {
  config: {
    find: { auth: false },
    findOne: { auth: false }
  }
});
