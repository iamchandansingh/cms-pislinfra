import type { Core } from '@strapi/strapi';
import { errors } from '@strapi/utils';
import { promises as fs } from 'fs';
import path from 'path';

const GOVERNANCE_PAGE = 'api::governance-page.governance-page';
const POLICY_DOCUMENT = 'api::policy-document.policy-document';

const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

// The page is served at /about/<slug>; these slugs already belong to other About pages
const RESERVED_ABOUT_SLUGS = ['leadership', 'ehs', 'awards', 'csr'];

const INITIAL_POLICIES = [
  {
    title: 'Risk Management Policy',
    slug: 'risk-management-policy',
    shortDescription: 'Our Risk Management Policy outlines our approach to identifying, assessing and mitigating risks to ensure business continuity, resilience and sustainable growth.',
    buttonName: 'Download PDF',
    documentLabel: 'PDF Document',
    displayOrder: 1,
    accentType: 'navy',
  },
  {
    title: 'Vigil Mechanism Policy',
    slug: 'vigil-mechanism-policy',
    shortDescription: 'Our Vigil Mechanism Policy provides a framework for reporting concerns and promotes a culture of ethics, transparency and accountability.',
    buttonName: 'Download PDF',
    documentLabel: 'PDF Document',
    displayOrder: 2,
    accentType: 'orange',
  },
  {
    title: 'CSR Policy',
    slug: 'csr-policy',
    shortDescription: 'Our CSR Policy reflects our commitment to social responsibility, community development and creating a positive, sustainable impact across the regions we operate in.',
    buttonName: 'Download PDF',
    documentLabel: 'PDF Document',
    displayOrder: 3,
    accentType: 'green',
  },
];

const extractMediaId = (value: any): number | null => {
  if (value === null || value === undefined) return null;
  if (Array.isArray(value)) return extractMediaId(value[0]);
  if (typeof value === 'number') return value;
  if (typeof value === 'string') return Number(value) || null;
  if (typeof value === 'object') {
    if ('id' in value) return extractMediaId(value.id);
    if ('set' in value) return extractMediaId(value.set);
    if ('connect' in value) return extractMediaId(value.connect);
  }
  return null;
};

// Reads the first bytes of the stored file. Returns null when the file cannot be reached.
const readFileSignature = async (strapi: Core.Strapi, url: string): Promise<string | null> => {
  try {
    if (url.startsWith('/')) {
      const handle = await fs.open(path.join(strapi.dirs.static.public, url), 'r');
      try {
        const buffer = Buffer.alloc(5);
        await handle.read(buffer, 0, 5, 0);
        return buffer.toString('latin1');
      } finally {
        await handle.close();
      }
    }
    const res = await fetch(url, { headers: { Range: 'bytes=0-4' }, signal: AbortSignal.timeout(8000) });
    if (!res.ok || !res.body) return null;
    const reader = res.body.getReader();
    const { value } = await reader.read();
    reader.cancel().catch(() => {});
    return value ? Buffer.from(value).subarray(0, 5).toString('latin1') : null;
  } catch {
    return null;
  }
};

const assertPdf = async (strapi: Core.Strapi, mediaValue: any) => {
  const fileId = extractMediaId(mediaValue);
  if (!fileId) return;

  const file: any = await strapi.db.query('plugin::upload.file').findOne({ where: { id: fileId } });
  if (!file) {
    throw new errors.ValidationError('The selected PDF file no longer exists in the Media Library.');
  }

  const ext = String(file.ext || '').toLowerCase();
  if (file.mime !== 'application/pdf' || ext !== '.pdf') {
    throw new errors.ValidationError(`"${file.name}" is not a PDF. Please upload a .pdf file for this policy.`);
  }

  const signature = await readFileSignature(strapi, file.url);
  if (signature === null) {
    strapi.log.warn(`[Policy Document] Could not read "${file.name}" to verify its PDF signature; accepted on MIME type and extension.`);
  } else if (signature !== '%PDF-') {
    throw new errors.ValidationError(`"${file.name}" has a .pdf name but is not a valid PDF file.`);
  }
};

const assertSlug = (slug: any, { reserved = [] as string[] } = {}) => {
  if (slug === undefined || slug === null) return;
  if (!SLUG_PATTERN.test(String(slug))) {
    throw new errors.ValidationError('Slug must use lowercase letters, numbers and single hyphens only (e.g. policies-governance).');
  }
  if (reserved.includes(String(slug))) {
    throw new errors.ValidationError(`Slug "${slug}" conflicts with an existing page at /about/${slug}.`);
  }
};

export const registerGovernanceValidation = (strapi: Core.Strapi) => {
  strapi.documents.use(async (context: any, next: any) => {
    if (!['create', 'update'].includes(context.action)) return next();
    const data = context.params?.data;
    if (!data || typeof data !== 'object') return next();

    if (context.uid === POLICY_DOCUMENT) {
      assertSlug(data.slug);
      if ('pdfFile' in data) await assertPdf(strapi, data.pdfFile);
    } else if (context.uid === GOVERNANCE_PAGE) {
      assertSlug(data.slug, { reserved: RESERVED_ABOUT_SLUGS });
    }
    return next();
  });
};

// Creates the initial page and the three policy entries once, as drafts.
// They stay unpublished until real cover images and PDFs are uploaded in the admin.
// A store flag prevents re-creating entries an editor later deletes.
export const seedGovernanceContent = async (strapi: Core.Strapi) => {
  const store = strapi.store({ type: 'plugin', name: 'pisl-governance' });
  if (await store.get({ key: 'seeded-v1' })) return;

  const existingPage = await strapi.documents(GOVERNANCE_PAGE).findFirst({ status: 'draft' });
  if (!existingPage) {
    const policyIds: string[] = [];
    for (const policy of INITIAL_POLICIES) {
      const existing = await strapi.documents(POLICY_DOCUMENT).findFirst({ status: 'draft', filters: { slug: policy.slug } });
      const doc = existing ?? (await strapi.documents(POLICY_DOCUMENT).create({ data: policy as any }));
      policyIds.push(doc.documentId);
    }

    await strapi.documents(GOVERNANCE_PAGE).create({
      data: {
        internalName: 'Policies & Governance Page',
        pageTitle: 'Policies & Governance',
        slug: 'policies-governance',
        breadcrumbLabel: 'Policies & Governance',
        heroTitle: 'Policies & Governance',
        heroDescription: 'Corporate policies that guide ethical, transparent and responsible growth.',
        policies: policyIds,
      } as any,
    });
    strapi.log.info('[Bootstrap] Seeded Policies & Governance page and initial policy documents as drafts.');
  }

  await store.set({ key: 'seeded-v1', value: true });
};
