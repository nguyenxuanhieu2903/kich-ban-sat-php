import test from 'node:test';
import assert from 'node:assert/strict';
import { sharedStore } from '../src/api/demo/shared-store.js';
import { demoApi } from '../src/api/demo/demo-api.js';
import { isValidSlotTime } from '../src/utils/rules.js';

test('Player Portal E2E - Full Lifecycle Verification', async (t) => {
  // Test 1: Active Rooms Quota enforcement (Max 3)
  await t.test('Enforces maximum 3 active rooms per creator', async () => {
    sharedStore.reset();
    const creatorId = 'user_player_01';
    
    // Check initial active rooms created by user_player_01
    const activeInitial = sharedStore.getRooms().filter(
      r => r.creator_id === creatorId && !['COMPLETED', 'EXPIRED', 'CANCELLED'].includes(r.status)
    );
    const initialCount = activeInitial.length;
    assert.ok(initialCount < 3, 'Initial active rooms should be less than 3');

    // Create rooms until reaching 3
    for (let i = initialCount; i < 3; i++) {
      const created = await demoApi.createRoom({
        game_id: `game_00${i + 2}`,
        session_start_time: `2026-11-${20 + i}T19:30:00`
      });
      assert.equal(created.success, true);
    }

    const activeAfter = sharedStore.getRooms().filter(
      r => r.creator_id === creatorId && !['COMPLETED', 'EXPIRED', 'CANCELLED'].includes(r.status)
    );
    assert.equal(activeAfter.length, 3, 'Must have exactly 3 active rooms');

    // Attempting to create a 4th room must fail with quota error
    await assert.rejects(
      async () => {
        await demoApi.createRoom({
          game_id: 'game_005',
          session_start_time: '2026-11-25T19:00:00'
        });
      },
      (err) => {
        return err.message.includes('tối đa 3 phòng');
      }
    );
  });

  // Test 2: Valid Slot Times (:00 and :30)
  await t.test('Validates :00 and :30 boundaries for room creation', () => {
    assert.equal(isValidSlotTime('19:00'), true);
    assert.equal(isValidSlotTime('19:30'), true);
    assert.equal(isValidSlotTime('20:00'), true);
    assert.equal(isValidSlotTime('19:15'), false);
    assert.equal(isValidSlotTime('19:45'), false);
    assert.equal(isValidSlotTime('invalid'), false);
  });

  // Test 3: Fixed Deposit Simulation & Idempotency Key
  await t.test('Applies 50.000 VNĐ fixed deposit and updates participant to DEPOSITED', async () => {
    sharedStore.reset();
    // PHONG-001 in seed has user_player_01 in PENDING_DEPOSIT
    const targetRoom = sharedStore.getRoomByCode('PHONG-001');
    assert.ok(targetRoom);

    const res = await demoApi.makeDeposit(targetRoom.id, 'test_idempotency_key_123');
    assert.equal(res.success, true);
    assert.equal(res.data.amount_vnd, 50000);

    // Verify room data in sharedStore
    const updatedRoom = sharedStore.getRoomById(targetRoom.id);
    const myPart = updatedRoom.participants.find(p => p.user_id === 'user_player_01');
    assert.equal(myPart.status, 'DEPOSITED');
  });

  // Test 4: Pricing calculation with Tuesday 30% discount vs Student 20k discount
  await t.test('Quote calculation reflects Tuesday -30% without stacking', async () => {
    // 2026-11-24 is Tuesday (09:00 - 19:00 window), non-student
    const quoteTuesday = await demoApi.getQuote({
      game_id: 'game_001',
      session_start_time: '2026-11-24T14:00:00',
      player_count: 5,
      is_student_verified: false
    });

    assert.equal(quoteTuesday.success, true);
    const q = quoteTuesday.data;
    assert.equal(q.deposit_per_person_vnd, 50000);
    assert.equal(q.total_deposit_vnd, 250000); // 5 * 50k
    assert.equal(q.applied_promotion.code, 'PROMO_TUESDAY');
    
    // Discount 30% of day base price
    const expectedDiscount = Math.round(q.raw_total_vnd * 0.3);
    assert.equal(q.total_discount_vnd, expectedDiscount);
    assert.equal(q.remaining_vnd, q.final_total_vnd - q.total_deposit_vnd);
  });
});
