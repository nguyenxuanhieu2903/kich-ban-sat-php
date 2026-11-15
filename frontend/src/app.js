import { HealthPage } from './pages/health/health-page.js';

document.addEventListener('DOMContentLoaded', () => {
  const mountPoint = document.getElementById('app-mount');
  if (mountPoint) {
    const healthPage = new HealthPage(mountPoint);
    healthPage.runCheck();
  }
});
