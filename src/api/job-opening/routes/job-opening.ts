import { factories } from '@strapi/strapi';
export default factories.createCoreRouter('api::job-opening.job-opening', {
  config: {
    find: { auth: false },
    findOne: { auth: false }
  }
});
