require('dotenv').config();
const { Client } = require('pg');

const client = new Client({
  connectionString: process.env.DATABASE_URL,
  ssl: { rejectUnauthorized: false }
});

async function main() {
  console.log('Connecting to PostgreSQL database...');
  await client.connect();
  console.log('Connected to DB successfully!');

  // 1. Add missing SEO columns to awards_pages table
  console.log('Ensuring SEO columns in awards_pages...');
  await client.query(`
    ALTER TABLE awards_pages
      ADD COLUMN IF NOT EXISTS primary_keyword VARCHAR(255),
      ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255),
      ADD COLUMN IF NOT EXISTS meta_description TEXT,
      ADD COLUMN IF NOT EXISTS focus_keywords TEXT;
  `);
  console.log('SEO columns verified in awards_pages.');

  // 2. Populate default / fallback SEO data in awards_pages rows
  console.log('Updating awards_pages records with default SEO values...');
  await client.query(`
    UPDATE awards_pages SET
      primary_keyword = COALESCE(primary_keyword, $1),
      meta_title = COALESCE(meta_title, $2),
      meta_description = COALESCE(meta_description, $3),
      focus_keywords = COALESCE(focus_keywords, $4);
  `, [
    'Industrial Construction Awards & Certifications India',
    'Awards & Certifications - Safety Milestones & Industry Honors | Pislinfra',
    'Explore Pislinfra prestigious awards, US LEED Gold certification, and zero-LTI safety milestones awarded by Adani, Reliance, and leading industry councils.',
    'Pislinfra awards, construction awards India, safety excellence awards, Adani best safety contractor, LEED Gold warehousing, industrial EPC honors, turnkey infrastructure accolades'
  ]);
  console.log('awards_pages records updated.');

  // 3. Configure Content Manager edit layout in strapi_core_store_settings (Row 65)
  console.log('Configuring Content Manager layout in strapi_core_store_settings (Row 65)...');
  const res = await client.query('SELECT id, key, value FROM strapi_core_store_settings WHERE id = 65');
  if (res.rows.length > 0) {
    const val = JSON.parse(res.rows[0].value);
    if (!val.metadatas) val.metadatas = {};
    val.metadatas.primaryKeyword = {
      edit: {
        label: 'Primary Keyword',
        description: 'The main target search phrase for the Awards page.',
        placeholder: 'e.g. Industrial Construction Awards & Certifications India',
        visible: true,
        editable: true
      },
      list: { label: 'Primary Keyword', searchable: true, sortable: true }
    };
    val.metadatas.metaTitle = {
      edit: {
        label: 'Meta Title',
        description: 'The SEO title displayed in search results (recommended: 50-60 chars).',
        placeholder: 'e.g. Awards & Certifications - Safety Milestones & Industry Honors | Pislinfra',
        visible: true,
        editable: true
      },
      list: { label: 'Meta Title', searchable: true, sortable: true }
    };
    val.metadatas.metaDescription = {
      edit: {
        label: 'Meta Description',
        description: 'The descriptive snippet for search results (recommended: 150-160 chars).',
        placeholder: 'e.g. Explore Pislinfra prestigious awards, US LEED Gold certification, and zero-LTI safety milestones...',
        visible: true,
        editable: true
      },
      list: { label: 'Meta Description', searchable: false, sortable: false }
    };
    val.metadatas.focusKeywords = {
      edit: {
        label: 'Focus Keywords',
        description: 'Optional additional related keywords for editorial SEO management.',
        placeholder: 'e.g. Pislinfra awards, construction awards India, safety excellence awards, LEED Gold warehousing',
        visible: true,
        editable: true
      },
      list: { label: 'Focus Keywords', searchable: false, sortable: false }
    };

    if (Array.isArray(val.layouts?.edit)) {
      val.layouts.edit = val.layouts.edit.filter(row =>
        !row.some(col => ['primaryKeyword', 'focusKeywords', 'metaTitle', 'metaDescription'].includes(col.name))
      );
      val.layouts.edit.splice(2, 0,
        [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
        [{ name: 'metaTitle', size: 12 }],
        [{ name: 'metaDescription', size: 12 }]
      );
    }

    await client.query('UPDATE strapi_core_store_settings SET value = $1 WHERE id = 65', [JSON.stringify(val)]);
    console.log('Successfully updated strapi_core_store_settings row 65 for awards-page!');
  } else {
    console.warn('Row 65 not found in strapi_core_store_settings.');
  }

  await client.end();
  console.log('All awards-page SEO setup complete!');
}

main().catch(err => {
  console.error('Migration failed:', err.message);
  process.exit(1);
});
