const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  const res = await client.query(`SELECT id, published_at FROM completed_projects LIMIT 2`);
  console.log(res.rows);
  await client.end();
}
run().catch(console.error);
