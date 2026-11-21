import assert from 'node:assert/strict';
import test from 'node:test';

const BASE_URL = 'http://127.0.0.1:8000';

test('1. Public Home Page HTML hides DM and Admin navigation by default', async () => {
  const res = await fetch(`${BASE_URL}/`);
  assert.equal(res.status, 200);
  const html = await res.text();

  // Desktop nav buttons must have style="display: none;"
  assert.match(
    html,
    /<button[^>]*id="nav-btn-dm"[^>]*style="display:\s*none;"/,
    'nav-btn-dm must have style="display: none;" in default HTML'
  );
  assert.match(
    html,
    /<button[^>]*id="nav-btn-admin"[^>]*style="display:\s*none;"/,
    'nav-btn-admin must have style="display: none;" in default HTML'
  );

  // Mobile drawer links must also have style="display: none;"
  assert.match(
    html,
    /<a[^>]*id="mobile-nav-dm"[^>]*style="display:\s*none;"/,
    'mobile-nav-dm must have style="display: none;" in default HTML'
  );
  assert.match(
    html,
    /<a[^>]*id="mobile-nav-admin"[^>]*style="display:\s*none;"/,
    'mobile-nav-admin must have style="display: none;" in default HTML'
  );

  // Public nav buttons that must be visible
  assert.ok(html.includes('id="nav-btn-home"'), 'Home tab must exist');
  assert.ok(html.includes('id="nav-btn-games"'), 'Games tab must exist');
  assert.ok(html.includes('id="nav-btn-rooms"'), 'Rooms tab must exist');
  assert.ok(html.includes('id="btn-open-login"'), 'Login button must exist');
});

test('2. Multi-Role Authentication: Player Login End-to-End', async () => {
  // 1. Get CSRF Token
  const csrfRes = await fetch(`${BASE_URL}/api/v1/auth/csrf-token`);
  assert.equal(csrfRes.status, 200);
  const csrfJson = await csrfRes.json();
  const csrfToken = csrfJson.data.csrf_token;
  const cookie = csrfRes.headers.get('set-cookie');
  assert.ok(csrfToken, 'Got CSRF token');
  assert.ok(cookie, 'Got session cookie');

  // Extract session cookie
  const phpSession = cookie.split(';')[0];

  // 2. Perform Login as Player
  const loginRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'Cookie': phpSession
    },
    body: JSON.stringify({
      email: 'player.hieu@kichbansat.vn',
      password: 'password123'
    })
  });

  assert.equal(loginRes.status, 200, 'Login returns 200 OK');
  const loginJson = await loginRes.json();
  assert.equal(loginJson.success, true);
  const userRole = loginJson.data.user.role_code || loginJson.data.user.role;
  assert.equal(userRole, 'PLAYER', 'Backend assigns role PLAYER');
  assert.equal(loginJson.data.user.email, 'player.hieu@kichbansat.vn');

  // Update session cookie if new one returned
  const loginCookie = loginRes.headers.get('set-cookie') || phpSession;
  const activeSession = loginCookie.split(';')[0];

  // 3. Verify Session via /api/v1/auth/me
  const meRes = await fetch(`${BASE_URL}/api/v1/auth/me`, {
    headers: { 'Cookie': activeSession }
  });
  assert.equal(meRes.status, 200);
  const meJson = await meRes.json();
  assert.equal(meJson.success, true);
  const meRole = meJson.data.user.role_code || meJson.data.user.role;
  assert.equal(meRole, 'PLAYER');

  // 4. Logout
  const logoutRes = await fetch(`${BASE_URL}/api/v1/auth/logout`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': csrfToken,
      'Cookie': activeSession
    }
  });
  assert.equal(logoutRes.status, 200);
  const logoutJson = await logoutRes.json();
  assert.equal(logoutJson.success, true);
});

test('3. Multi-Role Authentication: DM Login End-to-End', async () => {
  // 1. Get CSRF Token
  const csrfRes = await fetch(`${BASE_URL}/api/v1/auth/csrf-token`);
  assert.equal(csrfRes.status, 200);
  const csrfJson = await csrfRes.json();
  const csrfToken = csrfJson.data.csrf_token;
  const cookie = csrfRes.headers.get('set-cookie');
  const phpSession = cookie.split(';')[0];

  // 2. Perform Login as DM
  const loginRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'Cookie': phpSession
    },
    body: JSON.stringify({
      email: 'dm.nam@kichbansat.vn',
      password: 'password123'
    })
  });

  assert.equal(loginRes.status, 200, 'DM login returns 200 OK');
  const loginJson = await loginRes.json();
  assert.equal(loginJson.success, true);
  const userRole = loginJson.data.user.role_code || loginJson.data.user.role;
  assert.equal(userRole, 'DM', 'Backend assigns role DM');
  assert.equal(loginJson.data.user.email, 'dm.nam@kichbansat.vn');

  const loginCookie = loginRes.headers.get('set-cookie') || phpSession;
  const activeSession = loginCookie.split(';')[0];

  // 3. Verify Session via /api/v1/auth/me
  const meRes = await fetch(`${BASE_URL}/api/v1/auth/me`, {
    headers: { 'Cookie': activeSession }
  });
  assert.equal(meRes.status, 200);
  const meJson = await meRes.json();
  assert.equal(meJson.success, true);
  const meRole = meJson.data.user.role_code || meJson.data.user.role;
  assert.equal(meRole, 'DM');

  // 4. Verify DM can access DM assignments
  const dmAssignmentsRes = await fetch(`${BASE_URL}/api/v1/dm/assignments`, {
    headers: { 'Cookie': activeSession }
  });
  assert.equal(dmAssignmentsRes.status, 200, 'DM has access to /api/v1/dm/assignments');
  await dmAssignmentsRes.json();

  // 5. Logout
  const logoutRes = await fetch(`${BASE_URL}/api/v1/auth/logout`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': csrfToken,
      'Cookie': activeSession
    }
  });
  assert.equal(logoutRes.status, 200);
  await logoutRes.json();
});

test('4. Multi-Role Authentication: Admin Login End-to-End', async () => {
  // 1. Get CSRF Token
  const csrfRes = await fetch(`${BASE_URL}/api/v1/auth/csrf-token`);
  assert.equal(csrfRes.status, 200);
  const csrfJson = await csrfRes.json();
  const csrfToken = csrfJson.data.csrf_token;
  const cookie = csrfRes.headers.get('set-cookie');
  const phpSession = cookie.split(';')[0];

  // 2. Perform Login as Admin
  const loginRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'Cookie': phpSession
    },
    body: JSON.stringify({
      email: 'admin@kichbansat.vn',
      password: 'password123'
    })
  });

  assert.equal(loginRes.status, 200, 'Admin login returns 200 OK');
  const loginJson = await loginRes.json();
  assert.equal(loginJson.success, true);
  const userRole = loginJson.data.user.role_code || loginJson.data.user.role;
  assert.equal(userRole, 'ADMIN', 'Backend assigns role ADMIN');
  assert.equal(loginJson.data.user.email, 'admin@kichbansat.vn');

  const loginCookie = loginRes.headers.get('set-cookie') || phpSession;
  const activeSession = loginCookie.split(';')[0];

  // 3. Verify Session via /api/v1/auth/me
  const meRes = await fetch(`${BASE_URL}/api/v1/auth/me`, {
    headers: { 'Cookie': activeSession }
  });
  assert.equal(meRes.status, 200);
  const meJson = await meRes.json();
  assert.equal(meJson.success, true);
  const meRole = meJson.data.user.role_code || meJson.data.user.role;
  assert.equal(meRole, 'ADMIN');

  // 4. Verify Admin has access to admin users list
  const usersRes = await fetch(`${BASE_URL}/api/v1/admin/users`, {
    headers: { 'Cookie': activeSession }
  });
  assert.equal(usersRes.status, 200, 'Admin has access to /api/v1/admin/users');
  await usersRes.json();

  // 5. Logout
  const logoutRes = await fetch(`${BASE_URL}/api/v1/auth/logout`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': csrfToken,
      'Cookie': activeSession
    }
  });
  assert.equal(logoutRes.status, 200);
  await logoutRes.json();
});

test('5. Authentication Error Handling: Invalid credentials rejected', async () => {
  const csrfRes = await fetch(`${BASE_URL}/api/v1/auth/csrf-token`);
  assert.equal(csrfRes.status, 200);
  const csrfJson = await csrfRes.json();
  const csrfToken = csrfJson.data.csrf_token;
  const cookie = csrfRes.headers.get('set-cookie');
  const phpSession = cookie.split(';')[0];

  // Attempt login with invalid password
  const failRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'Cookie': phpSession
    },
    body: JSON.stringify({
      email: 'admin@kichbansat.vn',
      password: 'wrong_password_xyz'
    })
  });

  assert.equal(failRes.status, 401, 'Invalid login must return 401 Unauthorized');
  const failJson = await failRes.json();
  assert.equal(failJson.success, false);
  assert.ok(failJson.message.length > 0, 'Returns meaningful error message');
});

test('6. Security & Authorization: Player cannot access Admin API', async () => {
  // Login as Player
  const csrfRes = await fetch(`${BASE_URL}/api/v1/auth/csrf-token`);
  const csrfJson = await csrfRes.json();
  const csrfToken = csrfJson.data.csrf_token;
  const phpSession = csrfRes.headers.get('set-cookie').split(';')[0];

  const loginRes = await fetch(`${BASE_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'Cookie': phpSession
    },
    body: JSON.stringify({
      email: 'player.hieu@kichbansat.vn',
      password: 'password123'
    })
  });
  const playerCookie = (loginRes.headers.get('set-cookie') || phpSession).split(';')[0];

  // Attempt to access Admin endpoint as Player
  const forbidRes = await fetch(`${BASE_URL}/api/v1/admin/users`, {
    headers: { 'Cookie': playerCookie }
  });

  assert.equal(forbidRes.status, 403, 'Player accessing admin users must receive 403 Forbidden');
});
