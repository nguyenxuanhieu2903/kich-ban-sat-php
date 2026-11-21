import assert from 'node:assert/strict';
import test from 'node:test';

const BASE_URL = 'http://127.0.0.1:8000';

test('E2E: 1. Public Home Page HTML & Navigation structure', async () => {
  const res = await fetch(`${BASE_URL}/`);
  assert.equal(res.status, 200);
  const html = await res.text();

  // 1. Navigation elements
  assert.ok(html.includes('id="nav-btn-home"'), 'Contains Home nav button');
  assert.ok(html.includes('id="nav-btn-games"'), 'Contains Games nav button');
  assert.ok(html.includes('id="nav-btn-rooms"'), 'Contains Rooms nav button');
  assert.ok(html.includes('id="nav-btn-dm"'), 'Contains DM nav button');
  assert.ok(html.includes('id="nav-btn-admin"'), 'Contains Admin nav button');
  assert.ok(html.includes('id="btn-open-login"'), 'Contains Login CTA');

  // 2. Responsive Mobile Drawer & Hamburger
  assert.ok(html.includes('id="nav-hamburger-btn"'), 'Contains Mobile hamburger button');
  assert.ok(html.includes('id="mobile-nav-drawer"'), 'Contains Mobile navigation drawer');
  assert.ok(html.includes('href="#home"'), 'Contains links to #home');

  // 3. Mount point & CSS links
  assert.ok(html.includes('id="app-mount"'), 'Contains app mount point');
  assert.ok(html.includes('src/styles/pages.css'), 'Loads pages.css');
  assert.ok(html.includes('src/app.js'), 'Loads app.js');

  // 4. Public Footer multi-column & policies
  assert.ok(html.includes('class="footer-top"'), 'Contains footer top grid');
  assert.ok(html.includes('Chi Nhánh Hoạt Động'), 'Contains branches in footer');
  assert.ok(html.includes('Chính Sách & Quy Chuẩn V16'), 'Contains V16 policies in footer');
  assert.ok(html.includes('Cọc cố định: 50.000đ / người'), 'States 50k deposit rule');
  assert.ok(html.includes('Hoàn cọc 100% trước 24 giờ'), 'States 24h refund rule');
  assert.ok(html.includes('Không cộng dồn khuyến mãi'), 'States non-stacking rule');
});

test('E2E: 2. Game Catalog API (/api/v1/games)', async () => {
  const res = await fetch(`${BASE_URL}/api/v1/games`);
  assert.equal(res.status, 200);
  const json = await res.json();

  assert.equal(json.success, true);
  assert.ok(Array.isArray(json.data.games), 'Returns games array');
  assert.ok(json.data.games.length >= 3, 'Has at least 3 active games');

  const game = json.data.games[0];
  assert.ok(game.id, 'Has id');
  assert.ok(game.name, 'Has name');
  assert.ok(game.duration_minutes, 'Has duration_minutes');
  assert.ok(game.min_players, 'Has min_players');
  assert.ok(game.max_players, 'Has max_players');
  assert.ok(game.min_price > 0, 'Has calculated min_price from DB');
  assert.ok(game.max_price >= game.min_price, 'Has calculated max_price from DB');
});

test('E2E: 3. Game Discovery Search & Filter API', async () => {
  // Search by name
  const resSearch = await fetch(`${BASE_URL}/api/v1/games?search=Tuyết`);
  assert.equal(resSearch.status, 200);
  const jsonSearch = await resSearch.json();
  assert.equal(jsonSearch.success, true);
  assert.ok(jsonSearch.data.games.length >= 1);
  assert.ok(jsonSearch.data.games[0].name.includes('Tuyết'));

  // Filter by player count
  const resFilter = await fetch(`${BASE_URL}/api/v1/games?players=6`);
  assert.equal(resFilter.status, 200);
  const jsonFilter = await resFilter.json();
  assert.equal(jsonFilter.success, true);
  jsonFilter.data.games.forEach(g => {
    assert.ok(Number(g.min_players) <= 6 && Number(g.max_players) >= 6);
  });
});

test('E2E: 4. Promotions API (/api/v1/promotions)', async () => {
  const res = await fetch(`${BASE_URL}/api/v1/promotions`);
  assert.equal(res.status, 200);
  const json = await res.json();

  assert.equal(json.success, true);
  assert.ok(Array.isArray(json.data.promotions), 'Returns promotions array');
  assert.equal(json.data.promotions.length, 2, 'Returns exactly 2 active V16 promotions');

  const tuesdayPromo = json.data.promotions.find(p => p.code === 'TUESDAY_30');
  assert.ok(tuesdayPromo, 'Contains TUESDAY_30 promotion');
  assert.equal(tuesdayPromo.discount_type, 'PERCENT');
  assert.equal(tuesdayPromo.discount_value, 30);
  assert.deepEqual(tuesdayPromo.rules.weekdays, [2]);

  const hssvPromo = json.data.promotions.find(p => p.code === 'HSSV_20000');
  assert.ok(hssvPromo, 'Contains HSSV_20000 promotion');
  assert.equal(hssvPromo.discount_type, 'FIXED');
  assert.equal(hssvPromo.discount_value, 20000);
  assert.deepEqual(hssvPromo.rules.weekdays, [1, 2, 3, 4]);
});

test('E2E: 5. Game Price Modal Data (/api/v1/games/1/prices)', async () => {
  const res = await fetch(`${BASE_URL}/api/v1/games/1/prices`);
  assert.equal(res.status, 200);
  const json = await res.json();

  assert.equal(json.success, true);
  assert.equal(json.data.game_id, 1);
  assert.ok(Array.isArray(json.data.prices), 'Returns prices array');
  assert.ok(json.data.prices.length >= 3, 'Has daytime and nighttime slots');

  const daySlot = json.data.prices.find(p => p.start_time >= '09:00:00' && p.end_time <= '13:00:00');
  assert.ok(daySlot, 'Has 09:00 - 13:00 daytime price');
  assert.equal(daySlot.price_per_person, 180000);

  const nightSlot = json.data.prices.find(p => p.start_time >= '18:00:00');
  assert.ok(nightSlot, 'Has 18:00 - 23:00 peak/nighttime price');
  assert.equal(nightSlot.price_per_person, 250000);
});

test('E2E: 6. Security sanitization functions test', async () => {
  const { escapeHtml, sanitizeUrl } = await import('../src/utils/sanitize.js');

  // Test XSS escaping
  assert.equal(escapeHtml('<script>alert("XSS")</script>'), '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;');
  assert.equal(escapeHtml("Tom's & Jerry's"), 'Tom&#039;s &amp; Jerry&#039;s');
  assert.equal(escapeHtml(null), '');
  assert.equal(escapeHtml(undefined), '');

  // Test URL sanitization
  assert.equal(sanitizeUrl('https://images.unsplash.com/photo-1'), 'https://images.unsplash.com/photo-1');
  assert.equal(sanitizeUrl('http://example.com/pic.jpg'), 'http://example.com/pic.jpg');
  assert.equal(sanitizeUrl('/assets/banner.png'), '/assets/banner.png');
  assert.equal(sanitizeUrl('javascript:alert(1)'), '');
  assert.equal(sanitizeUrl('data:text/html,<script>alert(1)</script>'), '');
  assert.equal(sanitizeUrl(null), '');
});

test('E2E: 7. Static Module Scripts & Styles load correctly', async () => {
  const scripts = [
    '/src/app.js',
    '/src/pages/home/home-view.js',
    '/src/pages/games/games-view.js',
    '/src/pages/rooms/rooms-view.js',
    '/src/pages/dm/dm-view.js',
    '/src/pages/admin/admin-view.js',
    '/src/api/api-client.js',
    '/src/api/auth-state.js',
    '/src/utils/sanitize.js',
    '/src/styles/variables.css',
    '/src/styles/components.css',
    '/src/styles/pages.css'
  ];

  for (const script of scripts) {
    const res = await fetch(`${BASE_URL}${script}`);
    assert.equal(res.status, 200, `Failed to fetch ${script}`);
    const text = await res.text();
    assert.ok(text.length > 0, `Empty content for ${script}`);
  }
});
