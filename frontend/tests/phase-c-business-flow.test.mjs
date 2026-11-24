import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const BASE_URL = 'http://127.0.0.1:8000';

test('Phase C: DM View contains player list toggle and container', () => {
  const filePath = path.resolve('frontend/src/pages/dm/dm-view.js');
  const code = fs.readFileSync(filePath, 'utf-8');

  assert.ok(code.includes('btn-toggle-players-${a.id}'), 'Contains toggle player list button');
  assert.ok(code.includes('dm-players-container-${a.id}'), 'Contains players list container');
  assert.ok(code.includes('togglePlayerList(a.id, a.game_room_id)'), 'Wired to togglePlayerList method');
  assert.ok(code.includes('api.get(`/rooms/${roomId}`)'), 'Fetches room participants from /rooms/:id');
  assert.ok(code.includes('✓ Đã cọc 50k'), 'Displays deposit status badge');
});

test('Phase C: Venues View component renders active venues', () => {
  const filePath = path.resolve('frontend/src/pages/venues/venues-view.js');
  const code = fs.readFileSync(filePath, 'utf-8');

  assert.ok(code.includes("api.get('/venues')"), 'Fetches venues from API');
  assert.ok(code.includes('renderVenueCard(v)'), 'Renders venue cards');
  assert.ok(code.includes('escapeHtml(v.name)'), 'Escapes venue name');
  assert.ok(code.includes('escapeHtml(v.address)'), 'Escapes venue address');
});

test('Phase C: Admin View contains Games, Promotions, and Venues subtabs', () => {
  const filePath = path.resolve('frontend/src/pages/admin/admin-view.js');
  const code = fs.readFileSync(filePath, 'utf-8');

  assert.ok(code.includes('tab-btn-games'), 'Contains Games subtab button');
  assert.ok(code.includes('tab-btn-promotions'), 'Contains Promotions subtab button');
  assert.ok(code.includes('tab-btn-venues'), 'Contains Venues subtab button');
  assert.ok(code.includes("api.get('/admin/games')"), 'Fetches admin games');
  assert.ok(code.includes("api.get('/admin/promotions')"), 'Fetches admin promotions');
  assert.ok(code.includes("api.get('/admin/venues')"), 'Fetches admin venues');
  assert.ok(code.includes("api.post('/admin/games'"), 'Creates new games');
  assert.ok(code.includes("api.post('/admin/promotions'"), 'Creates promotions');
  assert.ok(code.includes("api.post('/admin/venues'"), 'Creates venues');
});

test('Phase C: Player Page (player.html) wires profile and bookings to live APIs', () => {
  const filePath = path.resolve('frontend/player.html');
  const code = fs.readFileSync(filePath, 'utf-8');

  assert.ok(code.includes("api.patch('/auth/profile'"), 'Wires profile save to /auth/profile');
  assert.ok(code.includes("api.get('/payments/my')"), 'Wires bookings to /payments/my');
  assert.ok(code.includes('data-pay-deposit'), 'Contains deposit button');
  assert.ok(code.includes('data-pay-remaining'), 'Contains remaining payment button');
  assert.ok(code.includes('data-leave-room'), 'Contains leave room button');
});

test('Phase C: Standalone DM and Admin HTML pages use live API clients', () => {
  const dmHtml = fs.readFileSync(path.resolve('frontend/dm.html'), 'utf-8');
  assert.ok(!dmHtml.includes('localMockAssignments = ['), 'Eliminated localMockAssignments mock array');
  assert.ok(dmHtml.includes("api.get('/dm/assignments')"), 'Loads live assignments');
  assert.ok(dmHtml.includes('api.get(`/rooms/${selectedRoomId}`)'), 'Loads live room details & participants');

  const adminHtml = fs.readFileSync(path.resolve('frontend/admin.html'), 'utf-8');
  assert.ok(!adminHtml.includes('localScripts = [...ALL_SCRIPTS];'), 'Eliminated localScripts mock array');
  assert.ok(!adminHtml.includes('localPhysical = [...PHYSICAL_ROOMS];'), 'Eliminated localPhysical mock array');
  assert.ok(adminHtml.includes("api.get('/admin/games')"), 'Loads live games in Admin');
  assert.ok(adminHtml.includes("api.get('/admin/venues')"), 'Loads live venues in Admin');
});

test('Phase C: Public Venues API returns active branches and physical rooms', async () => {
  const res = await fetch(`${BASE_URL}/api/v1/venues`);
  assert.equal(res.status, 200);
  const data = await res.json();
  assert.equal(data.success, true);
  assert.ok(Array.isArray(data.data.venues), 'Venues is an array');
  assert.ok(data.data.venues.length >= 2, 'Has at least 2 active venues');
  const v = data.data.venues[0];
  assert.ok(v.id, 'Venue has id');
  assert.ok(v.name, 'Venue has name');
  assert.ok(v.address, 'Venue has address');
  assert.ok(Array.isArray(v.rooms), 'Venue has rooms array');
  assert.ok(v.rooms.length >= 1, 'Venue has at least 1 physical room');
});

test('Phase C: Public Game-Rooms API alias (/api/v1/game-rooms) is reachable and returns 200', async () => {
  const res = await fetch(`${BASE_URL}/api/v1/game-rooms`);
  assert.equal(res.status, 200, 'GET /api/v1/game-rooms returns 200 OK');
  const data = await res.json();
  assert.equal(data.success, true);
  assert.ok(Array.isArray(data.data.rooms), 'Returns rooms array');
});

test('Phase C: Notifications view and app badge correctly unpack notifications array from API payload', () => {
  const notifViewPath = path.resolve('frontend/src/pages/notifications/notifications-view.js');
  const notifCode = fs.readFileSync(notifViewPath, 'utf-8');
  assert.ok(
    notifCode.includes('res?.data?.notifications'),
    'NotificationsView unpacks res.data.notifications instead of expecting res.data directly as an array'
  );

  const appPath = path.resolve('frontend/src/app.js');
  const appCode = fs.readFileSync(appPath, 'utf-8');
  assert.ok(
    appCode.includes('res?.data?.notifications'),
    'App updateNotificationsCount unpacks res.data.notifications for unread badge count'
  );
});

test('Gói 1: Player profile and Admin view wire live HSSV verification API', () => {
  const playerHtml = fs.readFileSync(path.resolve('frontend/player.html'), 'utf-8');
  assert.ok(playerHtml.includes("api.post('/auth/student-card'"), 'Player wires HSSV upload to /auth/student-card');
  assert.ok(playerHtml.includes('updateStudentUI'), 'Player updates student badge & status box');

  const adminHtml = fs.readFileSync(path.resolve('frontend/admin.html'), 'utf-8');
  assert.ok(adminHtml.includes('/verify-student'), 'Admin HTML wires verification to /verify-student');

  const adminViewJs = fs.readFileSync(path.resolve('frontend/src/pages/admin/admin-view.js'), 'utf-8');
  assert.ok(adminViewJs.includes('/verify-student'), 'Admin View JS wires verification to /verify-student');
});

test('Gói 1: Host Kick member is wired in rooms-view.js', () => {
  const roomsCode = fs.readFileSync(path.resolve('frontend/src/pages/rooms/rooms-view.js'), 'utf-8');
  assert.ok(roomsCode.includes('btn-kick-member'), 'Contains kick member button');
  assert.ok(roomsCode.includes('/kick'), 'Calls /kick endpoint');
});

test('Gói 1: DM View collects session notes and incident report on complete', () => {
  const dmViewCode = fs.readFileSync(path.resolve('frontend/src/pages/dm/dm-view.js'), 'utf-8');
  assert.ok(dmViewCode.includes('session_notes'), 'Collects session notes');
  assert.ok(dmViewCode.includes('incident_report'), 'Collects incident report');

  const dmHtml = fs.readFileSync(path.resolve('frontend/dm.html'), 'utf-8');
  assert.ok(dmHtml.includes('session_notes'), 'DM HTML passes session notes');
});

test('Gói 2: Admin HTML and AdminView wire Dashboard Overview API (/admin/dashboard)', () => {
  const adminHtml = fs.readFileSync(path.resolve('frontend/admin.html'), 'utf-8');
  assert.ok(adminHtml.includes("api.get('/admin/dashboard')"), 'Admin HTML fetches real metrics from /admin/dashboard');
  assert.ok(adminHtml.includes('revenue_month'), 'Renders revenue month');
  assert.ok(adminHtml.includes('revenue_chart_7d'), 'Renders 7-day revenue chart');

  const adminViewJs = fs.readFileSync(path.resolve('frontend/src/pages/admin/admin-view.js'), 'utf-8');
  assert.ok(adminViewJs.includes("api.get('/admin/dashboard')"), 'AdminView fetches metrics from /admin/dashboard');
  assert.ok(adminViewJs.includes('renderOverviewTab'), 'Implements renderOverviewTab method');
  assert.ok(adminViewJs.includes('tab-btn-overview'), 'Has Overview tab button');
});

test('Gói 2: Admin HTML and AdminView wire Payments and Ledger API (/admin/payments)', () => {
  const adminHtml = fs.readFileSync(path.resolve('frontend/admin.html'), 'utf-8');
  assert.ok(adminHtml.includes("data-atab=\"payments\""), 'Admin HTML contains Tab 7 Payments button');
  assert.ok(adminHtml.includes('/admin/payments'), 'Admin HTML queries /admin/payments');
  assert.ok(adminHtml.includes("data-view-payment"), 'Admin HTML supports viewing payment details');

  const adminViewJs = fs.readFileSync(path.resolve('frontend/src/pages/admin/admin-view.js'), 'utf-8');
  assert.ok(adminViewJs.includes('tab-btn-payments'), 'AdminView contains Payments tab button');
  assert.ok(adminViewJs.includes('renderPaymentsTab'), 'AdminView implements renderPaymentsTab method');
  assert.ok(adminViewJs.includes('/admin/payments'), 'AdminView queries /admin/payments');
  assert.ok(adminViewJs.includes('data-view-payment'), 'AdminView supports viewing payment details');
});

test('Gói 2: Admin Dashboard and Payments endpoints reject unauthenticated access', async () => {
  const dashRes = await fetch(`${BASE_URL}/api/v1/admin/dashboard`);
  assert.ok([401, 403].includes(dashRes.status), 'Dashboard rejects unauthenticated with 401 or 403');

  const payRes = await fetch(`${BASE_URL}/api/v1/admin/payments`);
  assert.ok([401, 403].includes(payRes.status), 'Payments rejects unauthenticated with 401 or 403');
});

test('Gói 3: Admin HTML and AdminView wire Game Configurations API (/admin/games/:id/configurations)', () => {
  const adminHtml = fs.readFileSync(path.resolve('frontend/admin.html'), 'utf-8');
  assert.ok(adminHtml.includes('data-config-game'), 'Admin HTML contains button to configure game characters');
  assert.ok(adminHtml.includes('/configurations'), 'Admin HTML queries /configurations endpoint');

  const adminViewJs = fs.readFileSync(path.resolve('frontend/src/pages/admin/admin-view.js'), 'utf-8');
  assert.ok(adminViewJs.includes('data-config-game'), 'AdminView contains button to configure game characters');
  assert.ok(adminViewJs.includes('/configurations'), 'AdminView queries /configurations endpoint');
});

test('Gói 3: Admin HTML and AdminView wire Audit Log & System Health API (/admin/audit-logs and /admin/system/health)', () => {
  const adminHtml = fs.readFileSync(path.resolve('frontend/admin.html'), 'utf-8');
  assert.ok(adminHtml.includes("data-atab=\"audit\""), 'Admin HTML contains Tab 8 Audit button');
  assert.ok(adminHtml.includes('/admin/system/health'), 'Admin HTML fetches system health');
  assert.ok(adminHtml.includes('/admin/audit-logs'), 'Admin HTML queries audit logs');

  const adminViewJs = fs.readFileSync(path.resolve('frontend/src/pages/admin/admin-view.js'), 'utf-8');
  assert.ok(adminViewJs.includes('tab-btn-audit'), 'AdminView contains Audit tab button');
  assert.ok(adminViewJs.includes('renderAuditTab'), 'AdminView implements renderAuditTab method');
  assert.ok(adminViewJs.includes('/admin/system/health'), 'AdminView fetches system health');
  assert.ok(adminViewJs.includes('/admin/audit-logs'), 'AdminView queries audit logs');
});

test('Gói 3: Audit Logs, Health, and Configurations endpoints reject unauthenticated access', async () => {
  const healthRes = await fetch(`${BASE_URL}/api/v1/admin/system/health`);
  assert.ok([401, 403].includes(healthRes.status), 'System health rejects unauthenticated with 401 or 403');

  const auditRes = await fetch(`${BASE_URL}/api/v1/admin/audit-logs`);
  assert.ok([401, 403].includes(auditRes.status), 'Audit logs rejects unauthenticated with 401 or 403');

  const configRes = await fetch(`${BASE_URL}/api/v1/admin/games/1/configurations`);
  assert.ok([401, 403].includes(configRes.status), 'Game configurations rejects unauthenticated with 401 or 403');
});
