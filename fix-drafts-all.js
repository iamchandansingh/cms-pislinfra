const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function fixTable(tableName) {
  const res = await client.query(`SELECT DISTINCT document_id FROM ${tableName}`);
  let inserted = 0;
  for (let row of res.rows) {
    const docId = row.document_id;
    const draftRes = await client.query(`SELECT id FROM ${tableName} WHERE document_id = $1 AND published_at IS NULL`, [docId]);
    if (draftRes.rows.length === 0) {
      const pubRes = await client.query(`SELECT * FROM ${tableName} WHERE document_id = $1`, [docId]);
      if (pubRes.rows.length > 0) {
        const p = pubRes.rows[0];
        
        const cols = Object.keys(p).filter(k => k !== 'id' && k !== 'published_at');
        const vals = cols.map(k => p[k]);
        
        const placeholders = cols.map((_, i) => `$${i+1}`).join(', ');
        const colNames = cols.join(', ');
        
        await client.query(`
          INSERT INTO ${tableName} (
            ${colNames}, published_at
          ) VALUES (
            ${placeholders}, NULL
          )
        `, vals);
        inserted++;
      }
    }
  }
  console.log(`Inserted drafts for ${tableName}:`, inserted);
}

async function run() {
  await client.connect();
  
  await fixTable('ongoing_projects');
  await fixTable('case_studies');
  
  await client.end();
}
run().catch(console.error);
