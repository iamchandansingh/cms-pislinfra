const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  const res = await client.query(`SELECT COUNT(*) FROM completed_projects WHERE published_at IS NULL`);
  console.log("Drafts:", res.rows[0].count);
  const res2 = await client.query(`SELECT COUNT(*) FROM completed_projects WHERE published_at IS NOT NULL`);
  console.log("Published:", res2.rows[0].count);
  
  // check images links for draft
  const draftId = await client.query(`SELECT id FROM completed_projects WHERE published_at IS NULL LIMIT 1`);
  if (draftId.rows.length) {
    const links = await client.query(`SELECT * FROM files_related_mph WHERE related_id = $1 AND related_type = 'api::completed-project.completed-project'`, [draftId.rows[0].id]);
    console.log("Images for draft 1:", links.rows.length);
  }
  
  await client.end();
}
run().catch(console.error);
