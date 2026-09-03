const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});

async function run() {
  await client.connect();
  
  const tables = await client.query(`
    SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'
  `);
  
  for (let t of tables.rows) {
    const tableName = t.table_name;
    try {
      const colsRes = await client.query(`
        SELECT column_name, data_type FROM information_schema.columns 
        WHERE table_name = $1 AND data_type IN ('character varying', 'text', 'jsonb')
      `, [tableName]);
      
      for (let col of colsRes.rows) {
        let query;
        if (col.data_type === 'jsonb') {
          query = `SELECT * FROM "${tableName}" WHERE "${col.column_name}"::text LIKE '%gocivghs7fs5lt8csq7y29ur%'`;
        } else {
          query = `SELECT * FROM "${tableName}" WHERE "${col.column_name}" LIKE '%gocivghs7fs5lt8csq7y29ur%'`;
        }
        const hits = await client.query(query);
        if (hits.rows.length > 0) {
          console.log(`Found in table: ${tableName}, column: ${col.column_name}`);
        }
      }
    } catch(e) {}
  }
  
  await client.end();
}
run().catch(console.error);
