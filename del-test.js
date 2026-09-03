const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  await client.query(`DELETE FROM completed_projects WHERE document_id = 'gocivghs7fs5lt8csq7y29ur'`);
  await client.end();
}
run().catch(console.error);
