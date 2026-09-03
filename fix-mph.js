const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  let inserted = 0;
  
  for (const tableName of ['completed_projects', 'ongoing_projects']) {
    const apiName = 'api::' + tableName.replace('_', '-') + '.' + tableName.replace('_', '-'); 
    
    const res = await client.query(`SELECT DISTINCT document_id FROM ${tableName}`);
    
    for (let row of res.rows) {
      const docId = row.document_id;
      const pubRes = await client.query(`SELECT id FROM ${tableName} WHERE document_id = $1 AND published_at IS NOT NULL`, [docId]);
      const draftRes = await client.query(`SELECT id FROM ${tableName} WHERE document_id = $1 AND published_at IS NULL`, [docId]);
      
      if (pubRes.rows.length > 0 && draftRes.rows.length > 0) {
        const pubId = pubRes.rows[0].id;
        const draftId = draftRes.rows[0].id;
        
        const links = await client.query(`SELECT * FROM files_related_mph WHERE related_id = $1 AND related_type = $2`, [pubId, apiName]);
        
        for (let link of links.rows) {
          const exist = await client.query(`SELECT id FROM files_related_mph WHERE related_id = $1 AND related_type = $2 AND file_id = $3 AND field = $4`, [draftId, apiName, link.file_id, link.field]);
          
          if (exist.rows.length === 0) {
            try {
              await client.query(`
                INSERT INTO files_related_mph (file_id, related_id, related_type, field, "order")
                VALUES ($1, $2, $3, $4, $5)
              `, [link.file_id, draftId, apiName, link.field, link.order]);
              inserted++;
            } catch(e) {
              console.log("INSERT ERROR:", e.message);
            }
          }
        }
      }
    }
  }
  
  console.log("Inserted draft images links:", inserted);
  await client.end();
}
run().catch(console.error);
