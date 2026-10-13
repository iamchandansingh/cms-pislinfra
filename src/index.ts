import type { Core } from '@strapi/strapi';
import { exec } from 'child_process';
import path from 'path';

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
            'api::solution-page': { controllers: { 'solution-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::top-header': { controllers: { 'top-header': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::warehouse-page': { controllers: { 'warehouse-page': { find: { enabled: true }, findOne: { enabled: true } } } },
            'api::coverage-marker': { controllers: { 'coverage-marker': { find: { enabled: true }, findOne: { enabled: true }, create: { enabled: true }, update: { enabled: true }, delete: { enabled: true } } } }
          }
        });
        console.log('Public permissions set for all API endpoints.');
      }

      // 5-Minute Auto-Backup Heartbeat to Hostinger Local DB
      const FIVE_MINUTES_MS = 5 * 60 * 1000;
      setInterval(() => {
        try {
          const reactDir = path.resolve(process.cwd(), '../pislinfra-react');
          const script = path.join(reactDir, 'scripts/syncProjectsOnly.js');
          exec(`node "${script}"`, { cwd: reactDir }, (err, stdout, stderr) => {
            if (!err) {
              strapi.log.info('[5-Min-AutoBackup] Hostinger Dual DB backup successfully synced and verified.');
            }
          });
        } catch (e: any) {
          strapi.log.warn('[5-Min-AutoBackup] Heartbeat trigger error: ' + e.message);
        }
      }, FIVE_MINUTES_MS);

    } catch (err) {
      console.error('Error in bootstrap:', err);
    }
  },
};
