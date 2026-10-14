export default ({ env }: { env: any }) => {
  let connectionString = env("DATABASE_URL", "postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj.c-4.us-east-2.aws.neon.tech/neondb?sslmode=require");
  // Neon direct connection (without -pooler) is required for persistent Strapi Knex pools
  // to avoid PgBouncer transaction queuing bottlenecks
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
        min: 1,
        max: 8,
        acquireTimeoutMillis: 60000,
        idleTimeoutMillis: 30000,
      },
    },
  };
};
