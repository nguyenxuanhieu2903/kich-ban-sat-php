import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

test('DEF-27: rooms-view.js contains remaining payment UI and event triggers', async () => {
  const filePath = path.resolve('frontend/src/pages/rooms/rooms-view.js');
  const content = fs.readFileSync(filePath, 'utf-8');

  // 1. Verify remaining payment button and card elements exist
  assert.ok(content.includes('id="btn-pay-remaining"'), 'Contains btn-pay-remaining button');
  assert.ok(content.includes('Thanh Toán Số Tiền Còn Lại'), 'Contains remaining payment header text');
  assert.ok(content.includes('handlePayRemaining'), 'Contains handlePayRemaining handler');

  // 2. Verify API endpoint and payment method
  assert.ok(content.includes('/payments/'), 'Calls payments API endpoint');
  assert.ok(content.includes('/remaining'), 'Calls remaining payment action endpoint');
  assert.ok(content.includes('SIMULATED_VNPAY'), 'Uses SIMULATED_VNPAY payment method');
  assert.ok(content.includes('X-Idempotency-Key'), 'Mentions Idempotency-Key protection');

  // 3. Verify state-based conditional rendering (SCHEDULED + DEPOSITED)
  assert.ok(content.includes('isScheduled && hasDeposited'), 'Renders remaining payment card only when room is scheduled and user deposited');
  assert.ok(content.includes('isRemainingPaid'), 'Checks whether remaining payment is already completed');
});
