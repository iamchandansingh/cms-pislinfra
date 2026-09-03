const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  const r1 = await client.query(`UPDATE completed_projects SET locale = NULL WHERE locale = 'en'`);
  console.log("Reverted completed_projects locale:", r1.rowCount);
  const r2 = await client.query(`UPDATE ongoing_projects SET locale = NULL WHERE locale = 'en'`);
  console.log("Reverted ongoing_projects locale:", r2.rowCount);
  await client.end();
}
run().catch(console.error);
