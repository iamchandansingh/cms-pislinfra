const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  
  try {
    const r1 = await client.query(`UPDATE ongoing_projects SET locale = 'en' WHERE locale IS NULL RETURNING id`);
    console.log("Updated ongoing_projects:", r1.rowCount);
  } catch (e) { console.log(e.message) }

  try {
    const r2 = await client.query(`UPDATE case_studies SET locale = 'en' WHERE locale IS NULL RETURNING id`);
    console.log("Updated case_studies:", r2.rowCount);
  } catch (e) { console.log(e.message) }

  await client.end();
}
run().catch(console.error);
