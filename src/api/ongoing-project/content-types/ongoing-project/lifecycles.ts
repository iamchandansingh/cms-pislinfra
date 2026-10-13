import { exec } from 'child_process';
import path from 'path';

let syncTimeout: any = null;
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
          strapi.log.info('[DirectSync] Successfully synced changes directly to projectsData.js & MainBackup!');
        }
      });
    } catch (e: any) {
      strapi.log.warn('[DirectSync] Trigger failed: ' + e.message);
    }
  }, 300);
}

export default {
  async afterCreate() {
    triggerDirectSync();
  },

  async afterDelete() {
    triggerDirectSync();
  },

  async afterUpdate(event: any) {
    const { result } = event;
    try {
      if (result && (result.projectStatus === "Completed" || (result.projectStatus || "").toLowerCase() === "completed")) {
        const ongoingResults = await strapi.documents("api::ongoing-project.ongoing-project").findMany({
          filters: { id: result.id } as any,
          populate: ["images", "seo"] as any,
        });

        if (ongoingResults && ongoingResults.length > 0) {
          const ongoing: any = ongoingResults[0];
          const imageIds = ongoing.images ? ongoing.images.map((img: any) => img.id) : [];

          await strapi.documents("api::completed-project.completed-project").create({
            data: {
              name: ongoing.name,
              category: ongoing.category || "Industrial",
              location: ongoing.location,
              state: ongoing.state,
              area: ongoing.area,
              client: ongoing.client,
              timeline: ongoing.timeline || "Completed",
              projectStatus: "Completed",
              scope: ongoing.scope,
              cx: ongoing.cx,
              cy: ongoing.cy,
              images: imageIds,
              seo: ongoing.seo,
            } as any,
            status: "published",
          });

          const markers = await strapi.documents("api::coverage-marker.coverage-marker").findMany({
            filters: { projectName: { $containsi: String(ongoing.name || "") } } as any,
          });
          if (markers && markers.length > 0) {
            for (const marker of markers) {
              await strapi.documents("api::coverage-marker.coverage-marker").update({
                documentId: marker.documentId,
                data: { projectType: "Completed" } as any,
                status: "published",
              });
            }
          }

          await strapi.documents("api::ongoing-project.ongoing-project").delete({
            documentId: ongoing.documentId,
          });

          strapi.log.info(`[Lifecycle] Successfully moved project "${ongoing.name}" to Completed Projects!`);
        }
      }
    } catch (err) {
      strapi.log.error("[Lifecycle Error on ongoing-project afterUpdate]:", err);
    }

    triggerDirectSync();
  },
};
