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
    const pubRes = await client.query(`SELECT id FROM completed_projects WHERE document_id = $1 AND published_at IS NOT NULL`, [docId]);
    const draftRes = await client.query(`SELECT id FROM completed_projects WHERE document_id = $1 AND published_at IS NULL`, [docId]);
    
    if (pubRes.rows.length > 0 && draftRes.rows.length > 0) {
      const pubId = pubRes.rows[0].id;
      const draftId = draftRes.rows[0].id;
      
      // Get images for pub
      const images = await client.query(`SELECT * FROM completed_projects_images_links WHERE completed_project_id = $1`, [pubId]);
      
      for (let img of images.rows) {
        // check if draft already has this image
        const exist = await client.query(`SELECT * FROM completed_projects_images_links WHERE completed_project_id = $1 AND file_id = $2`, [draftId, img.file_id]);
        if (exist.rows.length === 0) {
          // find max id
          const maxIdRes = await client.query(`SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM completed_projects_images_links`);
          const nextId = maxIdRes.rows[0].next_id;
          
          await client.query(`
            INSERT INTO completed_projects_images_links (completed_project_id, file_id, completed_project_order) 
            VALUES ($1, $2, $3)
          `, [draftId, img.file_id, img.completed_project_order || 1]);
          inserted++;
        }
      }
    }
  }
  
  console.log("Inserted relations for completed_projects:", inserted);
  await client.end();
}
run().catch(console.error);
