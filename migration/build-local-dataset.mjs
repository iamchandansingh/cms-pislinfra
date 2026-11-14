import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '../..');
const REACT_DIR = path.join(ROOT, 'pislinfra-react');
const STRAPI_UPLOADS = path.join(ROOT, 'cms-pisl/public/uploads');

const MAIN_BACKUP = path.join(REACT_DIR, 'src/data/MainBackupPislinfra.json');
const SNAPSHOT_AWARDS = path.join(REACT_DIR, 'src/data/backups/snapshot-2026-11-12T05-18-09-631Z.json');
const OUT_FILE = path.join(__dirname, 'local-dataset.json');

// Index all local media files across local project directories (zero network calls)
const localFileIndex = new Map();
const searchRoots = [
  path.join(REACT_DIR, 'public/images/uploads'),
  path.join(REACT_DIR, 'public/reports'),
  path.join(REACT_DIR, 'public/videos'),
  path.join(REACT_DIR, 'public/images'),
  STRAPI_UPLOADS,
  path.join(REACT_DIR, 'src/assets'),
];

function walkDir(dir) {
  if (!fs.existsSync(dir)) return;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name.startsWith('.')) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      walkDir(full);
    } else if (entry.isFile()) {
      const lower = entry.name.toLowerCase();
      if (!localFileIndex.has(lower)) {
        localFileIndex.set(lower, full);
      }
      const norm = lower.replace(/[^a-z0-9.]/g, '_');
      if (!localFileIndex.has(norm)) {
        localFileIndex.set(norm, full);
      }
    }
  }
}

for (const r of searchRoots) walkDir(r);

function resolveLocalFile(urlOrPath) {
  if (!urlOrPath || typeof urlOrPath !== 'string') return null;
  let clean = urlOrPath.trim().split('?')[0].split('#')[0];
  if (!clean) return null;

  // Handle relative public path directly if it starts with /images/, /reports/, /videos/
  if (clean.startsWith('/')) {
    const directPublic = path.join(REACT_DIR, 'public', clean);
    if (fs.existsSync(directPublic) && fs.statSync(directPublic).isFile()) {
      return directPublic;
    }
  }

  // Extract filename from URL or path
  let filename = '';
  try {
    filename = clean.startsWith('http')
      ? decodeURIComponent(new URL(clean).pathname.split('/').pop() || '')
      : decodeURIComponent(clean.split('/').pop() || '');
  } catch {
    filename = clean.split('/').pop() || '';
  }
  if (!filename) return null;

  // Strip Strapi responsive prefix if caller pointed to resized variant
  const unprefixed = filename.replace(/^(thumbnail|small|medium|large)_/, '');
  const candidates = [
    filename,
    unprefixed,
    filename.replace(/[^a-zA-Z0-9._-]/g, '_'),
    unprefixed.replace(/[^a-zA-Z0-9._-]/g, '_'),
  ];

  // Fallback mapping for outdated local EHS cover hash
  if (filename === 'pisl_EHS_100kb_82653bd4f2.jpeg') {
    candidates.unshift('pisl_EHS_100kb_1da8061955.jpeg');
  }

  for (const c of candidates) {
    const hit = localFileIndex.get(c.toLowerCase()) || localFileIndex.get(c.toLowerCase().replace(/[^a-z0-9.]/g, '_'));
    if (hit && fs.existsSync(hit)) return hit;
  }
  for (const prefix of ['large_', 'medium_', 'small_', 'thumbnail_']) {
    const pCandidate = prefix + unprefixed;
    const hit = localFileIndex.get(pCandidate.toLowerCase()) || localFileIndex.get(pCandidate.toLowerCase().replace(/[^a-z0-9.]/g, '_'));
    if (hit && fs.existsSync(hit)) return hit;
  }
  return null;
}

const mainJson = JSON.parse(fs.readFileSync(MAIN_BACKUP, 'utf8'));
const snapJson = fs.existsSync(SNAPSHOT_AWARDS) ? JSON.parse(fs.readFileSync(SNAPSHOT_AWARDS, 'utf8')) : {};

function extractPayload(jsonObj, primaryKey, ...fallbackKeys) {
  for (const k of [primaryKey, ...fallbackKeys]) {
    if (jsonObj && jsonObj[k] !== undefined) {
      const raw = jsonObj[k];
      return raw && typeof raw === 'object' && 'data' in raw ? raw.data : raw;
    }
  }
  return null;
}

function dedupeByKey(arr, keyFn) {
  const seen = new Map();
  for (const item of arr || []) {
    if (!item || typeof item !== 'object') continue;
    const key = keyFn(item);
    if (!seen.has(key)) seen.set(key, item);
  }
  return Array.from(seen.values());
}

// Merge awards from snapshot (45 records) + mainJson (25 records) without duplicates
const rawAwardsSnap = extractPayload(snapJson, 'awards', 'awards?populate=*&pagination[pageSize]=100') || [];
const rawAwardsMain = extractPayload(mainJson, 'awards') || [];
const mergedAwards = dedupeByKey(
  [...rawAwardsSnap, ...rawAwardsMain],
  (a) => a.documentId || `${(a.title || '').trim().toLowerCase()}::${a.year || ''}::${(a.company || '').trim().toLowerCase()}`
);

// Merge solution-pages ensuring all 5 slugs (warehouse, industrial, infrastructure, logistic, solutions)
const solutionList = dedupeByKey(
  [
    ...(extractPayload(mainJson, 'solution-pages') || []),
    ...(extractPayload(mainJson, 'solution-pages-warehouse') || []),
    ...(extractPayload(mainJson, 'solution-pages-industrial') || []),
    ...(extractPayload(mainJson, 'solution-pages-infrastructure') || []),
    ...(extractPayload(mainJson, 'solution-pages-logistic') || []),
    ...(extractPayload(mainJson, 'solution-pages-solutions') || []),
  ],
  (s) => s.slug || s.documentId || String(s.id)
);

// Default Governance Policy Documents extracted from PoliciesGovernance.jsx + local files
const defaultPolicies = [
  {
    documentId: 'policy_risk_management',
    slug: 'risk-management-policy',
    title: 'Risk Management Policy',
    shortDescription:
      'Policy framework establishing risk assessment, mitigation, and governance standards across all PISL operations.',
    coverImage: {
      url: '/images/uploads/Risk_49056e0c4c.jpeg',
      alternativeText: 'Risk Management Policy Cover',
    },
    coverImageAlt: 'Risk Management Policy Cover',
    pdfFile: {
      url: '/reports/Risk_Management_Policy_PISL_37fe051837.pdf',
      name: 'Risk_Management_Policy_PISL_37fe051837.pdf',
      mime: 'application/pdf',
    },
    buttonName: 'Risk Management Policy',
    documentLabel: 'PDF Document',
    displayOrder: 1,
    accentType: 'navy',
    updatedLabel: 'Last Updated: September 2026',
  },
  {
    documentId: 'policy_vigil_mechanism',
    slug: 'vigil-mechanism-policy',
    title: 'Vigil Mechanism Policy',
    shortDescription:
      'Whistleblower protection and ethical reporting mechanism ensuring transparency and accountability.',
    coverImage: {
      url: '/images/uploads/Vigil_cc39e832ef.jpeg',
      alternativeText: 'Vigil Mechanism Policy Cover',
    },
    coverImageAlt: 'Vigil Mechanism Policy Cover',
    pdfFile: {
      url: '/reports/Vigil_Mechanism_Policy_PISL_6c1fe65e70.pdf',
      name: 'Vigil_Mechanism_Policy_PISL_6c1fe65e70.pdf',
      mime: 'application/pdf',
    },
    buttonName: 'Vigil Mechanism Policy',
    documentLabel: 'PDF Document',
    displayOrder: 2,
    accentType: 'orange',
    updatedLabel: 'Last Updated: September 2026',
  },
  {
    documentId: 'policy_csr',
    slug: 'csr-policy',
    title: 'CSR Policy',
    shortDescription:
      'Corporate Social Responsibility commitment towards community upliftment, environmental sustainability, and education.',
    coverImage: {
      url: '/images/uploads/CSR_017ee514a5.jpeg',
      alternativeText: 'CSR Policy Cover',
    },
    coverImageAlt: 'CSR Policy Cover',
    pdfFile: {
      url: '/reports/CSR_Policy_PISL_2e584fd56d.pdf',
      name: 'CSR_Policy_PISL_2e584fd56d.pdf',
      mime: 'application/pdf',
    },
    buttonName: 'CSR Policy',
    documentLabel: 'PDF Document',
    displayOrder: 3,
    accentType: 'green',
    updatedLabel: 'Last Updated: September 2026',
  },
];

const existingGov = extractPayload(mainJson, 'governance-page');
const governancePage =
  existingGov && typeof existingGov === 'object' && !Array.isArray(existingGov) && Object.keys(existingGov).length > 0
    ? existingGov
    : {
        documentId: 'wp_single_governance',
        internalName: 'Governance Page',
        pageTitle: 'Company Policy',
        slug: 'company-policy',
        pageDescription: 'Corporate policies that guide ethical, transparent and responsible growth across Pislinfra.',
        breadcrumbLabel: 'Company Policy',
        heroTitle: 'Company Policy',
        heroDescription: 'Corporate policies that guide ethical, transparent and responsible growth across Pislinfra.',
        heroCoverImage: {
          url: '/images/uploads/optimized_1787756480751_Extra-2.webp',
          alternativeText: 'Company Policy Hero',
        },
        heroImageAlt: 'Company Policy Hero',
        seo: {
          seoTitle: 'Company Policy | Governance | Pislinfra',
          seoDescription:
            'Corporate governance policies of Pragati Infra Solutions (Pislinfra) including Risk Management, Vigil Mechanism, and CSR Policy.',
        },
      };

const existingPolicies = extractPayload(mainJson, 'policy-document', 'policy-documents');
const policyDocs =
  Array.isArray(existingPolicies) && existingPolicies.length > 0 ? existingPolicies : defaultPolicies;

const pressRaw = extractPayload(mainJson, 'press-release', 'press-releases');
const pressReleases = Array.isArray(pressRaw) ? pressRaw : pressRaw ? [pressRaw] : [];

const collections = {
  blogs: dedupeByKey(extractPayload(mainJson, 'blogs') || [], (b) => b.slug || b.documentId || String(b.id)),
  'ongoing-projects': dedupeByKey(extractPayload(mainJson, 'ongoing-projects') || [], (p) => p.documentId || `${p.name}::${p.location}`),
  'completed-projects': dedupeByKey(extractPayload(mainJson, 'completed-projects') || [], (p) => p.documentId || `${p.name}::${p.location}`),
  'case-studies': dedupeByKey(extractPayload(mainJson, 'case-studies') || [], (c) => c.slug || c.documentId || c.title),
  awards: mergedAwards,
  clients: dedupeByKey(extractPayload(mainJson, 'clients') || [], (c) => c.documentId || c.name),
  'client-appreciations': dedupeByKey(extractPayload(mainJson, 'client-appreciations') || [], (c) => c.documentId || `${c.company}::${c.title}`),
  'coverage-markers': dedupeByKey(extractPayload(mainJson, 'coverage-markers') || [], (m) => m.documentId || `${m.projectName}::${m.city}`),
  'job-openings': dedupeByKey(extractPayload(mainJson, 'job-openings') || [], (j) => j.documentId || `${j.title}::${j.location}`),
  'solution-pages': solutionList,
  'policy-documents': policyDocs,
  milestones: extractPayload(mainJson, 'milestones') || [],
  'press-releases': pressReleases,
};

const singleTypeKeys = [
  'home-page',
  'about-page',
  'leadership-page',
  'ehs-page',
  'csr-page',
  'awards-page',
  'ongoing-page',
  'completed-page',
  'case-study-page',
  'warehouse-page',
  'blog-page',
  'career-page',
  'annual-report-page',
  'contact-page',
  'policy-page',
  'top-header',
  'header',
  'footer',
];

const singleTypes = {};
for (const key of singleTypeKeys) {
  const val = extractPayload(mainJson, key);
  if (val && typeof val === 'object' && !Array.isArray(val)) {
    singleTypes[key] = val;
  }
}
singleTypes['governance-page'] = governancePage;

// Collect all primary media references recursively so WordPress imports every image/PDF/video locally
const mediaRegistry = new Map(); // refKey -> { localPath, filename, refsCount }
const missingLocalMedia = new Map(); // url -> [context...]

function registerMediaRef(urlOrObj, contextPath) {
  if (!urlOrObj) return;
  let url = null;
  let alt = null;
  if (typeof urlOrObj === 'string') {
    if (!/\.(png|jpe?g|webp|avif|gif|svg|pdf|mp4|webm)(\?|$)/i.test(urlOrObj) && !urlOrObj.includes('/uploads/') && !urlOrObj.includes('/images/') && !urlOrObj.includes('/reports/') && !urlOrObj.includes('/videos/')) {
      return;
    }
    url = urlOrObj;
  } else if (typeof urlOrObj === 'object' && typeof urlOrObj.url === 'string') {
    url = urlOrObj.url;
    alt = urlOrObj.alternativeText || null;
  } else {
    return;
  }

  const localPath = resolveLocalFile(url);
  if (!localPath) {
    if (!missingLocalMedia.has(url)) missingLocalMedia.set(url, []);
    missingLocalMedia.get(url).push(contextPath);
    return;
  }

  const key = localPath;
  if (!mediaRegistry.has(key)) {
    mediaRegistry.set(key, {
      localPath,
      filename: path.basename(localPath),
      alternativeText: alt,
      sourceUrls: new Set([url]),
    });
  } else {
    const entry = mediaRegistry.get(key);
    entry.sourceUrls.add(url);
    if (!entry.alternativeText && alt) entry.alternativeText = alt;
  }
}

const URL_KEYS = new Set([
  'url',
  'logoUrl',
  'fileUrl',
  'coverImage',
  'heroCoverImage',
  'image',
  'featuredImage',
  'heroImage',
  'heroVideo',
  'pdfFile',
  'logo',
]);

function scanValue(val, ctx, keyName = '') {
  if (!val) return;
  if (typeof val === 'string') {
    if (
      URL_KEYS.has(keyName) ||
      keyName.endsWith('[]') ||
      /^(\/(images|reports|videos|uploads)\/|https?:\/\/[^\s]*r2\.dev\/)/i.test(val.trim())
    ) {
      registerMediaRef(val, ctx);
    } else if (val.includes('r2.dev/') || val.includes('/images/') || val.includes('/reports/') || val.includes('/videos/')) {
      // Also capture inline image/document URLs inside markdown or HTML content
      const inlineMatches = val.match(/(https?:\/\/[^\s\)"']+r2\.dev\/[^\s\)"']+|\/(?:images|reports|videos)\/[^\s\)"']+)/gi) || [];
      for (const m of inlineMatches) {
        registerMediaRef(m, `${ctx} (inline)`);
      }
    }
    return;
  }
  if (Array.isArray(val)) {
    const childKey = keyName ? `${keyName}[]` : '[]';
    val.forEach((v, i) => scanValue(v, `${ctx}[${i}]`, childKey));
    return;
  }
  if (typeof val === 'object') {
    if (typeof val.url === 'string') {
      registerMediaRef(val, ctx);
    }
    for (const [k, v] of Object.entries(val)) {
      if (k === 'formats') continue; // WordPress regenerates responsive sizes from the original
      scanValue(v, `${ctx}.${k}`, k);
    }
  }
}

scanValue(collections, 'collections');
scanValue(singleTypes, 'singleTypes');

// Also register essential static hero/company-profile assets used in fallback props if present locally
for (const extraRel of [
  '/reports/Pragati_Infra_Solutions_2d2730c93f.pdf',
  '/reports/HSE_POLICY_PISL_e35061d871.pdf',
  '/images/uploads/Screenshot_2026_10_13_at_10_58_08_PM_baafd337f0.png',
  '/images/uploads/pisl_EHS_100kb_1da8061955.jpeg',
]) {
  registerMediaRef(extraRel, 'staticFallback');
}

const mediaList = Array.from(mediaRegistry.values()).map((m) => ({
  localPath: m.localPath,
  filename: m.filename,
  alternativeText: m.alternativeText,
  sourceUrls: Array.from(m.sourceUrls),
}));

const counts = Object.fromEntries(Object.entries(collections).map(([k, v]) => [k, v.length]));
const summary = {
  generatedAt: new Date().toISOString(),
  source: 'LOCAL_ONLY (MainBackupPislinfra.json + snapshot-2026-11-12T05-18-09-631Z.json + PoliciesGovernance defaults + local disk)',
  collectionCounts: counts,
  singleTypeCount: Object.keys(singleTypes).length,
  localMediaCount: mediaList.length,
  missingLocalMedia: Array.from(missingLocalMedia.entries()).map(([url, refs]) => ({ url, refs })),
};

fs.writeFileSync(
  OUT_FILE,
  JSON.stringify({ summary, collections, singleTypes, media: mediaList }, null, 2),
  'utf8'
);

console.log(JSON.stringify(summary, null, 2));
