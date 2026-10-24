import { factories } from '@strapi/strapi';

// Public read access comes from the Public role (granted in src/index.ts bootstrap).
// Don't use `auth: false` here: without an auth context Strapi strips the populated `policies` relation.
export default factories.createCoreRouter('api::governance-page.governance-page', {
  only: ['find'],
});
