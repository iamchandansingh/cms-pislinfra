const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  const res = await client.query(`SELECT id, document_id, locale FROM completed_projects LIMIT 5`);
  console.log("completed_projects:", res.rows);
  
  const docRes = await client.query(`SELECT * FROM strapi_documents WHERE document_id = $1`, [res.rows[0].document_id]);
  console.log("strapi_documents:", docRes.rows);
  
  await client.end();
}
run().catch(console.error);
