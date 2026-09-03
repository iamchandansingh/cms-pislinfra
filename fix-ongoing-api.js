const { Client } = require('pg');
const client = new Client({
  connectionString: 'postgresql://neondb_owner:npg_0veFtX8HTnmY@ep-mute-voice-axlrx2cj-pooler.c-4.us-east-2.aws.neon.tech/neondb',
  ssl: { rejectUnauthorized: false }
});
const fs = require('fs');

async function run() {
  await client.connect();
  
  // Read ongoing projects data
  const dataFile = fs.readFileSync('../pislinfra-react/src/data/projectsData.js', 'utf8');
  // quick and dirty eval
  const jsCode = dataFile.replace('export default projectsData;', 'module.exports = projectsData;');
  fs.writeFileSync('./temp-ongoing.js', jsCode);
  const projectsData = require('./temp-ongoing.js');
  
  for (let p of projectsData) {
    if (p.status !== 'Ongoing') continue;
    
    const imageIds = [];
    if (p.images) {
      for (let imgPath of p.images) {
        const parts = imgPath.split('/');
        let filename = parts[parts.length - 1];
        // match filename
        const res = await client.query(`SELECT id FROM files WHERE name = $1 LIMIT 1`, [filename]);
        if (res.rows.length) {
          imageIds.push(res.rows[0].id);
        }
      }
    }
    
    const body = {
      data: {
        name: p.name,
        category: p.category,
        location: p.location,
        state: p.state,
        area: p.area,
        client: p.client,
        timeline: p.timeline,
        projectStatus: p.status,
        scope: p.scope,
        images: imageIds
      }
    };
    
    // clean undefined
    for(let k in body.data) if(body.data[k] === undefined) delete body.data[k];
    
    const resp = await fetch("http://127.0.0.1:1337/api/ongoing-projects", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body)
    });
    
    if (resp.ok) {
      console.log("Migrated Ongoing:", p.name);
    } else {
      console.error("Failed Ongoing:", p.name, await resp.text());
    }
  }
  
  await client.end();
}
run().catch(console.error);
