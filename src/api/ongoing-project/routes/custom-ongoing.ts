export default {
  routes: [
    {
      method: "POST",
      path: "/ongoing-projects/:id/move-to-completed",
      handler: "ongoing-project.moveToCompleted",
      config: {
        auth: false,
      },
    },
    {
      method: "POST",
      path: "/ongoing-projects/move-to-completed",
      handler: "ongoing-project.moveToCompleted",
      config: {
        auth: false,
      },
    },
  ],
};
