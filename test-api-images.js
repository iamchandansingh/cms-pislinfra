const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  // Get Blue Dart Warehouse
  const res = await client.query(`SELECT id FROM completed_projects WHERE name = 'Blue Dart Warehouse'`);
  if (res.rows.length) {
    const pubId = res.rows[0].id;
    const links = await client.query(`SELECT file_id FROM files_related_mph WHERE related_id = $1 AND related_type = 'api::completed-project.completed-project'`, [pubId]);
    console.log("Blue Dart File IDs:", links.rows.map(r => r.file_id));
  }
  await client.end();
}
run().catch(console.error);
