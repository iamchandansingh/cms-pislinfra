export default [
  'strapi::logger',
  'strapi::errors',
  {
    name: 'strapi::security',
    config: {
      contentSecurityPolicy: {
        useDefaults: true,
        directives: {
          'connect-src': ["'self'", 'https:'],
          'img-src': [
            "'self'", 
            'data:', 
            'blob:', 
            'res.cloudinary.com', 
            'pub-eeb28a3c927b4ae1b67e3e8e731ee105.r2.dev',
            '3282b1f29fc44baa63c4b1c3bcdd7d22.r2.cloudflarestorage.com'
          ],
          'media-src': [
            "'self'", 
            'data:', 
            'blob:', 
            'res.cloudinary.com', 
            'pub-eeb28a3c927b4ae1b67e3e8e731ee105.r2.dev',
            '3282b1f29fc44baa63c4b1c3bcdd7d22.r2.cloudflarestorage.com'
          ],
          upgradeInsecureRequests: null,
        },
      },
    },
  },
  'strapi::cors',
  'strapi::poweredBy',
  'strapi::query',
  'strapi::body',
  'strapi::session',
  'strapi::favicon',
  'strapi::public',
];
