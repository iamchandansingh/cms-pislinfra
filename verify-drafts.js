const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  const res = await client.query(`SELECT count(*) FROM completed_projects WHERE published_at IS NULL`);
  console.log("Completed projects drafts:", res.rows[0].count);
  const res2 = await client.query(`SELECT count(*) FROM ongoing_projects WHERE published_at IS NULL`);
  console.log("Ongoing projects drafts:", res2.rows[0].count);
  await client.end();
}
run().catch(console.error);
