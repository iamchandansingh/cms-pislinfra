export default [
  'strapi::logger',
  'strapi::errors',
  {
    name: 'strapi::security',
    config: {
      contentSecurityPolicy: {
        useDefaults: true,
        directives: {
          'connect-src': ["'self'", 'https:', 'http:'],
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
  {
    name: 'strapi::cors',
    config: {
      enabled: true,
      origin: [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://pislinfra.com',
        'https://www.pislinfra.com',
        '*'
      ],
      headers: '*',
      methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
      keepHeaderOnError: true,
    },
  },
  'strapi::poweredBy',
  'strapi::query',
  'strapi::body',
  'strapi::session',
  'strapi::favicon',
  'strapi::public',
];
