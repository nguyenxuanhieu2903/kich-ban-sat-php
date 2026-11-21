import assert from 'node:assert';
import test from 'node:test';

import { formatCurrency, calculateCountdown } from '../src/utils/format.js';
import { getStatusMeta, isTerminal } from '../src/utils/status.js';
import { canAccess } from '../src/utils/router.js';
import { isValidSlotTime, validateReplacementWindow, isReasonRequired } from '../src/utils/rules.js';
import { simulateQuote } from '../src/api/demo/quote-simulator.js';

test('T-01: formatCurrency produces standard Vietnamese currency formatting', () => {
  const formatted50k = formatCurrency(50000);
  assert.ok(formatted50k.includes('50.000') || formatted50k.includes('50,000'));
  assert.ok(formatted50k.includes('₫') || formatted50k.includes('VND'));

  assert.strictEqual(formatCurrency(0), '0\u00a0₫');
});

test('T-02: calculateCountdown accurately handles active and expired times', () => {
  const past = new Date(Date.now() - 10000);
  const resPast = calculateCountdown(past);
  assert.strictEqual(resPast.isExpired, true);
  assert.strictEqual(resPast.formatted, '00:00:00');

  const future = new Date(Date.now() + 3661000); // 1h 1m 1s
  const resFuture = calculateCountdown(future);
  assert.strictEqual(resFuture.isExpired, false);
  assert.strictEqual(resFuture.hours, 1);
  assert.strictEqual(resFuture.minutes, 1);
});

test('T-03: getStatusMeta covers official enums and handles unknown status gracefully', () => {
  const forming = getStatusMeta('FORMING');
  assert.strictEqual(forming.label, 'Đang tuyển người');
  assert.strictEqual(forming.color, 'amber');

  const deposited = getStatusMeta('DEPOSITED');
  assert.strictEqual(deposited.label, 'Đã đặt cọc');

  const unknown = getStatusMeta('CUSTOM_SPECIAL_STATE');
  assert.strictEqual(unknown.label, 'CUSTOM SPECIAL STATE');
  assert.strictEqual(unknown.color, 'muted');
});

test('T-04: isTerminal identifies terminal states across domains', () => {
  assert.strictEqual(isTerminal('GameRoom', 'COMPLETED'), true);
  assert.strictEqual(isTerminal('GameRoom', 'CANCELLED'), true);
  assert.strictEqual(isTerminal('GameRoom', 'FORMING'), false);

  assert.strictEqual(isTerminal('RoomParticipant', 'LEFT'), true);
  assert.strictEqual(isTerminal('RoomParticipant', 'PENDING_DEPOSIT'), false);
});

test('T-05: canAccess correctly guards routes by role', () => {
  // Public
  assert.strictEqual(canAccess('PLAYER', '/'), true);
  assert.strictEqual(canAccess('GUEST', '/games'), true);
  assert.strictEqual(canAccess('GUEST', '/rooms'), true);

  // Player Portal
  assert.strictEqual(canAccess('PLAYER', '/player/dashboard'), true);
  assert.strictEqual(canAccess('DM', '/player/dashboard'), false);
  assert.strictEqual(canAccess('ADMIN', '/player/dashboard'), false);

  // DM Portal
  assert.strictEqual(canAccess('DM', '/dm/dashboard'), true);
  assert.strictEqual(canAccess('PLAYER', '/dm/dashboard'), false);

  // Admin Portal
  assert.strictEqual(canAccess('ADMIN', '/admin/dashboard'), true);
  assert.strictEqual(canAccess('PLAYER', '/admin/dashboard'), false);
  assert.strictEqual(canAccess('DM', '/admin/dashboard'), false);
});

test('T-06: isValidSlotTime validates only :00 and :30 minute boundaries', () => {
  assert.strictEqual(isValidSlotTime('09:00'), true);
  assert.strictEqual(isValidSlotTime('14:30'), true);
  assert.strictEqual(isValidSlotTime('20:00'), true);

  assert.strictEqual(isValidSlotTime('09:15'), false);
  assert.strictEqual(isValidSlotTime('14:45'), false);
  assert.strictEqual(isValidSlotTime('invalid'), false);
});

test('T-07: isReasonRequired validates mandatory reason actions', () => {
  assert.strictEqual(isReasonRequired('DM_REJECT_ASSIGNMENT'), true);
  assert.strictEqual(isReasonRequired('ADMIN_CANCEL_ROOM'), true);
  assert.strictEqual(isReasonRequired('ADMIN_REOPEN_RECRUITMENT'), true);
  assert.strictEqual(isReasonRequired('ADMIN_REJECT_REFUND'), true);

  assert.strictEqual(isReasonRequired('PLAYER_JOIN_ROOM'), false);
});

test('T-08: validateReplacementWindow enforces 30m - 4h boundary and Session start ceiling', () => {
  const now = new Date('2026-11-20T10:00:00');
  const sessionStart = '2026-11-20T14:00:00'; // 4 hours later

  assert.strictEqual(validateReplacementWindow(20, sessionStart, now).valid, false); // < 30m
  assert.strictEqual(validateReplacementWindow(300, sessionStart, now).valid, false); // > 4h
  assert.strictEqual(validateReplacementWindow(120, sessionStart, now).valid, true);

  // Exceeds session start (window 180m from 12:00 when session is 14:00)
  const nowLate = new Date('2026-11-20T12:00:00');
  assert.strictEqual(validateReplacementWindow(180, sessionStart, nowLate).valid, false);
});

test('T-09: simulateQuote accurately calculates Day vs Night, fixed 50k deposit, and promos', () => {
  const dummyGame = {
    id: 'game_test',
    title: 'Test Game',
    price_day_vnd: 200000,
    price_night_vnd: 230000,
  };

  // Day quote on Friday (no promo)
  const fridayDay = '2026-11-20T14:00:00'; // Friday 14:00
  const qDay = simulateQuote({
    game: dummyGame,
    sessionStartTime: fridayDay,
    playerCount: 6,
    isStudentVerified: false,
    bookingCreatedTime: '2026-11-18T10:00:00', // Advance > 24h
  });

  assert.strictEqual(qDay.pricing_snapshot.base_price_per_person, 200000);
  assert.strictEqual(qDay.pricing_snapshot.is_night, false);
  assert.strictEqual(qDay.pricing_snapshot.raw_total_vnd, 1200000);
  assert.strictEqual(qDay.pricing_snapshot.deposit_per_person_vnd, 50000);
  assert.strictEqual(qDay.pricing_snapshot.total_deposit_vnd, 300000); // 50k x 6
  assert.strictEqual(qDay.pricing_snapshot.remaining_vnd, 900000);

  // Tuesday Promo (-30%)
  const tuesdayDay = '2026-11-24T14:00:00'; // Tuesday 14:00
  const qTuesday = simulateQuote({
    game: dummyGame,
    sessionStartTime: tuesdayDay,
    playerCount: 6,
    isStudentVerified: false,
    bookingCreatedTime: '2026-11-20T10:00:00', // Advance > 24h
  });

  assert.strictEqual(qTuesday.pricing_snapshot.total_discount_vnd, 360000); // 30% of 1.2M
  assert.strictEqual(qTuesday.pricing_snapshot.final_total_vnd, 840000);
  assert.strictEqual(qTuesday.pricing_snapshot.total_deposit_vnd, 300000);
  assert.strictEqual(qTuesday.pricing_snapshot.remaining_vnd, 540000);

  // Promotion Conflict (Tuesday + Student verified simultaneously) -> NO auto-stacking, acknowledges Open Decision #7
  const qConflict = simulateQuote({
    game: dummyGame,
    sessionStartTime: tuesdayDay,
    playerCount: 6,
    isStudentVerified: true,
    bookingCreatedTime: '2026-11-20T10:00:00',
  });

  assert.strictEqual(qConflict.pricing_snapshot.has_promotion_conflict, true);
  assert.strictEqual(qConflict.pricing_snapshot.total_discount_vnd, 0); // Preserves base price until decided
});
