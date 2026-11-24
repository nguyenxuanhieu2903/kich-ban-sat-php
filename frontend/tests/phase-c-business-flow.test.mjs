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
