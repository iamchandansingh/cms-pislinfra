const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  
  const res = await client.query(`SELECT DISTINCT document_id FROM ongoing_projects`);
  
  let inserted = 0;
  for (let row of res.rows) {
    const docId = row.document_id;
    const pubRes = await client.query(`SELECT id FROM ongoing_projects WHERE document_id = $1 AND published_at IS NOT NULL`, [docId]);
    const draftRes = await client.query(`SELECT id FROM ongoing_projects WHERE document_id = $1 AND published_at IS NULL`, [docId]);
    
    if (pubRes.rows.length > 0 && draftRes.rows.length > 0) {
      const pubId = pubRes.rows[0].id;
      const draftId = draftRes.rows[0].id;
      
      const images = await client.query(`SELECT * FROM ongoing_projects_images_links WHERE ongoing_project_id = $1`, [pubId]);
      
      for (let img of images.rows) {
        const exist = await client.query(`SELECT * FROM ongoing_projects_images_links WHERE ongoing_project_id = $1 AND file_id = $2`, [draftId, img.file_id]);
        if (exist.rows.length === 0) {
          await client.query(`
            INSERT INTO ongoing_projects_images_links (ongoing_project_id, file_id, ongoing_project_order) 
            VALUES ($1, $2, $3)
          `, [draftId, img.file_id, img.ongoing_project_order || 1]);
          inserted++;
        }
      }
    }
  }
  
  console.log("Inserted relations for ongoing_projects:", inserted);
  await client.end();
}
run().catch(console.error);
