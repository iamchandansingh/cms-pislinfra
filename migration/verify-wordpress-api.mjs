import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const WP_BASE = 'http://127.0.0.1:8080/wp-json/pislinfra/v1';
const dataset = JSON.parse(fs.readFileSync(path.join(__dirname, 'local-dataset.json'), 'utf8'));

let failures = 0;
function assert(cond, msg) {
  if (!cond) {
    failures++;
    console.error('FAIL:', msg);
  } else {
    console.log('PASS:', msg);
  }
}

async function getJson(url) {
  const res = await fetch(url, { headers: { Origin: 'http://localhost:5173' } });
  const text = await res.text();
  let json = null;
  try { json = JSON.parse(text); } catch {}
  return { status: res.status, headers: res.headers, json, text };
}

async function main() {
  // 1. Health & CORS
  const health = await getJson(`${WP_BASE}/health`);
  assert(health.status === 200 && health.json?.ok === true, 'Health endpoint returns 200 ok:true');
  assert(
    health.headers.get('access-control-allow-origin') === 'http://localhost:5173',
    `CORS Allow-Origin matches http://localhost:5173 (${health.headers.get('access-control-allow-origin')})`
  );

  // 2. Collection counts match local dataset
  for (const [slug, expectedItems] of Object.entries(dataset.collections)) {
    const res = await getJson(`${WP_BASE}/${slug}?pagination[pageSize]=100`);
    assert(res.status === 200, `GET /${slug} -> 200`);
    const actualCount = Array.isArray(res.json?.data) ? res.json.data.length : -1;
    assert(
      actualCount === expectedItems.length,
      `Collection /${slug} count matches local dataset: expected=${expectedItems.length}, got=${actualCount}`
    );
  }

  // 3. Single types (all 19)
  for (const slug of Object.keys(dataset.singleTypes)) {
    const res = await getJson(`${WP_BASE}/${slug}`);
    assert(res.status === 200 && res.json?.data && typeof res.json.data === 'object', `Single-Type /${slug} -> 200 object`);
  }

  // 4. Filter by slug + SEO + Gallery + PDFs + EHS resource normalization + Governance policies
  const blogs = await getJson(`${WP_BASE}/blogs?pagination[pageSize]=100`);
  const sampleBlog = blogs.json.data[0];
  assert(Boolean(sampleBlog?.slug && sampleBlog?.featuredImage?.url), `Sample blog has slug (${sampleBlog?.slug}) & featuredImage URL`);
  assert(sampleBlog.featuredImage.url.includes('/wp-content/uploads/'), `Blog image served from WordPress Media Library (${sampleBlog.featuredImage.url})`);

  const blogBySlug = await getJson(`${WP_BASE}/blogs?filters[slug][$eq]=${encodeURIComponent(sampleBlog.slug)}`);
  assert(blogBySlug.status === 200 && blogBySlug.json.data.length === 1, `Filter blogs by slug matches 1 record`);

  const ongoing = await getJson(`${WP_BASE}/ongoing-projects`);
  const ogWithGallery = ongoing.json.data.find((p) => Array.isArray(p.images) && p.images.length > 0);
  assert(Boolean(ogWithGallery), `Ongoing projects preserve gallery images arrays`);

  const ehs = await getJson(`${WP_BASE}/ehs-page`);
  const ehsResources = ehs.json?.data?.resources || [];
  assert(ehsResources.length === 4 && !ehsResources.some((r) => /pisl profile/i.test(r.title || '')), `EHS resources normalized (4 PDFs, PISL Profile excluded)`);

  const gov = await getJson(`${WP_BASE}/governance-page`);
  assert((gov.json?.data?.policies || []).length === 3, `Governance page embeds 3 Policy Documents`);

  const indSol = await getJson(`${WP_BASE}/solution-pages?filters[slug]=industrial`);
  assert(indSol.status === 200 && indSol.json.data.length === 1 && Boolean(indSol.json.data[0].seo?.seoTitle), `Solution page filter & SEO verified`);

  // 5. Sample Media Library HTTP 200 checks (images + PDFs)
  const sampleMediaUrls = [
    sampleBlog.featuredImage.url,
    ogWithGallery.images[0].url,
    ehsResources[0]?.file?.url,
    gov.json.data.policies[0]?.pdfFile?.url,
    gov.json.data.policies[0]?.coverImage?.url,
  ].filter(Boolean);

  for (const mUrl of sampleMediaUrls) {
    const mRes = await fetch(mUrl);
    assert(mRes.status === 200, `Media URL HTTP 200: ${mUrl}`);
  }

  // 6. Unauthorized write rejection (no credentials -> 401/403)
  const unauthWrite = await fetch(`${WP_BASE}/ongoing-projects/move-to-completed`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: ongoing.json.data[0]?.id }),
  });
  assert(unauthWrite.status === 401 || unauthWrite.status === 403, `Unauthorized POST move-to-completed blocked (${unauthWrite.status})`);

  const unauthWpPost = await fetch('http://127.0.0.1:8080/wp-json/wp/v2/posts', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Unauthorized Test' }),
  });
  assert(unauthWpPost.status === 401 || unauthWpPost.status === 403, `Unauthorized POST /wp/v2/posts blocked (${unauthWpPost.status})`);

  console.log(`\nVerification Summary: ${failures === 0 ? 'ALL PASSED' : failures + ' FAILED'}`);
  if (failures > 0) process.exit(1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
