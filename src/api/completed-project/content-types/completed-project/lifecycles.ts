import { exec } from 'child_process';
import path from 'path';

let syncTimeout: any = null;
let isSyncingMarker = false;

function triggerDirectSync() {
  if (syncTimeout) clearTimeout(syncTimeout);
  syncTimeout = setTimeout(() => {
    try {
      const reactDir = path.resolve(process.cwd(), '../pislinfra-react');
      const script = path.join(reactDir, 'scripts/syncProjectsOnly.js');
      exec(`node "${script}"`, { cwd: reactDir }, (err, stdout, stderr) => {
        if (err) {
          strapi.log.warn('[DirectSync] Error updating React data files: ' + err.message);
        } else {
          strapi.log.info('[DirectSync] Successfully synced completed projects directly to completedProjects.js & MainBackup!');
        }
      });
    } catch (e: any) {
      strapi.log.warn('[DirectSync] Trigger failed: ' + e.message);
    }
  }, 300);
}

export default {
  async afterCreate(event: any) {
    const { result } = event;
    if (!isSyncingMarker && result && result.name) {
      isSyncingMarker = true;
      try {
        const existing = await strapi.documents("api::coverage-marker.coverage-marker").findMany({
          filters: { projectName: { $containsi: String(result.name || "") } } as any,
        });
        if (!existing || existing.length === 0) {
          const imgIds = Array.isArray(result.images) ? result.images.map((img: any) => img.id || img) : [];
          await strapi.documents("api::coverage-marker.coverage-marker").create({
            data: {
              projectName: result.name,
              projectType: "Completed",
              state: result.state,
              city: result.location || result.city,
              images: imgIds,
              cx: null,
              cy: null,
              showOnMap: false,
              markerStatus: "Pending Alignment",
            } as any,
            status: "published",
          });
          strapi.log.info(`[Lifecycle] Registered new Completed Project "${result.name}" in Nationwide Coverage (Pending Alignment, showOnMap=false).`);
        }
      } catch (e) {
        strapi.log.warn("[Lifecycle completed afterCreate marker sync error]:", e);
      } finally {
        isSyncingMarker = false;
      }
    }
    triggerDirectSync();
  },

  async afterUpdate(event: any) {
    const { result } = event;
    if (!isSyncingMarker && result && result.name) {
      isSyncingMarker = true;
      try {
        const existing = await strapi.documents("api::coverage-marker.coverage-marker").findMany({
          filters: { projectName: { $containsi: String(result.name || "") } } as any,
        });
        if (existing && existing.length > 0) {
          const currentMarker = existing[0];
          await strapi.documents("api::coverage-marker.coverage-marker").update({
            documentId: currentMarker.documentId,
            data: {
              state: result.state || currentMarker.state,
              city: result.location || result.city || currentMarker.city,
            } as any,
            status: "published",
          });
        }
      } catch (e) {
        strapi.log.warn("[Lifecycle completed afterUpdate marker sync error]:", e);
      } finally {
        isSyncingMarker = false;
      }
    }
    triggerDirectSync();
  },

  async afterDelete() {
    triggerDirectSync();
  },
};
