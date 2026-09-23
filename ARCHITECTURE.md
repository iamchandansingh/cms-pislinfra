# Pislinfra CMS Architecture Plan

This document provides a high-level overview of the technologies and architecture used in the Pislinfra Strapi CMS. It serves as a guide for any future developers working on this project.

## 1. Neon DB (PostgreSQL) - Primary Database
* **Role:** Serves as the primary relational database for the Strapi CMS. All textual content (Blogs, Projects, Case Studies, Awards, etc.) and admin configurations are stored here.
* **Technical Details:** 
  - Uses the `pg` (Postgres) client.
  - Configured in `config/database.ts`.
  - Connects using a **Neon Connection Pooler** (`pooler.c-4.us-east-2.aws.neon.tech`). This is a critical best practice for serverless and cloud environments to prevent connection limit exhaustion and ensure high stability.
  - Database SSL is enforced (`rejectUnauthorized: false`) to ensure end-to-end encryption.

## 2. Cloudflare R2 - Object & Media Storage
* **Role:** Acts as the decentralized media library storage. Any media (Images, Logos, PDFs, etc.) uploaded via the Strapi Admin panel bypasses the local server disk and is uploaded directly to Cloudflare R2.
* **Technical Details:**
  - Configured in `config/plugins.ts`.
  - Uses the `@strapi/provider-upload-aws-s3` plugin, which has been pointed to the Cloudflare R2 endpoint (`https://3282b1f29fc44baa63c4b1c3bcdd7d22.r2.cloudflarestorage.com`).
  - Bucket Name: `pisl-cms-media`
  - **CDN Delivery:** Media is served to the frontend via a blazing-fast Cloudflare public R2 URL (`https://pub-eeb28a3c927b4ae1b67e3e8e731ee105.r2.dev`). This ensures the Strapi server uses 0 bandwidth for serving images and guarantees global fast loading for the frontend.

## 3. High-Level Data Flow
1. **Admin Input:** Admin logs into Strapi (`cms-pislinfra.onrender.com/admin`) and creates an entry.
2. **Text Processing:** Textual data is inserted into the **Neon PostgreSQL** database.
3. **Media Processing:** Images/PDFs are intercepted by the S3 plugin and piped directly to **Cloudflare R2**.
4. **Frontend Delivery:** The React/Vite frontend fetches JSON data from the Strapi API. The JSON contains the Cloudflare R2 URLs for media. The frontend renders the text and fetches images directly from Cloudflare's CDN.

## 4. Frontend Offline Fallback Engine (Important)
Even if this CMS (Render backend) goes offline or sleeps, the frontend will **NOT** break. 
The React frontend is equipped with an Enterprise Offline Fallback Engine (IndexedDB + Local JSON backups). If the `VITE_STRAPI_URL` fails to respond, the frontend automatically falls back to bundled JSON files (e.g., `MainBackupPislinfra.json`), ensuring 100% uptime for end users.
