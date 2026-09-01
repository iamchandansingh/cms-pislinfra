export default {
  async afterUpdate(event: any) {
    const { result } = event;
    try {
      if (result && result.projectType === "Completed" && result.projectName) {
        const ongoingResults = await strapi.documents("api::ongoing-project.ongoing-project").findMany({
          filters: { name: { $containsi: result.projectName } } as any,
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
              cx: ongoing.cx ?? result.cx,
              cy: ongoing.cy ?? result.cy,
              images: imageIds,
              seo: ongoing.seo,
            } as any,
            status: "published",
          });

          await strapi.documents("api::ongoing-project.ongoing-project").delete({
            documentId: ongoing.documentId,
          });

          strapi.log.info(`[Lifecycle] Successfully moved project "${ongoing.name}" to Completed Projects!`);
        }
      }
    } catch (err) {
      strapi.log.error("[Lifecycle Error on coverage-marker afterUpdate]:", err);
    }
  },
};
