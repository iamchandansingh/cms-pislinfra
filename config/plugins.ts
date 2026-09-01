export default ({ env }: { env: any }) => ({
  upload: {
    config: {
      provider: '@strapi/provider-upload-aws-s3',
      providerOptions: {
        baseUrl: 'https://pub-eeb28a3c927b4ae1b67e3e8e731ee105.r2.dev',
        rootPath: '',
        s3Options: {
          credentials: {
            accessKeyId: '2e0b9723f16cc4c70b48aa4658fcd760',
            secretAccessKey: 'dff42db69e5004fd42a7d3e061782bb55142290fa797beb4caf5ad54fa7c6984',
          },
          region: 'auto',
          endpoint: 'https://3282b1f29fc44baa63c4b1c3bcdd7d22.r2.cloudflarestorage.com',
          params: {
            Bucket: 'pisl-cms-media',
          },
        },
      },
      actionOptions: {
        upload: {},
        uploadStream: {},
        delete: {},
      },
    },
  },
});
