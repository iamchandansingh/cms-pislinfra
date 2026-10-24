import { factories } from '@strapi/strapi';

// Public read access comes from the Public role (granted in src/index.ts bootstrap).
export default factories.createCoreRouter('api::policy-document.policy-document', {
  only: ['find', 'findOne'],
});
