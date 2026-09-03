const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  const drafts = await client.query(`SELECT id FROM completed_projects WHERE published_at IS NULL`);
  let totalLinks = 0;
  for (let row of drafts.rows) {
    const links = await client.query(`SELECT * FROM files_related_mph WHERE related_id = $1 AND related_type = 'api::completed-project.completed-project'`, [row.id]);
    totalLinks += links.rows.length;
  }
  console.log("Total image links for all drafts:", totalLinks);
  await client.end();
}
run().catch(console.error);
