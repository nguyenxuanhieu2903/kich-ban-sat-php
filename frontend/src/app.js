/**
 * Frontend Master Application Bootstrap (Vanilla JS ES Modules)
 * Registers routes across Public, Player, DM, and Admin portals + Health verification.
 */
import { Router } from './utils/router.js';
import { auth } from './utils/auth.js';
import { renderTopNav } from './components/top-nav.js';
import { renderBottomNav } from './components/bottom-nav.js';
import { clearAllToasts } from './components/toast.js';
import { threeAmbient } from './utils/three-ambient.js';
import { clickEffects } from './utils/click-effects.js';
import { initAutoCiphers } from './utils/text-cipher.js';
import { initCardInteractions } from './utils/card-interactions.js';

// Public Pages
import { HomePage } from './pages/public/home-page.js';
import { GamesPage } from './pages/public/games-page.js';
import { GameDetailPage } from './pages/public/game-detail-page.js';
import { RoomsPage } from './pages/public/rooms-page.js';
import { RoomDetailPublicPage } from './pages/public/room-detail-public-page.js';
import { ForbiddenPage, NotFoundPage } from './pages/public/error-pages.js';

// Auth Pages
import { LoginPage } from './pages/auth/login-page.js';
import { RegisterPage } from './pages/auth/register-page.js';

// Player Portal Pages
import { PlayerDashboardPage } from './pages/player/player-dashboard-page.js';
import { CreateRoomWizard } from './pages/player/create-room-wizard.js';
import { PlayerRoomDetailPage } from './pages/player/player-room-detail-page.js';
import { PlayerHistoryPage } from './pages/player/player-history-page.js';
import { PlayerProfilePage } from './pages/player/player-profile-page.js';
import { PlayerNotificationsPage } from './pages/player/player-notifications-page.js';

// DM Portal Pages
import { DmDashboardPage } from './pages/dm/dm-dashboard-page.js';
import { DmAssignmentsPage } from './pages/dm/dm-assignments-page.js';
import { DmRoomDetailPage } from './pages/dm/dm-room-detail-page.js';
import { DmAvailabilityPage } from './pages/dm/dm-availability-page.js';
import { DmSessionOpsPage } from './pages/dm/dm-session-ops-page.js';
import { DmNotificationsPage } from './pages/dm/dm-notifications-page.js';

// Admin Portal Pages
import { AdminDashboardPage } from './pages/admin/admin-dashboard-page.js';
import { AdminRoomsPage } from './pages/admin/admin-rooms-page.js';
import { AdminDmAssignmentPage } from './pages/admin/admin-dm-assignment-page.js';
import { AdminVenuesPage } from './pages/admin/admin-venues-page.js';
import { AdminRefundsPage } from './pages/admin/admin-refunds-page.js';
import { AdminGamesPage } from './pages/admin/admin-games-page.js';
import { AdminPromotionsPage } from './pages/admin/admin-promotions-page.js';
import { AdminSessionsPage } from './pages/admin/admin-sessions-page.js';
import { AdminUsersPage } from './pages/admin/admin-users-page.js';
import { AdminAuditPage } from './pages/admin/admin-audit-page.js';
import { AdminNotificationsPage } from './pages/admin/admin-notifications-page.js';

// Technical Baseline Health Page (Preserved)
import { HealthPage } from './pages/health/health-page.js';

const routes = {
  // Public
  '/': HomePage,
  '/games': GamesPage,
  '/games/:id': GameDetailPage,
  '/rooms': RoomsPage,
  '/rooms/:id': RoomDetailPublicPage,
  '/login': LoginPage,
  '/register': RegisterPage,
  '/403': ForbiddenPage,
  '/404': NotFoundPage,
  '/health': HealthPage,

  // Player Portal
  '/player/dashboard': PlayerDashboardPage,
  '/player/create-room': CreateRoomWizard,
  '/player/rooms/:id': PlayerRoomDetailPage,
  '/player/history': PlayerHistoryPage,
  '/player/profile': PlayerProfilePage,
  '/player/notifications': PlayerNotificationsPage,

  // DM Portal
  '/dm/dashboard': DmDashboardPage,
  '/dm/assignments': DmAssignmentsPage,
  '/dm/rooms/:id': DmRoomDetailPage,
  '/dm/availability': DmAvailabilityPage,
  '/dm/sessions/:id': DmSessionOpsPage,
  '/dm/notifications': DmNotificationsPage,

  // Admin Portal
  '/admin/dashboard': AdminDashboardPage,
  '/admin/rooms': AdminRoomsPage,
  '/admin/dm-assignments': AdminDmAssignmentPage,
  '/admin/venues': AdminVenuesPage,
  '/admin/refunds': AdminRefundsPage,
  '/admin/games': AdminGamesPage,
  '/admin/promotions': AdminPromotionsPage,
  '/admin/sessions': AdminSessionsPage,
  '/admin/users': AdminUsersPage,
  '/admin/audit': AdminAuditPage,
  '/admin/notifications': AdminNotificationsPage,
};

document.addEventListener('DOMContentLoaded', () => {
  const router = new Router(routes, NotFoundPage);

  // Top Nav container mount
  const navMount = document.getElementById('nav-mount');
  const bottomMount = document.getElementById('bottom-nav-mount');

  function updateNavbars() {
    if (navMount) {
      navMount.innerHTML = '';
      navMount.appendChild(renderTopNav(router));
    }
    if (bottomMount) {
      bottomMount.innerHTML = '';
      const bNav = renderBottomNav(router.getCurrentPath());
      if (bNav) bottomMount.appendChild(bNav);
    }
  }

  function onPageTransition() {
    clearAllToasts();
    updateNavbars();
    setTimeout(() => {
      initAutoCiphers();
      initCardInteractions();
    }, 80);
  }

  // Subscribe to auth changes to refresh navbars immediately
  auth.subscribe(() => {
    onPageTransition();
  });

  window.addEventListener('hashchange', () => {
    onPageTransition();
  });

  updateNavbars();
  router.start();

  // Initialize Three.js 3D Occult Engine & Kinetic Click Shockwaves
  try {
    threeAmbient.init();
    clickEffects.init();
    initAutoCiphers();
    initCardInteractions();
  } catch (err) {
    console.warn('3D/Motion enhancements initialized in fallback mode:', err);
  }
});
