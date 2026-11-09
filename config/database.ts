export default ({ env }: { env: any }) => {
  let connectionString = env(
    "DATABASE_URL",
    "postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj.c-4.us-east-2.aws.neon.tech/neondb?sslmode=require"
  );
  // Neon direct connection (without -pooler) is required for persistent Strapi Knex pools
  connectionString = connectionString.replace("-pooler.", ".");

  return {
    connection: {
      client: "postgres",
      connection: {
        connectionString,
        ssl: { rejectUnauthorized: false },
        keepAlive: true,
      },
      pool: {
        min: 0,
        max: 10,
        acquireTimeoutMillis: 30000,
        idleTimeoutMillis: 10000,
      },
    },
  };
};
