export default ({ env }: { env: any }) => {
  const connectionString = env("DATABASE_URL", "postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb");
  return {
    connection: {
      client: "postgres",
      connection: {
        connectionString: connectionString,
        ssl: { rejectUnauthorized: false },
      },
      pool: { min: 2, max: 10 },
    },
  };
};
