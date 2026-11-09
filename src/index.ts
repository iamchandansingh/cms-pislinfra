import type { Core } from '@strapi/strapi';
import { exec } from 'child_process';
import path from 'path';
import { registerGovernanceValidation, seedGovernanceContent } from './api/governance-page/setup';

const LOCATIONS: Record<string, { cx: number; cy: number }> = {
  "Mundra": { cx: 5.0, cy: 49.0 },
  "Hazira, Surat": { cx: 16.0, cy: 54.0 },
  "Surat": { cx: 16.0, cy: 54.0 },
  "Hazira": { cx: 16.0, cy: 54.0 },
  "Jamnagar": { cx: 7.0, cy: 51.0 },
  "Dahej": { cx: 13.0, cy: 52.0 },
  "GIDC Sanand": { cx: 17.5, cy: 48.5 },
  "Sanand": { cx: 17.5, cy: 48.5 },
  "Ahmedabad": { cx: 18.0, cy: 48.5 },
  "Vadodara": { cx: 18.5, cy: 51.0 },
  "Rajkot": { cx: 12.0, cy: 50.0 },
  "Bharuch": { cx: 15.0, cy: 52.5 },
  "Mappedu, Chennai": { cx: 41.0, cy: 78.0 },
  "Mappedu": { cx: 41.0, cy: 78.0 },
  "Chennai": { cx: 41.0, cy: 78.0 },
  "Coimbatore": { cx: 30.5, cy: 85.5 },
  "Tuticorin": { cx: 36.0, cy: 90.5 },
  "Thoothukudi": { cx: 36.0, cy: 90.5 },
  "Hosur": { cx: 33.5, cy: 80.5 },
  "Najibabad": { cx: 36.0, cy: 29.0 },
  "Noida": { cx: 30.5, cy: 31.5 },
  "Greater Noida": { cx: 30.5, cy: 31.5 },
  "Lucknow": { cx: 43.0, cy: 36.0 },
  "Kanpur": { cx: 40.0, cy: 38.0 },
  "Dhanbad": { cx: 61.55, cy: 46.10 },
  "Ranchi": { cx: 58.0, cy: 48.0 },
  "Jamshedpur": { cx: 63.0, cy: 49.0 },
  "Pune": { cx: 20.0, cy: 63.0 },
  "Mumbai": { cx: 17.0, cy: 62.0 },
  "Nagpur": { cx: 39.0, cy: 53.0 },
  "Bangalore": { cx: 32.5, cy: 79.5 },
  "Bengaluru": { cx: 32.5, cy: 79.5 },
  "Hyderabad": { cx: 35.5, cy: 65.0 },
  "Ludhiana": { cx: 25.50, cy: 23.1 },
  "Neemrana": { cx: 28.0, cy: 32.5 },
  "Jaipur": { cx: 25.5, cy: 36.0 },
  "Bhiwadi": { cx: 28.5, cy: 31.8 },
  "Gurgaon": { cx: 28.5, cy: 30.5 },
  "Gurugram": { cx: 28.5, cy: 30.5 },
  "Faridabad": { cx: 29.5, cy: 31.0 },
  "Kolkata": { cx: 67.0, cy: 50.0 },
  "Indore": { cx: 25.0, cy: 49.0 },
  "Bhopal": { cx: 30.0, cy: 47.0 },
  "Visakhapatnam": { cx: 54.0, cy: 63.0 },
  "Dahej-02": { cx: 13.0, cy: 52.0 },
  "Pataudi": { cx: 28.2, cy: 30.2 },
  "Patli": { cx: 28.3, cy: 30.1 },
  "Tauru": { cx: 28.4, cy: 30.4 },
  "Farrukhnagar": { cx: 27.5, cy: 30.0 },
  "Farukhnagar": { cx: 27.5, cy: 30.0 },
  "Palwal": { cx: 29.8, cy: 31.2 },
  "Khijuri": { cx: 28.1, cy: 30.6 },
  "Luhari": { cx: 28.5, cy: 30.5 },
  "Pathreri": { cx: 28.3, cy: 30.3 },
  "Jamalpur": { cx: 28.2, cy: 30.4 },
  "Manesar": { cx: 28.6, cy: 30.4 },
  "Bilaspur": { cx: 28.4, cy: 30.6 },
  "Dharuhera": { cx: 28.2, cy: 30.8 },
  "Delhi-Mumbai Expressway, Nuh": { cx: 27.0, cy: 42.0 },
  "Nuh": { cx: 27.0, cy: 42.0 }
};

const STATE_FALLBACKS: Record<string, { cx: number; cy: number }> = {
  "Gujarat": { cx: 15.0, cy: 50.0 },
  "Haryana": { cx: 28.0, cy: 29.0 },
  "Tamil Nadu": { cx: 35.0, cy: 83.0 },
  "Uttar Pradesh": { cx: 40.0, cy: 34.0 },
  "Maharashtra": { cx: 21.0, cy: 60.0 },
  "Karnataka": { cx: 30.0, cy: 78.0 },
  "Rajasthan": { cx: 24.0, cy: 35.0 },
  "West Bengal": { cx: 65.0, cy: 49.0 },
  "Jharkhand": { cx: 60.0, cy: 47.0 },
  "Punjab": { cx: 25.0, cy: 23.0 },
  "Telangana": { cx: 36.0, cy: 65.0 },
  "Andhra Pradesh": { cx: 44.0, cy: 68.0 },
  "Madhya Pradesh": { cx: 28.0, cy: 48.0 },
  "Odisha": { cx: 58.0, cy: 56.0 }
};

const getLoc = (city: string, state: string) => {
  if (LOCATIONS[city]) return LOCATIONS[city];
  if (STATE_FALLBACKS[state]) return STATE_FALLBACKS[state];
  return { cx: 50, cy: 50 };
};

const allMarkers = [
  { name: "Adani Copper Smelter Project", city: "Mundra", state: "Gujarat", projectType: "Ongoing" },
  { name: "AMNS Steel Plant", city: "Hazira, Surat", state: "Gujarat", projectType: "Ongoing" },
  { name: "Reliance Solar Unit Manufacturing Plant", city: "Jamnagar", state: "Gujarat", projectType: "Ongoing" },
  { name: "VPW Logistics Park", city: "Mappedu, Chennai", state: "Tamil Nadu", projectType: "Ongoing" },
  { name: "CBG Plant", city: "Najibabad", state: "Uttar Pradesh", projectType: "Ongoing" },
  { name: "CPWD – IIT ISM Campus Project", city: "Dhanbad", state: "Jharkhand", projectType: "Ongoing" },
  { name: "Reliance DMD Plant", city: "Dahej", state: "Gujarat", projectType: "Ongoing" },
  { name: "AMNS SSC GIDC Project", city: "GIDC Sanand", state: "Gujarat", projectType: "Ongoing" },
  { name: "Sanaswadi Industrial Project", city: "Pune", state: "Maharashtra", projectType: "Ongoing" },
  { name: "PMG Logistics Park", city: "Bangalore", state: "Karnataka", projectType: "Ongoing" },
  { name: "HANNO ONE Logistics Park", city: "Coimbatore", state: "Tamil Nadu", projectType: "Ongoing" },
  { name: "Logistics Park LLP", city: "Ludhiana", state: "Punjab", projectType: "Ongoing" },
  { name: "PMG Logistics Park", city: "Hyderabad", state: "Telangana", projectType: "Ongoing" },
  { name: "PMG Logistics Park", city: "Neemrana", state: "Rajasthan", projectType: "Ongoing" },
  { name: "HIGHWAY HUMSAFAR", city: "Nuh", state: "Haryana", projectType: "Ongoing" },
  { name: "Vinyl Project at DMD, OSBL Area", city: "Dahej-02", state: "Gujarat", projectType: "Ongoing" },
  { name: "Welspun Factory", city: "Farrukhnagar", state: "Haryana", projectType: "Completed" },
  { name: "Logistics Park", city: "Luhari", state: "Haryana", projectType: "Completed" },
  { name: "Hyperscale Data Center", city: "Greater Noida", state: "Uttar Pradesh", projectType: "Completed" },
  { name: "TCIL Logistics Park", city: "Ludhiana", state: "Punjab", projectType: "Completed" },
  { name: "IndoSpace Logistics Park", city: "Farrukhnagar", state: "Haryana", projectType: "Completed" },
  { name: "Bhilosa Industries Plant", city: "Rakholi", state: "Dadra and Nagar Haveli", projectType: "Completed" },
  { name: "PepsiCo Manufacturing Unit", city: "Kosi Kalan", state: "Uttar Pradesh", projectType: "Completed" },
  { name: "Industrial Warehouse", city: "Bhiwandi", state: "Maharashtra", projectType: "Completed" },
  { name: "Warehousing Facility", city: "Hosur", state: "Tamil Nadu", projectType: "Completed" },
  { name: "Textile Unit", city: "Surat", state: "Gujarat", projectType: "Completed" },
  { name: "Chemical Processing Unit", city: "Bharuch", state: "Gujarat", projectType: "Completed" },
  { name: "Auto Components Factory", city: "Sanand", state: "Gujarat", projectType: "Completed" },
  { name: "Pharma Logistics Hub", city: "Hyderabad", state: "Telangana", projectType: "Completed" },
  { name: "FMCG Distribution Center", city: "Kolkata", state: "West Bengal", projectType: "Completed" },
  { name: "Heavy Machinery Plant", city: "Jamshedpur", state: "Jharkhand", projectType: "Completed" },
  { name: "Steel Processing Yard", city: "Nagpur", state: "Maharashtra", projectType: "Completed" },
  { name: "Solar Components Yard", city: "Rajkot", state: "Gujarat", projectType: "Completed" },
  { name: "E-commerce Fulfillment Center", city: "Bhiwadi", state: "Rajasthan", projectType: "Completed" },
  { name: "Cold Storage Facility", city: "Indore", state: "Madhya Pradesh", projectType: "Completed" },
  { name: "Agritech Processing Unit", city: "Bhopal", state: "Madhya Pradesh", projectType: "Completed" },
  { name: "Port Logistics Terminal", city: "Tuticorin", state: "Tamil Nadu", projectType: "Completed" },
  { name: "Industrial Park Sheds", city: "Visakhapatnam", state: "Andhra Pradesh", projectType: "Completed" },
  { name: "CPWD ISM Infrastructure Phase-1", city: "Dhanbad", state: "Jharkhand", projectType: "Completed" },
  { name: "Engineering Workshop", city: "Ranchi", state: "Jharkhand", projectType: "Completed" },
  { name: "Commercial Warehousing Hub", city: "Lucknow", state: "Uttar Pradesh", projectType: "Completed" }
];

export default {
  register({ strapi }: { strapi: Core.Strapi }) {
    strapi.customFields.register({
      name: "project-selector",
      type: "string",
    });
    strapi.customFields.register({
      name: "move-to-completed",
      type: "string",
    });
    registerGovernanceValidation(strapi);
  },

  async bootstrap({ strapi }: { strapi: Core.Strapi }) {
    try {
      const roleService = strapi.plugin('users-permissions').service('role');
      const publicRole = await roleService.find({ name: 'Public' });
      if (publicRole && publicRole.length > 0) {
        await roleService.updateRole(publicRole[0].id, {
          permissions: {
            'api::about-page': { controllers: { 'about-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::annual-report-page': { controllers: { 'annual-report-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::award': { controllers: { award: { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::awards-page': { controllers: { 'awards-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::blog': { controllers: { blog: { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::blog-page': { controllers: { 'blog-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::career-page': { controllers: { 'career-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::case-study': { controllers: { 'case-study': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::case-study-page': { controllers: { 'case-study-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::client': { controllers: { client: { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::client-appreciation': { controllers: { 'client-appreciation': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::completed-page': { controllers: { 'completed-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::completed-project': { controllers: { 'completed-project': { find: { enabled: true }, findOne: { enabled: true }, create: { enabled: true }, update: { enabled: true }, delete: { enabled: true } } } },
            'api::contact-page': { controllers: { 'contact-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::csr-page': { controllers: { 'csr-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::ehs-page': { controllers: { 'ehs-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::footer': { controllers: { footer: { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::header': { controllers: { header: { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::home-page': { controllers: { 'home-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::job-opening': { controllers: { 'job-opening': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::leadership-page': { controllers: { 'leadership-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::ongoing-page': { controllers: { 'ongoing-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::ongoing-project': { controllers: { 'ongoing-project': { find: { enabled: true }, findOne: { enabled: true }, create: { enabled: true }, update: { enabled: true }, delete: { enabled: true }, moveToCompleted: { enabled: true } } } },
            'api::policy-page': { controllers: { 'policy-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::governance-page': { controllers: { 'governance-page': { find: { enabled: true } } } },
            'api::policy-document': { controllers: { 'policy-document': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::solution-page': { controllers: { 'solution-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::top-header': { controllers: { 'top-header': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::warehouse-page': { controllers: { 'warehouse-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::coverage-marker': { controllers: { 'coverage-marker': { find: { enabled: true }, findOne: { enabled: true }, create: { enabled: true }, update: { enabled: true }, delete: { enabled: true } } } }
          }
        });
        console.log('Public permissions set for all API endpoints.');
    try {
      const homeDoc = await strapi.documents("api::home-page.home-page").findFirst();
      if (homeDoc) {
        const patch: any = {};
        if (!homeDoc.aboutTitle) patch.aboutTitle = "About Our Expertise";
        if (!homeDoc.aboutSubtitle) patch.aboutSubtitle = "Building Global-Standard Industrial & Logistics Parks.";
        if (!homeDoc.capabilitiesTitle) patch.capabilitiesTitle = "Our Strategic Capabilities.";
        if (!homeDoc.stat1Value) patch.stat1Value = "25+";
        if (!homeDoc.stat1Label) patch.stat1Label = "Locations Covered";
        if (!homeDoc.stat2Value) patch.stat2Value = "16+";
        if (!homeDoc.stat2Label) patch.stat2Label = "Million Sq. Ft. Constructed";
        if (!homeDoc.stat3Value) patch.stat3Value = "600+";
        if (!homeDoc.stat3Label) patch.stat3Label = "Employee Strength";
        if (!homeDoc.stat4Value) patch.stat4Value = "10+";
        if (!homeDoc.stat4Label) patch.stat4Label = "Upcoming Projects";
        if (!homeDoc.legacyYears) patch.legacyYears = "17";
        if (!homeDoc.statsDesc) patch.statsDesc = "When you choose PISL, you gain a partner dedicated to understanding your business needs to support your growth — because your success is our relentless pursuit.";
        if (!homeDoc.hseTitle) patch.hseTitle = "Health, Safety & Environment";
        if (!homeDoc.primaryKeyword) patch.primaryKeyword = "Industrial Infrastructure & EPC Company India";
        if (!homeDoc.metaTitle) patch.metaTitle = "Pislinfra | Industrial Infrastructure & EPC Company India";
        if (!homeDoc.metaDescription) patch.metaDescription = "Pislinfra delivers warehousing, logistics parks, and industrial construction solutions, with 17+ years of experience and 16M+ sq. ft. delivered.";
        if (!homeDoc.focusKeywords) patch.focusKeywords = "industrial infrastructure, warehousing construction, logistics park, PEB buildings, turnkey EPC India";
        
        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::home-page.home-page").update({
            documentId: homeDoc.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default home-page sections and SEO fields in CMS:", Object.keys(patch));
        }
      }
    } catch (homeErr) {
      console.warn("[Bootstrap] Home-page seed note:", homeErr);
    }

    try {
      const aboutDoc: any = await strapi.documents("api::about-page.about-page").findFirst({
        populate: { seo: true }
      });
      if (aboutDoc) {
        const patch: any = {};
        if (!aboutDoc.primaryKeyword) patch.primaryKeyword = "Industrial & Warehouse EPC Contractor India";
        if (!aboutDoc.metaTitle) patch.metaTitle = aboutDoc.seo?.seoTitle || "About Pislinfra - India's Premier Industrial & Warehouse EPC Contractor";
        if (!aboutDoc.metaDescription) patch.metaDescription = aboutDoc.seo?.seoDescription || "Pragati Infra Solutions Pvt. Ltd. (Pislinfra) is a leading industrial infrastructure and EPC company with 16M+ sq. ft delivered across India. Specializing in warehouses, logistics parks, and PEB engineering.";
        if (!aboutDoc.focusKeywords) patch.focusKeywords = "industrial infrastructure, warehouse construction company, turnkey EPC contractor, PEB engineering";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::about-page.about-page").update({
            documentId: aboutDoc.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default about-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] About-page SEO fields already present.");
        }
      } else {
        console.warn("[Bootstrap] About-page document not found.");
      }
    } catch (aboutErr) {
      console.warn("[Bootstrap] About-page seed note:", aboutErr);
    }

    try {
      const leadDoc: any = await strapi.documents("api::leadership-page.leadership-page").findFirst({
        populate: { seo: true }
      });
      if (leadDoc) {
        const patch: any = {};
        if (!leadDoc.primaryKeyword) patch.primaryKeyword = "Industrial Construction & Infrastructure Leaders India";
        if (!leadDoc.metaTitle) patch.metaTitle = leadDoc.seo?.seoTitle || "Leadership Team | Pragati Infra Solutions (Pislinfra)";
        if (!leadDoc.metaDescription) patch.metaDescription = leadDoc.seo?.seoDescription || "Meet the executive leadership and board of directors at Pislinfra driving innovation, safety, and turnkey industrial EPC infrastructure across India.";
        if (!leadDoc.focusKeywords) patch.focusKeywords = "Pislinfra leadership, board of directors, management team, industrial infrastructure leaders India, civil engineering executives, Shubhendra Mittal";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::leadership-page.leadership-page").update({
            documentId: leadDoc.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default leadership-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Leadership-page SEO fields already present.");
        }
      } else {
        console.warn("[Bootstrap] Leadership-page document not found.");
      }
    } catch (leadErr) {
      console.warn("[Bootstrap] Leadership-page seed note:", leadErr);
    }

    try {
      const ehsDoc: any = await strapi.documents("api::ehs-page.ehs-page").findFirst({
        populate: { seo: true }
      });
      if (ehsDoc) {
        const patch: any = {};
        if (!ehsDoc.primaryKeyword) patch.primaryKeyword = "EHS Safety Standards & Zero-Harm Construction India";
        if (!ehsDoc.metaTitle) patch.metaTitle = ehsDoc.seo?.seoTitle || "EHS Safety Protocols & Zero-Accident Construction Standards | Pislinfra";
        if (!ehsDoc.metaDescription) patch.metaDescription = ehsDoc.seo?.seoDescription || "Pislinfra is committed to world-class Environment, Health & Safety (EHS) standards with over 15+ million safe man-hours, ISO certifications, and zero-LTI safety protocols on construction sites.";
        if (!ehsDoc.focusKeywords) patch.focusKeywords = "EHS policy construction, safety standards industrial construction, ISO 45001 contractor India, zero harm safety culture, safe man hours construction, Pislinfra EHS";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::ehs-page.ehs-page").update({
            documentId: ehsDoc.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default ehs-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] EHS-page SEO fields already present.");
        }
      } else {
        console.warn("[Bootstrap] EHS-page document not found.");
      }
    } catch (ehsErr) {
      console.warn("[Bootstrap] EHS-page seed note:", ehsErr);
    }

    try {
      const awardsDoc: any = await strapi.documents("api::awards-page.awards-page").findFirst({
        populate: { seo: true }
      });
      if (awardsDoc) {
        const patch: any = {};
        if (!awardsDoc.primaryKeyword) patch.primaryKeyword = "Industrial Construction Awards & Certifications India";
        if (!awardsDoc.metaTitle) patch.metaTitle = awardsDoc.seo?.seoTitle || "Awards & Certifications - Safety Milestones & Industry Honors | Pislinfra";
        if (!awardsDoc.metaDescription) patch.metaDescription = awardsDoc.seo?.seoDescription || "Explore Pislinfra prestigious awards, US LEED Gold certification, and zero-LTI safety milestones awarded by Adani, Reliance, and leading industry councils.";
        if (!awardsDoc.focusKeywords) patch.focusKeywords = "Pislinfra awards, construction awards India, safety excellence awards, Adani best safety contractor, LEED Gold warehousing, industrial EPC honors, turnkey infrastructure accolades";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::awards-page.awards-page").update({
            documentId: awardsDoc.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default awards-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Awards-page SEO fields already present.");
        }
      } else {
        console.warn("[Bootstrap] Awards-page document not found.");
      }
    } catch (awardsErr) {
      console.warn("[Bootstrap] Awards-page seed note:", awardsErr);
    }

    try {
      // CSR page uses Draft & Publish: seed the draft, then publish only if the draft had no pending edits
      const csrDraft: any = await strapi.documents("api::csr-page.csr-page").findFirst({
        status: 'draft',
        populate: { seo: true }
      });
      if (csrDraft) {
        const patch: any = {};
        if (!csrDraft.primaryKeyword) patch.primaryKeyword = "CSR Initiatives & Community Development India";
        if (!csrDraft.metaTitle) patch.metaTitle = csrDraft.seo?.seoTitle || "Corporate Social Responsibility (CSR) Initiatives | Pislinfra";
        if (!csrDraft.metaDescription) patch.metaDescription = csrDraft.seo?.seoDescription || "Discover Pislinfra's Corporate Social Responsibility (CSR) programs empowering local communities through education, healthcare, skill development, and environmental greening across India.";
        if (!csrDraft.focusKeywords) patch.focusKeywords = csrDraft.seo?.seoKeywords || "Pislinfra CSR, corporate social responsibility construction, community empowerment India, sustainable development";

        if (Object.keys(patch).length > 0) {
          const csrPublished: any = await strapi.documents("api::csr-page.csr-page").findFirst({ status: 'published' });
          const draftInSync = csrPublished?.publishedAt && new Date(csrDraft.updatedAt) <= new Date(csrPublished.publishedAt);
          await strapi.documents("api::csr-page.csr-page").update({
            documentId: csrDraft.documentId,
            data: patch
          });
          if (draftInSync) {
            await strapi.documents("api::csr-page.csr-page").publish({ documentId: csrDraft.documentId });
          }
          console.log("[Bootstrap] Seeded default csr-page SEO fields in CMS:", Object.keys(patch), draftInSync ? "(published)" : "(draft only)");
        } else {
          console.log("[Bootstrap] CSR-page SEO fields already present.");
        }
      } else {
        console.warn("[Bootstrap] CSR-page document not found.");
      }
    } catch (csrErr) {
      console.warn("[Bootstrap] CSR-page seed note:", csrErr);
    }

    try {
      const ongoingDraft: any = await strapi.documents("api::ongoing-page.ongoing-page").findFirst({
        status: 'draft',
        populate: { seo: true }
      });
      if (ongoingDraft) {
        const patch: any = {};
        if (!ongoingDraft.primaryKeyword) patch.primaryKeyword = "Ongoing Construction Projects";
        if (!ongoingDraft.metaTitle) patch.metaTitle = ongoingDraft.seo?.seoTitle || "Ongoing Projects | Pislinfra– Building India’s Future";
        if (!ongoingDraft.metaDescription) patch.metaDescription = ongoingDraft.seo?.seoDescription || "Explore Pislinfra's ongoing projects across industrial, infrastructure, warehousing and logistics sectors, delivering quality construction solutions across India.";
        if (!ongoingDraft.focusKeywords) patch.focusKeywords = ongoingDraft.seo?.seoKeywords || "Ongoing Construction Projects, industrial infrastructure India, warehousing construction, PEB manufacturing plants";

        if (Object.keys(patch).length > 0) {
          const ongoingPublished: any = await strapi.documents("api::ongoing-page.ongoing-page").findFirst({ status: 'published' });
          const draftInSync = ongoingPublished?.publishedAt && new Date(ongoingDraft.updatedAt) <= new Date(ongoingPublished.publishedAt);
          await strapi.documents("api::ongoing-page.ongoing-page").update({
            documentId: ongoingDraft.documentId,
            data: patch
          });
          if (draftInSync) {
            await strapi.documents("api::ongoing-page.ongoing-page").publish({ documentId: ongoingDraft.documentId });
          }
          console.log("[Bootstrap] Seeded default ongoing-page SEO fields in CMS:", Object.keys(patch), draftInSync ? "(published)" : "(draft only)");
        } else {
          console.log("[Bootstrap] Ongoing-page SEO fields already present.");
        }
      } else {
        console.warn("[Bootstrap] Ongoing-page document not found.");
      }
    } catch (ongoingErr) {
      console.warn("[Bootstrap] Ongoing-page seed note:", ongoingErr);
    }

    try {
      const completedDraft: any = await strapi.documents("api::completed-page.completed-page").findFirst({
        status: 'draft',
        populate: { seo: true }
      });
      if (completedDraft) {
        const patch: any = {};
        if (!completedDraft.primaryKeyword) patch.primaryKeyword = "Completed Construction Projects";
        if (!completedDraft.metaTitle) patch.metaTitle = completedDraft.seo?.seoTitle || "Completed Projects & Project Portfolio | Pislinfra";
        if (!completedDraft.metaDescription) patch.metaDescription = completedDraft.seo?.seoDescription || "Explore Pislinfra's completed projects across industrial, warehousing and infrastructure sectors, showcasing quality, safety and successful project delivery.";
        if (!completedDraft.focusKeywords) patch.focusKeywords = completedDraft.seo?.seoKeywords || "Completed Construction Projects, industrial warehousing projects, completed infrastructure India, turnkey project delivery";

        if (Object.keys(patch).length > 0) {
          const completedPublished: any = await strapi.documents("api::completed-page.completed-page").findFirst({ status: 'published' });
          const draftInSync = completedPublished?.publishedAt && new Date(completedDraft.updatedAt) <= new Date(completedPublished.publishedAt);
          await strapi.documents("api::completed-page.completed-page").update({
            documentId: completedDraft.documentId,
            data: patch
          });
          if (draftInSync) {
            await strapi.documents("api::completed-page.completed-page").publish({ documentId: completedDraft.documentId });
          }
          console.log("[Bootstrap] Seeded default completed-page SEO fields in CMS:", Object.keys(patch), draftInSync ? "(published)" : "(draft only)");
        } else {
          console.log("[Bootstrap] Completed-page SEO fields already present.");
        }
      } else {
        console.warn("[Bootstrap] Completed-page document not found.");
      }
    } catch (completedErr) {
      console.warn("[Bootstrap] Completed-page seed note:", completedErr);
    }

    try {
      const caseStudyDraft: any = await strapi.documents("api::case-study-page.case-study-page").findFirst({
        status: 'draft',
        populate: { seo: true }
      });
      if (caseStudyDraft) {
        const patch: any = {};
        if (!caseStudyDraft.primaryKeyword) patch.primaryKeyword = "Construction Project Case Studies";
        if (!caseStudyDraft.metaTitle) patch.metaTitle = caseStudyDraft.seo?.seoTitle || "Project Case Studies & Insights | Pislinfra";
        if (!caseStudyDraft.metaDescription) patch.metaDescription = caseStudyDraft.seo?.seoDescription || "Explore Pislinfra's project case studies, highlighting our expertise, execution capabilities, innovative solutions and successful delivery across key sectors.";
        if (!caseStudyDraft.focusKeywords) patch.focusKeywords = caseStudyDraft.seo?.seoKeywords || "Construction Project Case Studies, industrial EPC case studies, warehouse project insights, infrastructure execution India";

        if (Object.keys(patch).length > 0) {
          const caseStudyPublished: any = await strapi.documents("api::case-study-page.case-study-page").findFirst({ status: 'published' });
          const draftInSync = caseStudyPublished?.publishedAt && new Date(caseStudyDraft.updatedAt) <= new Date(caseStudyPublished.publishedAt);
          await strapi.documents("api::case-study-page.case-study-page").update({
            documentId: caseStudyDraft.documentId,
            data: patch
          });
          if (draftInSync) {
            await strapi.documents("api::case-study-page.case-study-page").publish({ documentId: caseStudyDraft.documentId });
          }
          console.log("[Bootstrap] Seeded default case-study-page SEO fields in CMS:", Object.keys(patch), draftInSync ? "(published)" : "(draft only)");
        } else {
          console.log("[Bootstrap] Case-study-page SEO fields already present.");
        }
      } else {
        console.warn("[Bootstrap] Case-study-page document not found.");
      }
    } catch (caseStudyErr) {
      console.warn("[Bootstrap] Case-study-page seed note:", caseStudyErr);
    }

    try {
      const solutionEntries: any = await strapi.documents("api::solution-page.solution-page").findMany({
        filters: { slug: 'solutions' },
        populate: { seo: true }
      });
      const solutionOverview = solutionEntries && solutionEntries[0];
      const patch: any = {
        slug: 'solutions',
        title: 'Construction & Infrastructure Solutions',
        heroSubtitle: 'Comprehensive infrastructure solutions tailored to your needs',
        breadcrumb: 'Solutions',
        primaryKeyword: 'Construction & Infrastructure Solutions',
        metaTitle: 'Construction & Infrastructure Solutions | Pislinfra',
        metaDescription: "Explore Pislinfra's comprehensive construction and infrastructure solutions, combining technical expertise, quality execution and innovative approaches.",
        focusKeywords: 'Construction & Infrastructure Solutions, turnkey industrial EPC, warehouse construction, infrastructure solutions India'
      };

      if (!solutionOverview) {
        await strapi.documents("api::solution-page.solution-page").create({
          data: patch
        });
        console.log("[Bootstrap] Created default solutions-page SEO entry in CMS.");
      } else {
        const updateData: any = {};
        if (!solutionOverview.primaryKeyword) updateData.primaryKeyword = patch.primaryKeyword;
        if (!solutionOverview.metaTitle) updateData.metaTitle = patch.metaTitle;
        if (!solutionOverview.metaDescription) updateData.metaDescription = patch.metaDescription;
        if (!solutionOverview.focusKeywords) updateData.focusKeywords = patch.focusKeywords;
        if (Object.keys(updateData).length > 0) {
          await strapi.documents("api::solution-page.solution-page").update({
            documentId: solutionOverview.documentId,
            data: updateData
          });
          console.log("[Bootstrap] Seeded default solutions-page SEO fields in CMS:", Object.keys(updateData));
        } else {
          console.log("[Bootstrap] Solutions-page SEO fields already present.");
        }
      }
    } catch (solErr) {
      console.warn("[Bootstrap] Solutions-page seed note:", solErr);
    }

    try {
      const industrialEntries: any = await strapi.documents("api::solution-page.solution-page").findMany({
        filters: { slug: 'industrial' },
        populate: { seo: true }
      });
      const industrialEntry = industrialEntries && industrialEntries[0];
      if (industrialEntry) {
        const patch: any = {};
        if (!industrialEntry.primaryKeyword) patch.primaryKeyword = "Industrial Development";
        if (!industrialEntry.metaTitle) patch.metaTitle = industrialEntry.seo?.seoTitle || "Industrial Development Company In India | Pislinfra";
        if (!industrialEntry.metaDescription) patch.metaDescription = industrialEntry.seo?.seoDescription || "Discover Pislinfra's industrial construction solutions, delivering reliable, efficient and quality-driven infrastructure for diverse industrial requirements across India.";
        if (!industrialEntry.focusKeywords) patch.focusKeywords = industrialEntry.seo?.seoKeywords || "Industrial Development, industrial construction, turnkey EPC contractor India, factory building construction, heavy steel PEB fabrication, manufacturing infrastructure, Pislinfra";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::solution-page.solution-page").update({
            documentId: industrialEntry.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default industrial solution-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Industrial solution-page SEO fields already present.");
        }
      }
    } catch (indErr) {
      console.warn("[Bootstrap] Industrial solution-page seed note:", indErr);
    }

    try {
      const infraEntries: any = await strapi.documents("api::solution-page.solution-page").findMany({
        filters: { slug: 'infrastructure' },
        populate: { seo: true }
      });
      const infraEntry = infraEntries && infraEntries[0];
      if (infraEntry) {
        const patch: any = {};
        if (!infraEntry.primaryKeyword) patch.primaryKeyword = "Infrastructure Development";
        if (!infraEntry.metaTitle) patch.metaTitle = infraEntry.seo?.seoTitle || "Infrastructure Development Company | Pislinfra";
        if (!infraEntry.metaDescription) patch.metaDescription = infraEntry.seo?.seoDescription || "Explore Pislinfra’s infrastructure construction solutions, combining engineering expertise, quality execution and reliable project delivery for complex developments.";
        if (!infraEntry.focusKeywords) patch.focusKeywords = infraEntry.seo?.seoKeywords || "Infrastructure Development, infrastructure construction solutions, civil infrastructure, engineering expertise, complex developments India, Pislinfra";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::solution-page.solution-page").update({
            documentId: infraEntry.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default infrastructure solution-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Infrastructure solution-page SEO fields already present.");
        }
      }
    } catch (infraErr) {
      console.warn("[Bootstrap] Infrastructure solution-page seed note:", infraErr);
    }

    try {
      const logisticEntries: any = await strapi.documents("api::solution-page.solution-page").findMany({
        filters: { slug: 'logistic' },
        populate: { seo: true }
      });
      const logisticEntry = logisticEntries && logisticEntries[0];
      if (logisticEntry) {
        const patch: any = {};
        if (!logisticEntry.primaryKeyword) patch.primaryKeyword = "Logistics Park Development";
        if (!logisticEntry.metaTitle) patch.metaTitle = logisticEntry.seo?.seoTitle || "Logistics Park Development Company in India | Pislinfra";
        if (!logisticEntry.metaDescription) patch.metaDescription = logisticEntry.seo?.seoDescription || "Pislinfra's develops Grade-A logistics parks across India with integrated design, procurement and construction solutions for modern supply chains.";
        if (!logisticEntry.focusKeywords) patch.focusKeywords = logisticEntry.seo?.seoKeywords || "Logistics Park Development, Grade-A logistics parks, warehousing and logistics solutions, supply chain infrastructure India, Pislinfra";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::solution-page.solution-page").update({
            documentId: logisticEntry.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default logistic solution-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Logistic solution-page SEO fields already present.");
        }
      }
    } catch (logisticErr) {
      console.warn("[Bootstrap] Logistic solution-page seed note:", logisticErr);
    }

    try {
      const warehouseEntries: any = await strapi.documents("api::solution-page.solution-page").findMany({
        filters: { slug: 'warehouse' },
        populate: { seo: true }
      });
      const warehouseEntry = warehouseEntries && warehouseEntries[0];
      if (warehouseEntry) {
        const patch: any = {};
        if (!warehouseEntry.primaryKeyword) patch.primaryKeyword = "Warehouse Construction Company";
        if (!warehouseEntry.metaTitle) patch.metaTitle = warehouseEntry.seo?.seoTitle || "Warehouse Construction Company in India | Pislinfra";
        if (!warehouseEntry.metaDescription) patch.metaDescription = warehouseEntry.seo?.seoDescription || "Pislinfra's delivers design-build warehouse construction solutions across India, creating efficient, scalable and future-ready facilities for businesses.";
        if (!warehouseEntry.focusKeywords) patch.focusKeywords = warehouseEntry.seo?.seoKeywords || "Warehouse Construction Company, design-build warehouse construction, warehouse solutions India, future-ready facilities, Pislinfra";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::solution-page.solution-page").update({
            documentId: warehouseEntry.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default warehouse solution-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Warehouse solution-page SEO fields already present.");
        }
      }
    } catch (warehouseErr) {
      console.warn("[Bootstrap] Warehouse solution-page seed note:", warehouseErr);
    }

    try {
      const blogDoc: any = await strapi.documents("api::blog-page.blog-page").findFirst({
        populate: { seo: true }
      });
      if (blogDoc) {
        const patch: any = {};
        if (!blogDoc.primaryKeyword) patch.primaryKeyword = "Construction & Infrastructure Insights";
        if (!blogDoc.metaTitle) patch.metaTitle = blogDoc.seo?.seoTitle || "Construction & Infrastructure Insights | Pislinfra";
        if (!blogDoc.metaDescription) patch.metaDescription = blogDoc.seo?.seoDescription || "Explore Pislinfra's latest insights on construction, infrastructure, engineering, project management, industry trends and innovative solutions.";
        if (!blogDoc.focusKeywords) patch.focusKeywords = blogDoc.seo?.seoKeywords || "Construction & Infrastructure Insights, infrastructure blog, construction insights, warehouse design, industrial development, logistics, project management, Pislinfra";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::blog-page.blog-page").update({
            documentId: blogDoc.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default blog-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Blog-page SEO fields already present.");
        }
      }
    } catch (blogPageErr) {
      console.warn("[Bootstrap] Blog-page seed note:", blogPageErr);
    }

    try {
      const careerDoc: any = await strapi.documents("api::career-page.career-page").findFirst({
        populate: { seo: true }
      });
      if (careerDoc) {
        const patch: any = {};
        if (!careerDoc.primaryKeyword) patch.primaryKeyword = "Careers at Pislinfra";
        if (!careerDoc.metaTitle) patch.metaTitle = careerDoc.seo?.seoTitle || "Careers at Pislinfra | Build Your Future With Us";
        if (!careerDoc.metaDescription) patch.metaDescription = careerDoc.seo?.seoDescription || "Explore career opportunities at Pislinfra and join a team committed to excellence, innovation, safety and building the future of infrastructure.";
        if (!careerDoc.focusKeywords) patch.focusKeywords = careerDoc.seo?.seoKeywords || "Careers at Pislinfra, construction jobs India, civil engineering careers, infrastructure job openings, hiring, Pislinfra careers";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::career-page.career-page").update({
            documentId: careerDoc.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default career-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Career-page SEO fields already present.");
        }
      }
    } catch (careerPageErr) {
      console.warn("[Bootstrap] Career-page seed note:", careerPageErr);
    }

    try {
      const contactDoc: any = await strapi.documents("api::contact-page.contact-page").findFirst({
        populate: { seo: true }
      });
      if (contactDoc) {
        const patch: any = {};
        if (!contactDoc.primaryKeyword) patch.primaryKeyword = "Contact Pislinfra";
        if (!contactDoc.metaTitle) patch.metaTitle = contactDoc.seo?.seoTitle || "Contact Pislinfra | Enquiries & Business Support";
        if (!contactDoc.metaDescription) patch.metaDescription = contactDoc.seo?.seoDescription || "Get in touch with Pislinfra for project enquiries, business partnerships and construction solutions. Connect with our team for expert assistance.";
        if (!contactDoc.focusKeywords) patch.focusKeywords = contactDoc.seo?.seoKeywords || "Contact Pislinfra, project enquiries, business partnerships, construction solutions, infrastructure assistance, Pislinfra office";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::contact-page.contact-page").update({
            documentId: contactDoc.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default contact-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Contact-page SEO fields already present.");
        }
      }
    } catch (contactPageErr) {
      console.warn("[Bootstrap] Contact-page seed note:", contactPageErr);
    }

    try {
      const reportDoc: any = await strapi.documents("api::annual-report-page.annual-report-page").findFirst({
        populate: { seo: true }
      });
      if (reportDoc) {
        const patch: any = {};
        if (!reportDoc.primaryKeyword) patch.primaryKeyword = "Pislinfra Annual Reports";
        if (!reportDoc.metaTitle) patch.metaTitle = reportDoc.seo?.seoTitle || "Pislinfra Annual Reports | Company Reports & Updates";
        if (!reportDoc.metaDescription) patch.metaDescription = reportDoc.seo?.seoDescription || "Explore Pislinfra annual reports, company information and key business updates covering multiple financial years.";
        if (!reportDoc.focusKeywords) patch.focusKeywords = reportDoc.seo?.seoKeywords || "Pislinfra Annual Reports, company reports, annual returns, statutory disclosures, financial year performance, Pislinfra";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::annual-report-page.annual-report-page").update({
            documentId: reportDoc.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default annual-report-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Annual-report-page SEO fields already present.");
        }
      }
    } catch (annualReportErr) {
      console.warn("[Bootstrap] Annual-report-page seed note:", annualReportErr);
    }

    try {
      const policyDoc: any = await strapi.documents("api::policy-page.policy-page").findFirst({
        populate: { seo: true }
      });
      if (policyDoc) {
        const patch: any = {};
        if (!policyDoc.primaryKeyword) patch.primaryKeyword = "Privacy Policy";
        if (!policyDoc.metaTitle) patch.metaTitle = policyDoc.seo?.seoTitle || "Privacy Policy | Pislinfra";
        if (!policyDoc.metaDescription) patch.metaDescription = policyDoc.seo?.seoDescription || "Read Pislinfra’s Privacy Policy to understand how we collect, use, protect and manage personal information when you use our website and services.";
        if (!policyDoc.focusKeywords) patch.focusKeywords = policyDoc.seo?.seoKeywords || "Privacy Policy, Pislinfra, data protection, personal information, cookies and tracking, privacy terms";

        if (Object.keys(patch).length > 0) {
          await strapi.documents("api::policy-page.policy-page").update({
            documentId: policyDoc.documentId,
            data: patch
          });
          console.log("[Bootstrap] Seeded default policy-page SEO fields in CMS:", Object.keys(patch));
        } else {
          console.log("[Bootstrap] Policy-page SEO fields already present.");
        }
      }
    } catch (policyPageErr) {
      console.warn("[Bootstrap] Policy-page seed note:", policyPageErr);
    }

      }
    } catch (err) {
      console.error('Error in bootstrap:', err);
    }

    try {
      await seedGovernanceContent(strapi);
    } catch (governanceErr) {
      strapi.log.warn('[Bootstrap] Policies & Governance seed note:', governanceErr);
    }

    // Automatically inject resourcesCoverImage into Strapi Content Manager Edit View layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let config: any = await cmStore.get({ key: 'configuration_content_types::api::ehs-page.ehs-page' });
      if (config && config.layouts && Array.isArray(config.layouts.edit)) {
        let hasField = false;
        for (const row of config.layouts.edit) {
          if (Array.isArray(row) && row.some((col: any) => col.name === 'resourcesCoverImage')) {
            hasField = true;
            break;
          }
        }
        if (!hasField) {
          let insertIdx = -1;
          for (let i = 0; i < config.layouts.edit.length; i++) {
            const row = config.layouts.edit[i];
            if (Array.isArray(row) && row.some((col: any) => col.name === 'resourcesSectionDesc' || col.name === 'resources')) {
              insertIdx = i;
              break;
            }
          }
          const newCol = [{ name: 'resourcesCoverImage', size: 12 }];
          if (insertIdx !== -1) {
            config.layouts.edit.splice(insertIdx + 1, 0, newCol);
          } else {
            config.layouts.edit.push(newCol);
          }
          if (!config.metadatas) config.metadatas = {};
          config.metadatas.resourcesCoverImage = {
            edit: {
              label: '12.4 Resources Section Cover Image',
              description: 'Cover image displayed on the left of Downloadable Resources',
              placeholder: '',
              visible: true,
              editable: true
            },
            list: {
              label: 'Resources Cover Image',
              searchable: false,
              sortable: false
            }
          };
          if (!config.metadatas.resources) config.metadatas.resources = {};
          config.metadatas.resources.edit = {
            label: '12.5 Downloadable Policies (4)',
            description: 'Corporate policies with View & Download PDF actions',
            placeholder: '',
            visible: true,
            editable: true
          };
          await cmStore.set({ key: 'configuration_content_types::api::ehs-page.ehs-page', value: config });
          strapi.log.info('[Bootstrap] Successfully added resourcesCoverImage to Content Manager layout via strapi.store!');
        }
      }
    } catch (cmStoreErr) {
      strapi.log.warn('[Bootstrap] strapi.store config update note:', cmStoreErr);
    }

    try {
      const tableName = (await strapi.db.connection.schema.hasTable('strapi_core_store_settings'))
        ? 'strapi_core_store_settings'
        : (await strapi.db.connection.schema.hasTable('core_store')) ? 'core_store' : null;
      if (tableName) {
        const rows = await strapi.db.connection(tableName).whereILike('key', '%content_manager%ehs-page%');
        for (const row of rows) {
          let val = typeof row.value === 'string' ? JSON.parse(row.value) : row.value;
          if (val && val.layouts && Array.isArray(val.layouts.edit)) {
            let hasField = false;
            for (const r of val.layouts.edit) {
              if (Array.isArray(r) && r.some((c: any) => c.name === 'resourcesCoverImage')) {
                hasField = true; break;
              }
            }
            if (!hasField) {
              let insertIdx = -1;
              for (let i = 0; i < val.layouts.edit.length; i++) {
                const r = val.layouts.edit[i];
                if (Array.isArray(r) && r.some((c: any) => c.name === 'resourcesSectionDesc' || c.name === 'resources')) {
                  insertIdx = i; break;
                }
              }
              const newCol = [{ name: 'resourcesCoverImage', size: 12 }];
              if (insertIdx !== -1) {
                val.layouts.edit.splice(insertIdx + 1, 0, newCol);
              } else {
                val.layouts.edit.push(newCol);
              }
              if (!val.metadatas) val.metadatas = {};
              val.metadatas.resourcesCoverImage = {
                edit: {
                  label: '12.4 Resources Section Cover Image',
                  description: 'Cover image displayed on the left of Downloadable Resources',
                  placeholder: '',
                  visible: true,
                  editable: true
                },
                list: {
                  label: 'Resources Cover Image',
                  searchable: false,
                  sortable: false
                }
              };
              if (!val.metadatas.resources) val.metadatas.resources = {};
              val.metadatas.resources.edit = {
                label: '12.5 Downloadable Policies (4)',
                description: 'Corporate policies with View & Download PDF actions',
                placeholder: '',
                visible: true,
                editable: true
              };
              await strapi.db.connection(tableName).where('id', row.id).update({ value: JSON.stringify(val) });
              strapi.log.info(`[Bootstrap] Updated ${tableName} layout for ehs-page!`);
            }
          }
        }
      }
    } catch (e) {
      strapi.log.warn('[Bootstrap] Layout table update note:', e);
    }

    // Automatically configure Homepage SEO fields in Strapi Content Manager layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let homeCmConfig: any = await cmStore.get({ key: 'configuration_content_types::api::home-page.home-page' });
      if (homeCmConfig) {
        if (!homeCmConfig.metadatas) homeCmConfig.metadatas = {};
        homeCmConfig.metadatas.primaryKeyword = {
          edit: {
            label: 'Primary Keyword',
            description: 'The main target search phrase for the homepage.',
            placeholder: 'e.g. Industrial Infrastructure & EPC Company India',
            visible: true,
            editable: true
          },
          list: { label: 'Primary Keyword', searchable: true, sortable: true }
        };
        homeCmConfig.metadatas.metaTitle = {
          edit: {
            label: 'Meta Title',
            description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).',
            placeholder: 'e.g. Pislinfra | Industrial Infrastructure & EPC Company India',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Title', searchable: true, sortable: true }
        };
        homeCmConfig.metadatas.metaDescription = {
          edit: {
            label: 'Meta Description',
            description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).',
            placeholder: 'e.g. Pislinfra delivers warehousing, logistics parks, and industrial construction solutions...',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Description', searchable: false, sortable: false }
        };
        homeCmConfig.metadatas.focusKeywords = {
          edit: {
            label: 'Focus Keywords',
            description: 'Optional additional related keywords for editorial SEO management.',
            placeholder: 'e.g. industrial infrastructure, warehousing, logistics park, PEB buildings',
            visible: true,
            editable: true
          },
          list: { label: 'Focus Keywords', searchable: false, sortable: false }
        };

        if (Array.isArray(homeCmConfig.layouts?.edit)) {
          const editRows = homeCmConfig.layouts.edit;
          const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
          if (!hasField('primaryKeyword')) {
            editRows.push([{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }]);
          }
          if (!hasField('metaTitle')) {
            editRows.push([{ name: 'metaTitle', size: 12 }]);
          }
          if (!hasField('metaDescription')) {
            editRows.push([{ name: 'metaDescription', size: 12 }]);
          }
        }
        await cmStore.set({ key: 'configuration_content_types::api::home-page.home-page', value: homeCmConfig });
        strapi.log.info('[Bootstrap] Successfully configured homepage SEO fields in Content Manager via cmStore!');
      }

      // Also ensure direct database records in core store are aligned
      const tableName = (await strapi.db.connection.schema.hasTable('strapi_core_store_settings'))
        ? 'strapi_core_store_settings'
        : (await strapi.db.connection.schema.hasTable('core_store')) ? 'core_store' : null;
      if (tableName) {
        const rows = await strapi.db.connection(tableName).whereILike('key', '%content_manager%home-page%');
        for (const row of rows) {
          let val = typeof row.value === 'string' ? JSON.parse(row.value) : row.value;
          if (val) {
            if (!val.metadatas) val.metadatas = {};
            val.metadatas.primaryKeyword = {
              edit: { label: 'Primary Keyword', description: 'The main target search phrase for the homepage.', placeholder: '', visible: true, editable: true },
              list: { label: 'Primary Keyword' }
            };
            val.metadatas.metaTitle = {
              edit: { label: 'Meta Title', description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Title' }
            };
            val.metadatas.metaDescription = {
              edit: { label: 'Meta Description', description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Description' }
            };
            val.metadatas.focusKeywords = {
              edit: { label: 'Focus Keywords', description: 'Optional additional related keywords for editorial SEO management.', placeholder: '', visible: true, editable: true },
              list: { label: 'Focus Keywords' }
            };

            if (Array.isArray(val.layouts?.edit)) {
              const editRows = val.layouts.edit;
              const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
              if (!hasField('primaryKeyword')) {
                editRows.splice(1, 0,
                  [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
                  [{ name: 'metaTitle', size: 12 }],
                  [{ name: 'metaDescription', size: 12 }]
                );
              }
            }
            await strapi.db.connection(tableName).where('id', row.id).update({ value: JSON.stringify(val) });
          }
        }
      }
    } catch (homeCmErr) {
      strapi.log.warn('[Bootstrap] Home-page layout config note:', homeCmErr);
    }

    // Automatically configure About Page SEO fields in Strapi Content Manager layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let aboutCmConfig: any = await cmStore.get({ key: 'configuration_content_types::api::about-page.about-page' });
      if (aboutCmConfig) {
        if (!aboutCmConfig.metadatas) aboutCmConfig.metadatas = {};
        aboutCmConfig.metadatas.primaryKeyword = {
          edit: {
            label: 'Primary Keyword',
            description: 'The main target search phrase for the About page.',
            placeholder: 'e.g. Industrial & Warehouse EPC Contractor India',
            visible: true,
            editable: true
          },
          list: { label: 'Primary Keyword', searchable: true, sortable: true }
        };
        aboutCmConfig.metadatas.metaTitle = {
          edit: {
            label: 'Meta Title',
            description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).',
            placeholder: "e.g. About Pislinfra - India's Premier Industrial & Warehouse EPC Contractor",
            visible: true,
            editable: true
          },
          list: { label: 'Meta Title', searchable: true, sortable: true }
        };
        aboutCmConfig.metadatas.metaDescription = {
          edit: {
            label: 'Meta Description',
            description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).',
            placeholder: 'e.g. Pragati Infra Solutions Pvt. Ltd. (Pislinfra) is a leading industrial infrastructure and EPC company...',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Description', searchable: false, sortable: false }
        };
        aboutCmConfig.metadatas.focusKeywords = {
          edit: {
            label: 'Focus Keywords',
            description: 'Optional additional related keywords for editorial SEO management.',
            placeholder: 'e.g. industrial infrastructure, warehouse construction company, turnkey EPC contractor',
            visible: true,
            editable: true
          },
          list: { label: 'Focus Keywords', searchable: false, sortable: false }
        };

        if (Array.isArray(aboutCmConfig.layouts?.edit)) {
          const editRows = aboutCmConfig.layouts.edit;
          const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
          if (!hasField('primaryKeyword')) {
            editRows.splice(1, 0,
              [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
              [{ name: 'metaTitle', size: 12 }],
              [{ name: 'metaDescription', size: 12 }]
            );
          }
        }
        await cmStore.set({ key: 'configuration_content_types::api::about-page.about-page', value: aboutCmConfig });
        strapi.log.info('[Bootstrap] Successfully configured about-page SEO fields in Content Manager via cmStore!');
      }

      const tableName = (await strapi.db.connection.schema.hasTable('strapi_core_store_settings'))
        ? 'strapi_core_store_settings'
        : (await strapi.db.connection.schema.hasTable('core_store')) ? 'core_store' : null;
      if (tableName) {
        const rows = await strapi.db.connection(tableName).whereILike('key', '%content_manager%about-page%');
        for (const row of rows) {
          let val = typeof row.value === 'string' ? JSON.parse(row.value) : row.value;
          if (val) {
            if (!val.metadatas) val.metadatas = {};
            val.metadatas.primaryKeyword = {
              edit: { label: 'Primary Keyword', description: 'The main target search phrase for the About page.', placeholder: '', visible: true, editable: true },
              list: { label: 'Primary Keyword' }
            };
            val.metadatas.metaTitle = {
              edit: { label: 'Meta Title', description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Title' }
            };
            val.metadatas.metaDescription = {
              edit: { label: 'Meta Description', description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Description' }
            };
            val.metadatas.focusKeywords = {
              edit: { label: 'Focus Keywords', description: 'Optional additional related keywords for editorial SEO management.', placeholder: '', visible: true, editable: true },
              list: { label: 'Focus Keywords' }
            };

            if (Array.isArray(val.layouts?.edit)) {
              const editRows = val.layouts.edit;
              const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
              if (!hasField('primaryKeyword')) {
                editRows.splice(1, 0,
                  [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
                  [{ name: 'metaTitle', size: 12 }],
                  [{ name: 'metaDescription', size: 12 }]
                );
              }
            }
            await strapi.db.connection(tableName).where('id', row.id).update({ value: JSON.stringify(val) });
          }
        }
      }
    } catch (aboutCmErr) {
      strapi.log.warn('[Bootstrap] About-page layout config note:', aboutCmErr);
    }

    // Automatically configure Leadership Page SEO fields in Strapi Content Manager layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let leadCmConfig: any = await cmStore.get({ key: 'configuration_content_types::api::leadership-page.leadership-page' });
      if (leadCmConfig) {
        if (!leadCmConfig.metadatas) leadCmConfig.metadatas = {};
        leadCmConfig.metadatas.primaryKeyword = {
          edit: {
            label: 'Primary Keyword',
            description: 'The main target search phrase for the Leadership page.',
            placeholder: 'e.g. Industrial Construction & Infrastructure Leaders India',
            visible: true,
            editable: true
          },
          list: { label: 'Primary Keyword', searchable: true, sortable: true }
        };
        leadCmConfig.metadatas.metaTitle = {
          edit: {
            label: 'Meta Title',
            description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).',
            placeholder: 'e.g. Leadership Team | Pragati Infra Solutions (Pislinfra)',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Title', searchable: true, sortable: true }
        };
        leadCmConfig.metadatas.metaDescription = {
          edit: {
            label: 'Meta Description',
            description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).',
            placeholder: 'e.g. Meet the executive leadership and board of directors at Pislinfra...',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Description', searchable: false, sortable: false }
        };
        leadCmConfig.metadatas.focusKeywords = {
          edit: {
            label: 'Focus Keywords',
            description: 'Optional additional related keywords for editorial SEO management.',
            placeholder: 'e.g. Pislinfra leadership, board of directors, management team',
            visible: true,
            editable: true
          },
          list: { label: 'Focus Keywords', searchable: false, sortable: false }
        };

        if (Array.isArray(leadCmConfig.layouts?.edit)) {
          const editRows = leadCmConfig.layouts.edit;
          const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
          if (!hasField('primaryKeyword')) {
            editRows.splice(1, 0,
              [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
              [{ name: 'metaTitle', size: 12 }],
              [{ name: 'metaDescription', size: 12 }]
            );
          }
        }
        await cmStore.set({ key: 'configuration_content_types::api::leadership-page.leadership-page', value: leadCmConfig });
        strapi.log.info('[Bootstrap] Successfully configured leadership-page SEO fields in Content Manager via cmStore!');
      }

      const tableName = (await strapi.db.connection.schema.hasTable('strapi_core_store_settings'))
        ? 'strapi_core_store_settings'
        : (await strapi.db.connection.schema.hasTable('core_store')) ? 'core_store' : null;
      if (tableName) {
        const rows = await strapi.db.connection(tableName).whereILike('key', '%content_manager%leadership-page%');
        for (const row of rows) {
          let val = typeof row.value === 'string' ? JSON.parse(row.value) : row.value;
          if (val) {
            if (!val.metadatas) val.metadatas = {};
            val.metadatas.primaryKeyword = {
              edit: { label: 'Primary Keyword', description: 'The main target search phrase for the Leadership page.', placeholder: '', visible: true, editable: true },
              list: { label: 'Primary Keyword' }
            };
            val.metadatas.metaTitle = {
              edit: { label: 'Meta Title', description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Title' }
            };
            val.metadatas.metaDescription = {
              edit: { label: 'Meta Description', description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Description' }
            };
            val.metadatas.focusKeywords = {
              edit: { label: 'Focus Keywords', description: 'Optional additional related keywords for editorial SEO management.', placeholder: '', visible: true, editable: true },
              list: { label: 'Focus Keywords' }
            };

            if (Array.isArray(val.layouts?.edit)) {
              const editRows = val.layouts.edit;
              const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
              if (!hasField('primaryKeyword')) {
                editRows.splice(1, 0,
                  [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
                  [{ name: 'metaTitle', size: 12 }],
                  [{ name: 'metaDescription', size: 12 }]
                );
              }
            }
            await strapi.db.connection(tableName).where('id', row.id).update({ value: JSON.stringify(val) });
          }
        }
      }
    } catch (leadCmErr) {
      strapi.log.warn('[Bootstrap] Leadership-page layout config note:', leadCmErr);
    }

    // Automatically configure EHS Page SEO fields in Strapi Content Manager layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let ehsCmConfig: any = await cmStore.get({ key: 'configuration_content_types::api::ehs-page.ehs-page' });
      if (ehsCmConfig) {
        if (!ehsCmConfig.metadatas) ehsCmConfig.metadatas = {};
        ehsCmConfig.metadatas.primaryKeyword = {
          edit: {
            label: 'Primary Keyword',
            description: 'The main target search phrase for the EHS page.',
            placeholder: 'e.g. EHS Safety Standards & Zero-Harm Construction India',
            visible: true,
            editable: true
          },
          list: { label: 'Primary Keyword', searchable: true, sortable: true }
        };
        ehsCmConfig.metadatas.metaTitle = {
          edit: {
            label: 'Meta Title',
            description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).',
            placeholder: 'e.g. EHS Safety Protocols & Zero-Accident Construction Standards | Pislinfra',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Title', searchable: true, sortable: true }
        };
        ehsCmConfig.metadatas.metaDescription = {
          edit: {
            label: 'Meta Description',
            description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).',
            placeholder: 'e.g. Pislinfra is committed to world-class Environment, Health & Safety (EHS) standards...',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Description', searchable: false, sortable: false }
        };
        ehsCmConfig.metadatas.focusKeywords = {
          edit: {
            label: 'Focus Keywords',
            description: 'Optional additional related keywords for editorial SEO management.',
            placeholder: 'e.g. EHS policy construction, safety standards industrial construction',
            visible: true,
            editable: true
          },
          list: { label: 'Focus Keywords', searchable: false, sortable: false }
        };

        if (Array.isArray(ehsCmConfig.layouts?.edit)) {
          const editRows = ehsCmConfig.layouts.edit;
          const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
          if (!hasField('primaryKeyword')) {
            editRows.splice(2, 0,
              [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
              [{ name: 'metaTitle', size: 12 }],
              [{ name: 'metaDescription', size: 12 }]
            );
          }
        }
        await cmStore.set({ key: 'configuration_content_types::api::ehs-page.ehs-page', value: ehsCmConfig });
        strapi.log.info('[Bootstrap] Successfully configured ehs-page SEO fields in Content Manager via cmStore!');
      }

      const tableName = (await strapi.db.connection.schema.hasTable('strapi_core_store_settings'))
        ? 'strapi_core_store_settings'
        : (await strapi.db.connection.schema.hasTable('core_store')) ? 'core_store' : null;
      if (tableName) {
        const rows = await strapi.db.connection(tableName).whereILike('key', '%content_manager%ehs-page%');
        for (const row of rows) {
          let val = typeof row.value === 'string' ? JSON.parse(row.value) : row.value;
          if (val) {
            if (!val.metadatas) val.metadatas = {};
            val.metadatas.primaryKeyword = {
              edit: { label: 'Primary Keyword', description: 'The main target search phrase for the EHS page.', placeholder: '', visible: true, editable: true },
              list: { label: 'Primary Keyword' }
            };
            val.metadatas.metaTitle = {
              edit: { label: 'Meta Title', description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Title' }
            };
            val.metadatas.metaDescription = {
              edit: { label: 'Meta Description', description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Description' }
            };
            val.metadatas.focusKeywords = {
              edit: { label: 'Focus Keywords', description: 'Optional additional related keywords for editorial SEO management.', placeholder: '', visible: true, editable: true },
              list: { label: 'Focus Keywords' }
            };

            if (Array.isArray(val.layouts?.edit)) {
              const editRows = val.layouts.edit;
              const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
              if (!hasField('primaryKeyword')) {
                editRows.splice(2, 0,
                  [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
                  [{ name: 'metaTitle', size: 12 }],
                  [{ name: 'metaDescription', size: 12 }]
                );
              }
            }
            await strapi.db.connection(tableName).where('id', row.id).update({ value: JSON.stringify(val) });
          }
        }
      }
    } catch (ehsCmErr) {
      strapi.log.warn('[Bootstrap] EHS-page layout config note:', ehsCmErr);
    }

    // Automatically configure CSR Page SEO fields in Strapi Content Manager layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let csrCmConfig: any = await cmStore.get({ key: 'configuration_content_types::api::csr-page.csr-page' });
      if (csrCmConfig) {
        if (!csrCmConfig.metadatas) csrCmConfig.metadatas = {};
        csrCmConfig.metadatas.primaryKeyword = {
          edit: {
            label: 'Primary Keyword',
            description: 'The main target search phrase for the CSR page.',
            placeholder: 'e.g. CSR Initiatives & Community Development India',
            visible: true,
            editable: true
          },
          list: { label: 'Primary Keyword', searchable: true, sortable: true }
        };
        csrCmConfig.metadatas.metaTitle = {
          edit: {
            label: 'Meta Title',
            description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).',
            placeholder: 'e.g. CSR Initiatives & Community Development | PISL Infra',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Title', searchable: true, sortable: true }
        };
        csrCmConfig.metadatas.metaDescription = {
          edit: {
            label: 'Meta Description',
            description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).',
            placeholder: "e.g. Discover PISL Infra's CSR initiatives focused on community development...",
            visible: true,
            editable: true
          },
          list: { label: 'Meta Description', searchable: false, sortable: false }
        };
        csrCmConfig.metadatas.focusKeywords = {
          edit: {
            label: 'Focus Keywords',
            description: 'Optional additional related keywords for editorial SEO management.',
            placeholder: 'e.g. CSR initiatives, corporate social responsibility, community development',
            visible: true,
            editable: true
          },
          list: { label: 'Focus Keywords', searchable: false, sortable: false }
        };

        if (Array.isArray(csrCmConfig.layouts?.edit)) {
          const editRows = csrCmConfig.layouts.edit;
          const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
          if (!hasField('primaryKeyword')) {
            editRows.splice(1, 0,
              [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
              [{ name: 'metaTitle', size: 12 }],
              [{ name: 'metaDescription', size: 12 }]
            );
          }
        }
        await cmStore.set({ key: 'configuration_content_types::api::csr-page.csr-page', value: csrCmConfig });
        strapi.log.info('[Bootstrap] Successfully configured csr-page SEO fields in Content Manager via cmStore!');
      }
    } catch (csrCmErr) {
      strapi.log.warn('[Bootstrap] CSR-page layout config note:', csrCmErr);
    }

    // Automatically configure Awards Page SEO fields in Strapi Content Manager layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let awardsCmConfig: any = await cmStore.get({ key: 'configuration_content_types::api::awards-page.awards-page' });
      if (awardsCmConfig) {
        if (!awardsCmConfig.metadatas) awardsCmConfig.metadatas = {};
        awardsCmConfig.metadatas.primaryKeyword = {
          edit: {
            label: 'Primary Keyword',
            description: 'The main target search phrase for the Awards page.',
            placeholder: 'e.g. Industrial Construction Awards & Certifications India',
            visible: true,
            editable: true
          },
          list: { label: 'Primary Keyword', searchable: true, sortable: true }
        };
        awardsCmConfig.metadatas.metaTitle = {
          edit: {
            label: 'Meta Title',
            description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).',
            placeholder: 'e.g. Awards & Certifications - Safety Milestones & Industry Honors | Pislinfra',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Title', searchable: true, sortable: true }
        };
        awardsCmConfig.metadatas.metaDescription = {
          edit: {
            label: 'Meta Description',
            description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).',
            placeholder: 'e.g. Explore Pislinfra prestigious awards, US LEED Gold certification, and zero-LTI safety milestones...',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Description', searchable: false, sortable: false }
        };
        awardsCmConfig.metadatas.focusKeywords = {
          edit: {
            label: 'Focus Keywords',
            description: 'Optional additional related keywords for editorial SEO management.',
            placeholder: 'e.g. Pislinfra awards, construction awards India, safety excellence awards, LEED Gold warehousing',
            visible: true,
            editable: true
          },
          list: { label: 'Focus Keywords', searchable: false, sortable: false }
        };

        if (Array.isArray(awardsCmConfig.layouts?.edit)) {
          const editRows = awardsCmConfig.layouts.edit;
          const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
          if (!hasField('primaryKeyword')) {
            editRows.splice(2, 0,
              [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
              [{ name: 'metaTitle', size: 12 }],
              [{ name: 'metaDescription', size: 12 }]
            );
          }
        }
        await cmStore.set({ key: 'configuration_content_types::api::awards-page.awards-page', value: awardsCmConfig });
        strapi.log.info('[Bootstrap] Successfully configured awards-page SEO fields in Content Manager via cmStore!');
      }

      const tableName = (await strapi.db.connection.schema.hasTable('strapi_core_store_settings'))
        ? 'strapi_core_store_settings'
        : (await strapi.db.connection.schema.hasTable('core_store')) ? 'core_store' : null;
      if (tableName) {
        const rows = await strapi.db.connection(tableName).whereILike('key', '%content_manager%awards-page%');
        for (const row of rows) {
          let val = typeof row.value === 'string' ? JSON.parse(row.value) : row.value;
          if (val) {
            if (!val.metadatas) val.metadatas = {};
            val.metadatas.primaryKeyword = {
              edit: { label: 'Primary Keyword', description: 'The main target search phrase for the Awards page.', placeholder: '', visible: true, editable: true },
              list: { label: 'Primary Keyword' }
            };
            val.metadatas.metaTitle = {
              edit: { label: 'Meta Title', description: 'The SEO title displayed in search results when selected by Google (recommended: 50-60 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Title' }
            };
            val.metadatas.metaDescription = {
              edit: { label: 'Meta Description', description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Description' }
            };
            val.metadatas.focusKeywords = {
              edit: { label: 'Focus Keywords', description: 'Optional additional related keywords for editorial SEO management.', placeholder: '', visible: true, editable: true },
              list: { label: 'Focus Keywords' }
            };

            if (Array.isArray(val.layouts?.edit)) {
              const editRows = val.layouts.edit;
              const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
              if (!hasField('primaryKeyword')) {
                editRows.splice(2, 0,
                  [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
                  [{ name: 'metaTitle', size: 12 }],
                  [{ name: 'metaDescription', size: 12 }]
                );
              }
            }
            await strapi.db.connection(tableName).where('id', row.id).update({ value: JSON.stringify(val) });
          }
        }
      }
    } catch (awardsCmErr) {
      strapi.log.warn('[Bootstrap] Awards-page layout config note:', awardsCmErr);
    }

    // Automatically configure Ongoing Page SEO fields in Strapi Content Manager layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let ongoingCmConfig: any = await cmStore.get({ key: 'configuration_content_types::api::ongoing-page.ongoing-page' });
      if (ongoingCmConfig) {
        if (!ongoingCmConfig.metadatas) ongoingCmConfig.metadatas = {};
        ongoingCmConfig.metadatas.primaryKeyword = {
          edit: {
            label: 'Primary Keyword',
            description: 'The main target search phrase for the Ongoing Projects page.',
            placeholder: 'e.g. Ongoing Construction Projects',
            visible: true,
            editable: true
          },
          list: { label: 'Primary Keyword', searchable: true, sortable: true }
        };
        ongoingCmConfig.metadatas.metaTitle = {
          edit: {
            label: 'Meta Title',
            description: 'The SEO title displayed in search results (recommended: 50-60 chars).',
            placeholder: 'e.g. Ongoing Projects | Pislinfra– Building India’s Future',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Title', searchable: true, sortable: true }
        };
        ongoingCmConfig.metadatas.metaDescription = {
          edit: {
            label: 'Meta Description',
            description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).',
            placeholder: 'e.g. Explore Pislinfra\'s ongoing projects across industrial, infrastructure...',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Description', searchable: false, sortable: false }
        };
        ongoingCmConfig.metadatas.focusKeywords = {
          edit: {
            label: 'Focus Keywords',
            description: 'Optional additional related keywords for editorial SEO management.',
            placeholder: 'e.g. Ongoing Construction Projects, industrial infrastructure India, warehousing construction',
            visible: true,
            editable: true
          },
          list: { label: 'Focus Keywords', searchable: false, sortable: false }
        };

        if (Array.isArray(ongoingCmConfig.layouts?.edit)) {
          const editRows = ongoingCmConfig.layouts.edit;
          const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
          if (!hasField('primaryKeyword')) {
            editRows.splice(2, 0,
              [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
              [{ name: 'metaTitle', size: 12 }],
              [{ name: 'metaDescription', size: 12 }]
            );
          }
        }
        await cmStore.set({ key: 'configuration_content_types::api::ongoing-page.ongoing-page', value: ongoingCmConfig });
        strapi.log.info('[Bootstrap] Successfully configured ongoing-page SEO fields in Content Manager via cmStore!');
      }

      const tableName = (await strapi.db.connection.schema.hasTable('strapi_core_store_settings'))
        ? 'strapi_core_store_settings'
        : (await strapi.db.connection.schema.hasTable('core_store')) ? 'core_store' : null;
      if (tableName) {
        const rows = await strapi.db.connection(tableName).whereILike('key', '%content_manager%ongoing-page%');
        for (const row of rows) {
          let val = typeof row.value === 'string' ? JSON.parse(row.value) : row.value;
          if (val) {
            if (!val.metadatas) val.metadatas = {};
            val.metadatas.primaryKeyword = {
              edit: { label: 'Primary Keyword', description: 'The main target search phrase for the Ongoing Projects page.', placeholder: '', visible: true, editable: true },
              list: { label: 'Primary Keyword' }
            };
            val.metadatas.metaTitle = {
              edit: { label: 'Meta Title', description: 'The SEO title displayed in search results (recommended: 50-60 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Title' }
            };
            val.metadatas.metaDescription = {
              edit: { label: 'Meta Description', description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Description' }
            };
            val.metadatas.focusKeywords = {
              edit: { label: 'Focus Keywords', description: 'Optional additional related keywords for editorial SEO management.', placeholder: '', visible: true, editable: true },
              list: { label: 'Focus Keywords' }
            };

            if (Array.isArray(val.layouts?.edit)) {
              const editRows = val.layouts.edit;
              const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
              if (!hasField('primaryKeyword')) {
                editRows.splice(2, 0,
                  [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
                  [{ name: 'metaTitle', size: 12 }],
                  [{ name: 'metaDescription', size: 12 }]
                );
              }
            }
            await strapi.db.connection(tableName).where('id', row.id).update({ value: JSON.stringify(val) });
          }
        }
      }
    } catch (ongoingCmErr) {
      strapi.log.warn('[Bootstrap] Ongoing-page layout config note:', ongoingCmErr);
    }

    // Automatically configure Completed Page SEO fields in Strapi Content Manager layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let completedCmConfig: any = await cmStore.get({ key: 'configuration_content_types::api::completed-page.completed-page' });
      if (completedCmConfig) {
        if (!completedCmConfig.metadatas) completedCmConfig.metadatas = {};
        completedCmConfig.metadatas.primaryKeyword = {
          edit: {
            label: 'Primary Keyword',
            description: 'The main target search phrase for the Completed Projects page.',
            placeholder: 'e.g. Completed Construction Projects',
            visible: true,
            editable: true
          },
          list: { label: 'Primary Keyword', searchable: true, sortable: true }
        };
        completedCmConfig.metadatas.metaTitle = {
          edit: {
            label: 'Meta Title',
            description: 'The SEO title displayed in search results (recommended: 50-60 chars).',
            placeholder: 'e.g. Completed Projects & Project Portfolio | Pislinfra',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Title', searchable: true, sortable: true }
        };
        completedCmConfig.metadatas.metaDescription = {
          edit: {
            label: 'Meta Description',
            description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).',
            placeholder: 'e.g. Explore Pislinfra\'s completed projects across industrial, warehousing...',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Description', searchable: false, sortable: false }
        };
        completedCmConfig.metadatas.focusKeywords = {
          edit: {
            label: 'Focus Keywords',
            description: 'Optional additional related keywords for editorial SEO management.',
            placeholder: 'e.g. Completed Construction Projects, industrial warehousing projects',
            visible: true,
            editable: true
          },
          list: { label: 'Focus Keywords', searchable: false, sortable: false }
        };

        if (Array.isArray(completedCmConfig.layouts?.edit)) {
          const editRows = completedCmConfig.layouts.edit;
          const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
          if (!hasField('primaryKeyword')) {
            editRows.splice(2, 0,
              [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
              [{ name: 'metaTitle', size: 12 }],
              [{ name: 'metaDescription', size: 12 }]
            );
          }
        }
        await cmStore.set({ key: 'configuration_content_types::api::completed-page.completed-page', value: completedCmConfig });
        strapi.log.info('[Bootstrap] Successfully configured completed-page SEO fields in Content Manager via cmStore!');
      }

      const tableName = (await strapi.db.connection.schema.hasTable('strapi_core_store_settings'))
        ? 'strapi_core_store_settings'
        : (await strapi.db.connection.schema.hasTable('core_store')) ? 'core_store' : null;
      if (tableName) {
        const rows = await strapi.db.connection(tableName).whereILike('key', '%content_manager%completed-page%');
        for (const row of rows) {
          let val = typeof row.value === 'string' ? JSON.parse(row.value) : row.value;
          if (val) {
            if (!val.metadatas) val.metadatas = {};
            val.metadatas.primaryKeyword = {
              edit: { label: 'Primary Keyword', description: 'The main target search phrase for the Completed Projects page.', placeholder: '', visible: true, editable: true },
              list: { label: 'Primary Keyword' }
            };
            val.metadatas.metaTitle = {
              edit: { label: 'Meta Title', description: 'The SEO title displayed in search results (recommended: 50-60 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Title' }
            };
            val.metadatas.metaDescription = {
              edit: { label: 'Meta Description', description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Description' }
            };
            val.metadatas.focusKeywords = {
              edit: { label: 'Focus Keywords', description: 'Optional additional related keywords for editorial SEO management.', placeholder: '', visible: true, editable: true },
              list: { label: 'Focus Keywords' }
            };

            if (Array.isArray(val.layouts?.edit)) {
              const editRows = val.layouts.edit;
              const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
              if (!hasField('primaryKeyword')) {
                editRows.splice(2, 0,
                  [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
                  [{ name: 'metaTitle', size: 12 }],
                  [{ name: 'metaDescription', size: 12 }]
                );
              }
            }
            await strapi.db.connection(tableName).where('id', row.id).update({ value: JSON.stringify(val) });
          }
        }
      }
    } catch (completedCmErr) {
      strapi.log.warn('[Bootstrap] Completed-page layout config note:', completedCmErr);
    }

    // Automatically configure Case Study Page SEO fields in Strapi Content Manager layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let caseStudyCmConfig: any = await cmStore.get({ key: 'configuration_content_types::api::case-study-page.case-study-page' });
      if (caseStudyCmConfig) {
        if (!caseStudyCmConfig.metadatas) caseStudyCmConfig.metadatas = {};
        caseStudyCmConfig.metadatas.primaryKeyword = {
          edit: {
            label: 'Primary Keyword',
            description: 'The main target search phrase for the Case Studies page.',
            placeholder: 'e.g. Construction Project Case Studies',
            visible: true,
            editable: true
          },
          list: { label: 'Primary Keyword', searchable: true, sortable: true }
        };
        caseStudyCmConfig.metadatas.metaTitle = {
          edit: {
            label: 'Meta Title',
            description: 'The SEO title displayed in search results (recommended: 50-60 chars).',
            placeholder: 'e.g. Project Case Studies & Insights | Pislinfra',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Title', searchable: true, sortable: true }
        };
        caseStudyCmConfig.metadatas.metaDescription = {
          edit: {
            label: 'Meta Description',
            description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).',
            placeholder: 'e.g. Explore Pislinfra\'s project case studies, highlighting our expertise...',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Description', searchable: false, sortable: false }
        };
        caseStudyCmConfig.metadatas.focusKeywords = {
          edit: {
            label: 'Focus Keywords',
            description: 'Optional additional related keywords for editorial SEO management.',
            placeholder: 'e.g. Construction Project Case Studies, industrial EPC case studies',
            visible: true,
            editable: true
          },
          list: { label: 'Focus Keywords', searchable: false, sortable: false }
        };

        if (Array.isArray(caseStudyCmConfig.layouts?.edit)) {
          const editRows = caseStudyCmConfig.layouts.edit;
          const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
          if (!hasField('primaryKeyword')) {
            editRows.splice(2, 0,
              [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
              [{ name: 'metaTitle', size: 12 }],
              [{ name: 'metaDescription', size: 12 }]
            );
          }
        }
        await cmStore.set({ key: 'configuration_content_types::api::case-study-page.case-study-page', value: caseStudyCmConfig });
        strapi.log.info('[Bootstrap] Successfully configured case-study-page SEO fields in Content Manager via cmStore!');
      }

      const tableName = (await strapi.db.connection.schema.hasTable('strapi_core_store_settings'))
        ? 'strapi_core_store_settings'
        : (await strapi.db.connection.schema.hasTable('core_store')) ? 'core_store' : null;
      if (tableName) {
        const rows = await strapi.db.connection(tableName).whereILike('key', '%content_manager%case-study-page%');
        for (const row of rows) {
          let val = typeof row.value === 'string' ? JSON.parse(row.value) : row.value;
          if (val) {
            if (!val.metadatas) val.metadatas = {};
            val.metadatas.primaryKeyword = {
              edit: { label: 'Primary Keyword', description: 'The main target search phrase for the Case Studies page.', placeholder: '', visible: true, editable: true },
              list: { label: 'Primary Keyword' }
            };
            val.metadatas.metaTitle = {
              edit: { label: 'Meta Title', description: 'The SEO title displayed in search results (recommended: 50-60 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Title' }
            };
            val.metadatas.metaDescription = {
              edit: { label: 'Meta Description', description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Description' }
            };
            val.metadatas.focusKeywords = {
              edit: { label: 'Focus Keywords', description: 'Optional additional related keywords for editorial SEO management.', placeholder: '', visible: true, editable: true },
              list: { label: 'Focus Keywords' }
            };

            if (Array.isArray(val.layouts?.edit)) {
              const editRows = val.layouts.edit;
              const hasField = (name: string) => editRows.some((row: any) => Array.isArray(row) && row.some((col: any) => col.name === name));
              if (!hasField('primaryKeyword')) {
                editRows.splice(2, 0,
                  [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
                  [{ name: 'metaTitle', size: 12 }],
                  [{ name: 'metaDescription', size: 12 }]
                );
              }
            }
            await strapi.db.connection(tableName).where('id', row.id).update({ value: JSON.stringify(val) });
          }
        }
      }
    } catch (caseStudyCmErr) {
      strapi.log.warn('[Bootstrap] Case-study-page layout config note:', caseStudyCmErr);
    }

    // Automatically configure Solution Page SEO fields in Strapi Content Manager layout
    try {
      const cmStore = strapi.store({ type: 'plugin', name: 'content_manager' });
      let solutionCmConfig: any = await cmStore.get({ key: 'configuration_content_types::api::solution-page.solution-page' });
      if (solutionCmConfig) {
        if (!solutionCmConfig.metadatas) solutionCmConfig.metadatas = {};
        solutionCmConfig.metadatas.primaryKeyword = {
          edit: {
            label: 'Primary Keyword',
            description: 'The main target search phrase for the Solutions page.',
            placeholder: 'e.g. Construction & Infrastructure Solutions',
            visible: true,
            editable: true
          },
          list: { label: 'Primary Keyword', searchable: true, sortable: true }
        };
        solutionCmConfig.metadatas.metaTitle = {
          edit: {
            label: 'Meta Title',
            description: 'The SEO title displayed in search results (recommended: 50-60 chars).',
            placeholder: 'e.g. Construction & Infrastructure Solutions | Pislinfra',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Title', searchable: true, sortable: true }
        };
        solutionCmConfig.metadatas.metaDescription = {
          edit: {
            label: 'Meta Description',
            description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).',
            placeholder: 'e.g. Explore Pislinfra\'s comprehensive construction and infrastructure solutions...',
            visible: true,
            editable: true
          },
          list: { label: 'Meta Description', searchable: false, sortable: false }
        };
        solutionCmConfig.metadatas.focusKeywords = {
          edit: {
            label: 'Focus Keywords',
            description: 'Optional additional related keywords for editorial SEO management.',
            placeholder: 'e.g. Construction & Infrastructure Solutions, turnkey industrial EPC, warehouse construction',
            visible: true,
            editable: true
          },
          list: { label: 'Focus Keywords', searchable: false, sortable: false }
        };

        if (Array.isArray(solutionCmConfig.layouts?.edit)) {
          const editRows = solutionCmConfig.layouts.edit;
          const filtered = editRows.filter((row: any) => !Array.isArray(row) || !row.some((col: any) => ['primaryKeyword', 'metaTitle', 'metaDescription', 'focusKeywords'].includes(col.name)));
          const seoIdx = filtered.findIndex((row: any) => Array.isArray(row) && row.some((col: any) => col.name === 'seo'));
          const insertAt = seoIdx >= 0 ? seoIdx : 2;
          filtered.splice(insertAt, 0,
            [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
            [{ name: 'metaTitle', size: 12 }],
            [{ name: 'metaDescription', size: 12 }]
          );
          solutionCmConfig.layouts.edit = filtered;
        }
        await cmStore.set({ key: 'configuration_content_types::api::solution-page.solution-page', value: solutionCmConfig });
        strapi.log.info('[Bootstrap] Successfully configured solution-page SEO fields in Content Manager via cmStore!');
      }

      const tableName = (await strapi.db.connection.schema.hasTable('strapi_core_store_settings'))
        ? 'strapi_core_store_settings'
        : (await strapi.db.connection.schema.hasTable('core_store')) ? 'core_store' : null;
      if (tableName) {
        const rows = await strapi.db.connection(tableName).whereILike('key', '%content_manager%solution-page%');
        for (const row of rows) {
          let val = typeof row.value === 'string' ? JSON.parse(row.value) : row.value;
          if (val) {
            if (!val.metadatas) val.metadatas = {};
            val.metadatas.primaryKeyword = {
              edit: { label: 'Primary Keyword', description: 'The main target search phrase for the Solutions page.', placeholder: '', visible: true, editable: true },
              list: { label: 'Primary Keyword' }
            };
            val.metadatas.metaTitle = {
              edit: { label: 'Meta Title', description: 'The SEO title displayed in search results (recommended: 50-60 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Title' }
            };
            val.metadatas.metaDescription = {
              edit: { label: 'Meta Description', description: 'The descriptive snippet that may appear in search results (recommended: 150-160 chars).', placeholder: '', visible: true, editable: true },
              list: { label: 'Meta Description' }
            };
            val.metadatas.focusKeywords = {
              edit: { label: 'Focus Keywords', description: 'Optional additional related keywords for editorial SEO management.', placeholder: '', visible: true, editable: true },
              list: { label: 'Focus Keywords' }
            };

            if (Array.isArray(val.layouts?.edit)) {
              const editRows = val.layouts.edit;
              const filtered = editRows.filter((r: any) => !Array.isArray(r) || !r.some((c: any) => ['primaryKeyword', 'metaTitle', 'metaDescription', 'focusKeywords'].includes(c.name)));
              const seoIdx = filtered.findIndex((r: any) => Array.isArray(r) && r.some((c: any) => c.name === 'seo'));
              const insertAt = seoIdx >= 0 ? seoIdx : 2;
              filtered.splice(insertAt, 0,
                [{ name: 'primaryKeyword', size: 6 }, { name: 'focusKeywords', size: 6 }],
                [{ name: 'metaTitle', size: 12 }],
                [{ name: 'metaDescription', size: 12 }]
              );
              val.layouts.edit = filtered;
            }
            await strapi.db.connection(tableName).where('id', row.id).update({ value: JSON.stringify(val) });
          }
        }
      }
    } catch (solutionCmErr) {
      strapi.log.warn('[Bootstrap] Solution-page layout config note:', solutionCmErr);
    }
    strapi.log.info('[Bootstrap] Strapi Content Manager SEO layouts refreshed successfully.');

    // FastSave Optimizer: Eliminate 100s save delay by skipping unchanged components
    strapi.documents.use(async (context: any, next: any) => {
      if (context.uid === 'api::ehs-page.ehs-page' && context.action === 'update') {
        if (typeof (global as any).clearEhsCache === 'function') {
          (global as any).clearEhsCache();
        }
        const data = context.params?.data;
        if (data && typeof data === 'object') {
          try {
            const currentDoc: any = await strapi.documents('api::ehs-page.ehs-page').findFirst({
              populate: {
                heroImage: true,
                stats: true,
                features: true,
                philosophy: { populate: { image: true } },
                approaches: true,
                rules: true,
                pillars: true,
                programs: true,
                gallery: { populate: { image: true } },
                awards: { populate: { image: true } },
                resources: { populate: { file: true, coverImage: true } },
                resourcesCoverImage: true,
                seo: true,
              }
            });

            if (currentDoc) {
              const componentKeys = [
                'stats', 'features', 'philosophy', 'approaches',
                'rules', 'pillars', 'programs', 'gallery', 'awards', 'resources'
              ];

              const skipped: string[] = [];
              const updated: string[] = [];

              for (const key of componentKeys) {
                if (Array.isArray(data[key]) && Array.isArray(currentDoc[key])) {
                  if (areComponentsIdentical(data[key], currentDoc[key])) {
                    delete data[key];
                    skipped.push(key);
                  } else {
                    updated.push(key);
                  }
                }
              }

              strapi.log.info(`[FastSave Optimizer] Updating: [${updated.join(', ') || 'scalars only'}]. Skipped identical: [${skipped.join(', ')}]`);
            }
          } catch (diffErr) {
            strapi.log.warn('[FastSave Optimizer] Diff error, falling back to full update:', diffErr);
          }
        }
      }
      return next();
    });

    // Real-Time 2-Second Auto-Sync Dispatcher
    strapi.documents.use(async (context: any, next: any) => {
      const result = await next();
      if (
        context.uid &&
        context.uid.startsWith('api::') &&
        ['create', 'update', 'delete', 'publish', 'unpublish', 'discardDraft'].includes(context.action)
      ) {
        try {
          fetch('http://127.0.0.1:5174/api/webhook', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              event: `entry.${context.action}`,
              uid: context.uid,
              action: context.action,
              documentId: context.params?.documentId || result?.documentId,
              entry: result,
              timestamp: Date.now()
            }),
            signal: AbortSignal.timeout(600)
          }).catch(() => {});
        } catch (e) {}
      }
      return result;
    });

    // File upload real-time dispatcher
    try {
      strapi.db.lifecycles.subscribe({
        models: ['plugin::upload.file'],
        async afterCreate(event: any) {
          try {
            fetch('http://127.0.0.1:5174/api/webhook', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                event: 'media.create',
                model: 'file',
                entry: event.result,
                timestamp: Date.now()
              }),
              signal: AbortSignal.timeout(600)
            }).catch(() => {});
          } catch (e) {}
        },
        async afterUpdate(event: any) {
          try {
            fetch('http://127.0.0.1:5174/api/webhook', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                event: 'media.update',
                model: 'file',
                entry: event.result,
                timestamp: Date.now()
              }),
              signal: AbortSignal.timeout(600)
            }).catch(() => {});
          } catch (e) {}
        }
      });
    } catch (e) {}
  },
};

function getMediaId(m: any): any {
  if (!m) return null;
  if (typeof m === 'number') return m;
  if (typeof m === 'string') return m;
  if (typeof m === 'object') return m.id || m.documentId || null;
  return null;
}

function areComponentsIdentical(incoming: any[], existing: any[]): boolean {
  if (incoming.length !== existing.length) return false;
  for (let i = 0; i < incoming.length; i++) {
    const inc = incoming[i];
    const ext = existing[i];
    if (!inc || !ext) return false;

    const keys = Object.keys(inc);
    for (const key of keys) {
      if (['__temp_key__', '__component', 'documentId', 'id'].includes(key)) continue;

      const valA = inc[key];
      const valB = ext[key];

      // Media fields
      if (['image', 'file', 'coverImage'].includes(key)) {
        const idA = getMediaId(valA);
        const idB = getMediaId(valB);
        if (idA !== idB && String(idA || '') !== String(idB || '')) {
          return false;
        }
        continue;
      }

      // Primitive fields
      if (typeof valA !== 'object' && typeof valB !== 'object') {
        if (String(valA ?? '') !== String(valB ?? '')) {
          return false;
        }
        continue;
      }
    }
  }
  return true;
}
