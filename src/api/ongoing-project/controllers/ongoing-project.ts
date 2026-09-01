import { factories } from "@strapi/strapi";

export default factories.createCoreController("api::ongoing-project.ongoing-project", ({ strapi }) => ({
  async moveToCompleted(ctx: any) {
    try {
      const { id } = ctx.params || {};
      const body = ctx.request.body || {};
      const targetIdentifier = id || body.documentId || body.projectId || body.name || body.id;

      if (!targetIdentifier) {
        return ctx.badRequest("Project ID, documentId, or name is required");
      }

      // Find the ongoing project
      let ongoing: any = null;
      if (typeof targetIdentifier === "string" && targetIdentifier.length > 10 && !/^\d+$/.test(targetIdentifier)) {
        const results = await strapi.documents("api::ongoing-project.ongoing-project").findMany({
          filters: { documentId: targetIdentifier } as any,
          populate: ["images", "seo"] as any,
        });
        ongoing = results && results[0];
      }
      
      if (!ongoing && /^\d+$/.test(String(targetIdentifier))) {
        const results = await strapi.documents("api::ongoing-project.ongoing-project").findMany({
          filters: { id: parseInt(String(targetIdentifier), 10) } as any,
          populate: ["images", "seo"] as any,
        });
        ongoing = results && results[0];
      }

      if (!ongoing) {
        const results = await strapi.documents("api::ongoing-project.ongoing-project").findMany({
          filters: { name: { $containsi: String(targetIdentifier) } } as any,
          populate: ["images", "seo"] as any,
        });
        ongoing = results && results[0];
      }

      if (!ongoing) {
        return ctx.notFound(`Ongoing project "${targetIdentifier}" not found`);
      }

      // 1. Create in Completed Projects
      const imageIds = ongoing.images ? ongoing.images.map((img: any) => img.id) : [];
      
      const newCompleted = await strapi.documents("api::completed-project.completed-project").create({
        data: {
          name: ongoing.name || "",
          category: ongoing.category || "Industrial",
          location: ongoing.location || "",
          state: ongoing.state || "",
          area: ongoing.area || "",
          client: ongoing.client || "",
          timeline: ongoing.timeline || "Completed",
          projectStatus: "Completed",
          scope: ongoing.scope || "",
          cx: ongoing.cx ?? null,
          cy: ongoing.cy ?? null,
          images: imageIds,
          seo: ongoing.seo || undefined,
        } as any,
        status: "published",
      });

      // 2. Update Nationwide Coverage Marker if exists
      const markers = await strapi.documents("api::coverage-marker.coverage-marker").findMany({
        filters: { projectName: { $containsi: String(ongoing.name || "") } } as any,
      });
      if (markers && markers.length > 0) {
        for (const marker of markers) {
          await strapi.documents("api::coverage-marker.coverage-marker").update({
            documentId: marker.documentId,
            data: {
              projectType: "Completed",
            } as any,
            status: "published",
          });
        }
      }

      // 3. Delete from Ongoing Projects
      await strapi.documents("api::ongoing-project.ongoing-project").delete({
        documentId: ongoing.documentId,
      });

      return ctx.send({
        success: true,
        message: `Project "${ongoing.name}" successfully moved to Completed Projects!`,
        completedProject: newCompleted,
      });
    } catch (err: any) {
      strapi.log.error("Error in moveToCompleted:", err);
      return ctx.internalServerError(err.message || "Failed to move project to completed");
    }
  },
}));
