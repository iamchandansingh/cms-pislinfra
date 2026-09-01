import { factories } from '@strapi/strapi';
export default factories.createCoreRouter('api::policy-page.policy-page', {
  config: {
    find: { auth: false },
    findOne: { auth: false }
  }
});
