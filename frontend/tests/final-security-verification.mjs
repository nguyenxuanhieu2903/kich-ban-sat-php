import assert from 'node:assert/strict';
import test from 'node:test';

const BASE_URL = 'http://127.0.0.1:8000';

test('1. CSRF Verification: Enforce CSRF token on login and state-changing requests', async () => {
  // 1.1 Mutation (POST /api/v1/auth/login) without CSRF token -> REJECTED (403 Forbidden)
  const noCsrfLoginRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'player.hieu@kichbansat.vn', password: 'password123' })
  });
  assert.equal(noCsrfLoginRes.status, 403, 'Missing CSRF token on login must return 403 Forbidden');
  const noCsrfLoginJson = await noCsrfLoginRes.json();
  assert.equal(noCsrfLoginJson.success, false);
  assert.equal(noCsrfLoginJson.error?.code, 'CSRF_TOKEN_INVALID');

  // 1.2 Mutation with invalid/forged CSRF token -> REJECTED (403 Forbidden)
  const invalidCsrfRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': 'malicious_forged_csrf_token_12345'
    },
    body: JSON.stringify({ email: 'player.hieu@kichbansat.vn', password: 'password123' })
  });
  assert.equal(invalidCsrfRes.status, 403, 'Forged CSRF token on login must return 403 Forbidden');
  const invalidCsrfJson = await invalidCsrfRes.json();
  assert.equal(invalidCsrfJson.success, false);
  assert.equal(invalidCsrfJson.error?.code, 'CSRF_TOKEN_INVALID');

  // 1.3 Request with valid CSRF token and session -> SUCCEEDS (200 OK)
  const csrfRes = await fetch(`${BASE_URL}/api/v1/auth/csrf-token`);
  assert.equal(csrfRes.status, 200);
  const csrfJson = await csrfRes.json();
  const validCsrf = csrfJson.data.csrf_token;
  const sessionCookie = csrfRes.headers.get('set-cookie').split(';')[0];

  const validLoginRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': validCsrf,
      'Cookie': sessionCookie
    },
    body: JSON.stringify({ email: 'player.hieu@kichbansat.vn', password: 'password123' })
  });
  assert.equal(validLoginRes.status, 200, 'Valid CSRF token on login must succeed with 200 OK');
  const validLoginJson = await validLoginRes.json();
  assert.equal(validLoginJson.success, true);
  const activeSession = (validLoginRes.headers.get('set-cookie') || sessionCookie).split(';')[0];

  // 1.4 Mutation (POST /api/v1/auth/logout) without CSRF token -> REJECTED (403 Forbidden)
  const noCsrfLogoutRes = await fetch(`${BASE_URL}/api/v1/auth/logout`, {
    method: 'POST',
    headers: { 'Cookie': activeSession }
  });
  assert.equal(noCsrfLogoutRes.status, 403, 'Missing CSRF token on logout must return 403 Forbidden');
  const noCsrfLogoutJson = await noCsrfLogoutRes.json();
  assert.equal(noCsrfLogoutJson.success, false);
  assert.equal(noCsrfLogoutJson.error?.code, 'CSRF_TOKEN_INVALID');

  // 1.5 Mutation with valid CSRF token -> SUCCEEDS (200 OK)
  const validLogoutRes = await fetch(`${BASE_URL}/api/v1/auth/logout`, {
    method: 'POST',
    headers: {
      'Cookie': activeSession,
      'X-CSRF-TOKEN': validCsrf
    }
  });
  assert.equal(validLogoutRes.status, 200, 'Valid CSRF token on logout must succeed with 200 OK');
  const validLogoutJson = await validLogoutRes.json();
  assert.equal(validLogoutJson.success, true);
});

test('2. RBAC: Strict Access Control across Unauthenticated, Player, DM, and Admin', async () => {
  // 2.1 Unauthenticated requests to protected endpoints
  const unauthAdmin = await fetch(`${BASE_URL}/api/v1/admin/users`);
  assert.equal(unauthAdmin.status, 401, 'Unauthenticated access to /admin/users must return 401');

  const unauthDm = await fetch(`${BASE_URL}/api/v1/dm/assignments`);
  assert.equal(unauthDm.status, 401, 'Unauthenticated access to /dm/assignments must return 401');

  // 2.2 Authenticate as PLAYER
  const csrfResPlayer = await fetch(`${BASE_URL}/api/v1/auth/csrf-token`);
  const csrfTokenPlayer = (await csrfResPlayer.json()).data.csrf_token;
  const cookiePlayerInit = csrfResPlayer.headers.get('set-cookie').split(';')[0];

  const playerLoginRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfTokenPlayer, 'Cookie': cookiePlayerInit },
    body: JSON.stringify({ email: 'player.hieu@kichbansat.vn', password: 'password123' })
  });
  const playerCookie = (playerLoginRes.headers.get('set-cookie') || cookiePlayerInit).split(';')[0];

  // Player accessing Admin resources -> 403
  const playerAdminRes = await fetch(`${BASE_URL}/api/v1/admin/users`, { headers: { 'Cookie': playerCookie } });
  assert.equal(playerAdminRes.status, 403, 'Player accessing /admin/users must return 403');

  // Player accessing DM resources -> 403
  const playerDmRes = await fetch(`${BASE_URL}/api/v1/dm/assignments`, { headers: { 'Cookie': playerCookie } });
  assert.equal(playerDmRes.status, 403, 'Player accessing /dm/assignments must return 403');

  // 2.3 Authenticate as DM
  const csrfResDm = await fetch(`${BASE_URL}/api/v1/auth/csrf-token`);
  const csrfTokenDm = (await csrfResDm.json()).data.csrf_token;
  const cookieDmInit = csrfResDm.headers.get('set-cookie').split(';')[0];

  const dmLoginRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfTokenDm, 'Cookie': cookieDmInit },
    body: JSON.stringify({ email: 'dm.nam@kichbansat.vn', password: 'password123' })
  });
  const dmCookie = (dmLoginRes.headers.get('set-cookie') || cookieDmInit).split(';')[0];

  // DM accessing DM resources -> 200 OK
  const dmRes = await fetch(`${BASE_URL}/api/v1/dm/assignments`, { headers: { 'Cookie': dmCookie } });
  assert.equal(dmRes.status, 200, 'DM accessing /dm/assignments must return 200 OK');
  await dmRes.json();

  // DM accessing Admin resources -> 403 Forbidden
  const dmAdminRes = await fetch(`${BASE_URL}/api/v1/admin/users`, { headers: { 'Cookie': dmCookie } });
  assert.equal(dmAdminRes.status, 403, 'DM accessing /admin/users must return 403 Forbidden');

  // 2.4 Authenticate as ADMIN
  const csrfResAdmin = await fetch(`${BASE_URL}/api/v1/auth/csrf-token`);
  const csrfTokenAdmin = (await csrfResAdmin.json()).data.csrf_token;
  const cookieAdminInit = csrfResAdmin.headers.get('set-cookie').split(';')[0];

  const adminLoginRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfTokenAdmin, 'Cookie': cookieAdminInit },
    body: JSON.stringify({ email: 'admin@kichbansat.vn', password: 'password123' })
  });
  const adminCookie = (adminLoginRes.headers.get('set-cookie') || cookieAdminInit).split(';')[0];

  // Admin accessing Admin resources -> 200 OK
  const adminUsersRes = await fetch(`${BASE_URL}/api/v1/admin/users`, { headers: { 'Cookie': adminCookie } });
  assert.equal(adminUsersRes.status, 200, 'Admin accessing /admin/users must return 200 OK');
  await adminUsersRes.json();

  // Admin accessing DM resources -> 200 OK (Supervisory access)
  const adminDmRes = await fetch(`${BASE_URL}/api/v1/dm/assignments`, { headers: { 'Cookie': adminCookie } });
  assert.equal(adminDmRes.status, 200, 'Admin accessing /dm/assignments must return 200 OK');
  await adminDmRes.json();
});

test('3. Role Mismatch Protection: Backend denies client-side privilege escalation', async () => {
  const csrfRes = await fetch(`${BASE_URL}/api/v1/auth/csrf-token`);
  const csrfToken = (await csrfRes.json()).data.csrf_token;
  const cookie = csrfRes.headers.get('set-cookie').split(';')[0];

  // Authenticate using Player account
  const loginRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Cookie': cookie },
    body: JSON.stringify({ email: 'player.hieu@kichbansat.vn', password: 'password123' })
  });
  const loginJson = await loginRes.json();
  const sessionCookie = (loginRes.headers.get('set-cookie') || cookie).split(';')[0];

  // Verify server-side role
  assert.equal(loginJson.data.user.role_code, 'PLAYER', 'Server must assign PLAYER');
  assert.notEqual(loginJson.data.user.role_code, 'ADMIN', 'Server must never grant ADMIN to player account');

  // Verify server rejects admin action
  const forbiddenAction = await fetch(`${BASE_URL}/api/v1/admin/users`, {
    headers: { 'Cookie': sessionCookie }
  });
  assert.equal(forbiddenAction.status, 403, 'Player cannot perform admin action');
});

test('4. Demo Credentials Isolation: Production Safety Verification', async () => {
  const { isDevEnvironment, getRoleConfigs } = await import('../src/pages/auth/auth-modal.js');

  // Verify development behavior (localhost / default)
  const devConfigs = getRoleConfigs();
  assert.ok(devConfigs.PLAYER.demoEmail, 'Dev mode provides player demo email');
  assert.ok(devConfigs.ADMIN.demoEmail, 'Dev mode provides admin demo email');

  // Simulate Production environment override: window.__APP_CONFIG__.enableDemoFill = false
  globalThis.window = {
    __APP_CONFIG__: { enableDemoFill: false },
    location: { hostname: 'kichbansat.vn' }
  };

  assert.equal(isDevEnvironment(), false, 'isDevEnvironment returns false in Production');
  const prodConfigs = getRoleConfigs();
  assert.equal(prodConfigs.PLAYER.demoEmail, null, 'Production hides player demo email');
  assert.equal(prodConfigs.PLAYER.demoPassword, null, 'Production hides player demo password');
  assert.equal(prodConfigs.DM.demoEmail, null, 'Production hides DM demo email');
  assert.equal(prodConfigs.DM.demoPassword, null, 'Production hides DM demo password');
  assert.equal(prodConfigs.ADMIN.demoEmail, null, 'Production hides Admin demo email');
  assert.equal(prodConfigs.ADMIN.demoPassword, null, 'Production hides Admin demo password');

  // Cleanup mock
  delete globalThis.window;
});
