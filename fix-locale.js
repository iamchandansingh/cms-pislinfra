const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  
  // Update locale to 'en' where it is null for completed_projects
  const res = await client.query(`UPDATE completed_projects SET locale = 'en' WHERE locale IS NULL RETURNING id`);
  console.log("Updated completed_projects:", res.rowCount);
  
  await client.end();
}
run().catch(console.error);
