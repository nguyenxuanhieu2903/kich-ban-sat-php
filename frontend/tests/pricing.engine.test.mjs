import assert from 'node:assert/strict';
import test from 'node:test';
import { quotePrice } from '../src/logic/pricing.engine.js';

test('Thứ 3 giảm 30% và được chọn vì lợi hơn HSSV', () => {
  const quote = quotePrice({
    basePrice: 220000,
    playDate: '2026-11-24',
    playTime: '10:00',
    hssvApproved: true,
  });
  assert.equal(quote.applied, 'tuesday');
  assert.equal(quote.base, 220000);
  assert.equal(quote.discount, 66000);
  assert.equal(quote.total, 154000);
  assert.equal(quote.deposit, 50000);
  assert.equal(quote.remaining, 104000);
});

test('HSSV được chọn khi không phải Thứ 3 và nằm trong khung giờ', () => {
  const quote = quotePrice({
    basePrice: 190000,
    playDate: '2026-11-26',
    playTime: '19:59',
    hssvApproved: true,
  });
  assert.equal(quote.applied, 'hssv');
  assert.equal(quote.discount, 20000);
  assert.equal(quote.total, 170000);
  assert.equal(quote.remaining, 120000);
});

test('HSSV không áp dụng ngoài khung giờ hoặc khi chưa duyệt', () => {
  const late = quotePrice({
    basePrice: 190000,
    playDate: '2026-11-26',
    playTime: '20:00',
    hssvApproved: true,
  });
  const pending = quotePrice({
    basePrice: 190000,
    playDate: '2026-11-26',
    playTime: '10:00',
    hssvApproved: false,
  });
  assert.equal(late.applied, 'none');
  assert.equal(late.discount, 0);
  assert.equal(pending.discount, 0);
  assert.equal(pending.total, 190000);
});

test('không có giá thì không bịa bảng giá', () => {
  assert.equal(quotePrice({ basePrice: null, playDate: '2026-11-24', playTime: '10:00' }), null);
});
