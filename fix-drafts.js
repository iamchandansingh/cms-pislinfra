const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  
  // Find all distinct document_ids
  const res = await client.query(`SELECT DISTINCT document_id FROM completed_projects`);
  
  let inserted = 0;
  for (let row of res.rows) {
    const docId = row.document_id;
    // Check if this doc has a draft (published_at IS NULL)
    const draftRes = await client.query(`SELECT id FROM completed_projects WHERE document_id = $1 AND published_at IS NULL`, [docId]);
    if (draftRes.rows.length === 0) {
      // Get the published row
      const pubRes = await client.query(`SELECT * FROM completed_projects WHERE document_id = $1`, [docId]);
      if (pubRes.rows.length > 0) {
        const p = pubRes.rows[0];
        // Insert a draft clone
        await client.query(`
          INSERT INTO completed_projects (
            document_id, name, category, location, state, area, client, timeline, project_status, scope, 
            created_at, updated_at, published_at, locale, cx, cy
          ) VALUES (
            $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, 
            $11, $12, NULL, $13, $14, $15
          )
        `, [
          p.document_id, p.name, p.category, p.location, p.state, p.area, p.client, p.timeline, p.project_status, p.scope,
          p.created_at, p.updated_at, p.locale, p.cx, p.cy
        ]);
        inserted++;
      }
    }
  }
  
  console.log("Inserted drafts for completed_projects:", inserted);
  await client.end();
}
run().catch(console.error);
