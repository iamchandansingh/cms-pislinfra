import type { StrapiApp } from "@strapi/strapi/admin";

export default {
  config: {
    locales: [],
  },
  register(app: StrapiApp) {
    if (app && app.customFields && typeof app.customFields.register === "function") {
      app.customFields.register({
        name: "move-to-completed",
        pluginId: "",
        type: "string",
        intlLabel: {
          id: "move-to-completed.label",
          defaultMessage: "Move to Completed Action",
        },
        intlDescription: {
          id: "move-to-completed.description",
          defaultMessage: "Move ongoing project to completed",
        },
        components: {
          Input: async () => import("./components/MoveToCompletedField"),
        },
      });
    }
  },
  bootstrap(app: StrapiApp) {},
};
