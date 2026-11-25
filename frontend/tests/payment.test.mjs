import assert from 'node:assert/strict';
import test from 'node:test';
import {
  DEPOSIT_VND,
  PAYMENT_STATUS,
  canPayDeposit,
  canPayRemaining,
  mapPaymentStatus,
  normalizePayment,
  remainingAfterConfirmedDeposit,
  pickOwnPayment,
} from '../src/logic/payment.js';
import { reminderBucket } from '../src/logic/timer.engine.js';

test('cọc cố định 50.000đ, còn lại = giá cuối − cọc đã xác nhận', () => {
  assert.equal(DEPOSIT_VND, 50000);
  assert.equal(remainingAfterConfirmedDeposit(150000, true), 100000);
  assert.equal(remainingAfterConfirmedDeposit(150000, false), 150000);
});

test('ánh xạ trạng thái thanh toán từng người chơi', () => {
  assert.equal(mapPaymentStatus('waiting_for_deposit'), PAYMENT_STATUS.AWAITING);
  assert.equal(mapPaymentStatus('deposit_paid'), PAYMENT_STATUS.DEPOSIT_PAID);
  assert.equal(mapPaymentStatus('expired'), PAYMENT_STATUS.OVERDUE);
  assert.equal(mapPaymentStatus('paid_in_full'), PAYMENT_STATUS.FULLY_PAID);
  assert.equal(mapPaymentStatus('leave_pending'), PAYMENT_STATUS.REFUND_PENDING);
});

test('chỉ cho đặt cọc khi đang chờ cọc', () => {
  const awaiting = normalizePayment({ status: 'awaiting_deposit', total: 150000 });
  const paid = normalizePayment({ status: 'deposit_paid', total: 150000 });
  assert.equal(canPayDeposit(awaiting), true);
  assert.equal(canPayDeposit(paid), false);
  assert.equal(canPayRemaining(paid), true);
  assert.equal(paid.remaining, 100000);
});

test('không lấy payment của người khác khi đã biết user id', () => {
  const own = pickOwnPayment([
    { user_id: '2', status: 'deposit_paid' },
    { user_id: '9', status: 'awaiting_deposit' },
  ], '9');
  assert.equal(own.user_id, '9');
  assert.equal(pickOwnPayment([{ user_id: '2' }], '9'), null);
});

test('nhắc trước 1 giờ và 30 phút', () => {
  assert.equal(reminderBucket(61 * 60000), null);
  assert.equal(reminderBucket(59 * 60000), '1h');
  assert.equal(reminderBucket(29 * 60000), '30m');
  assert.equal(reminderBucket(0), null);
});
