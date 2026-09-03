const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  const pubs = await client.query(`SELECT id FROM completed_projects WHERE published_at IS NOT NULL AND id < 77 LIMIT 1`);
  if (pubs.rows.length) {
    const pubId = pubs.rows[0].id;
    console.log("pubId:", pubId);
    const links = await client.query(`SELECT * FROM files_related_mph WHERE related_id = $1`, [pubId]);
    console.log("links:", links.rows);
  }
  await client.end();
}
run().catch(console.error);
