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
          strapi.log.info('[DirectSync] Successfully synced completed projects directly to completedProjects.js & MainBackup!');
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

  async afterUpdate() {
    triggerDirectSync();
  },

  async afterDelete() {
    triggerDirectSync();
  },
};
