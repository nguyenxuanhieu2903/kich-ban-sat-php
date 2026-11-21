/**
 * DM Portal E2E & Business Logic Test Suite (V15/V16 Freeze)
 * Verifies DMAssignment lifecycle, 12h buffer check, Availability Matrix, Attendance, and In-Store Payment.
 */
import { test, describe, before, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { sharedStore } from '../src/api/demo/shared-store.js';
import { demoApi } from '../src/api/demo/demo-api.js';
import { auth, ROLES, DEMO_USERS } from '../src/utils/auth.js';
import { checkDmPrepBuffer, isReasonRequired } from '../src/utils/rules.js';
import { DM_ASSIGNMENT_STATUS, GAMEROOM_STATUS, SESSION_STATUS } from '../src/utils/status.js';

describe('DM Portal E2E — Master Command Station Logic Tests', () => {
  beforeEach(() => {
    // Reset to clean store
    sharedStore.reset();
    auth.switchDemoRole(ROLES.DM);
  });

  test('1. DM Rejection: Requires mandatory reason >= 5 chars, reverts room to WAITING_FOR_DM, never cancels room', async () => {
    const dmId = 'user_dm_01';
    // room_002 has a pending assignment for user_dm_01
    // Let's create an assignment in a room if needed
    const room = sharedStore.data.gameRooms.find((r) => r.status === GAMEROOM_STATUS.WAITING_FOR_DM) || sharedStore.data.gameRooms[0];
    room.status = GAMEROOM_STATUS.WAITING_FOR_DM;
    sharedStore.adminAssignDm(room.id, dmId);

    assert.equal(isReasonRequired('DM_REJECT_ASSIGNMENT'), true);

    // Attempt rejection with empty reason -> must throw
    assert.throws(() => {
      sharedStore.dmRespondAssignment(room.id, dmId, false, '');
    }, /Bắt buộc phải cung cấp lý do/);

    // Attempt rejection with short reason (< 5 chars) -> must throw
    assert.throws(() => {
      sharedStore.dmRespondAssignment(room.id, dmId, false, 'bận');
    }, /tối thiểu 5 ký tự/);

    // Valid rejection with explanation
    const rejectionReason = 'Trùng lịch dạy học ngoại khóa đột xuất tại trường';
    const updated = sharedStore.dmRespondAssignment(room.id, dmId, false, rejectionReason);

    assert.equal(updated.dm_assignment.status, DM_ASSIGNMENT_STATUS.REJECTED);
    assert.equal(updated.dm_assignment.rejected_reason, rejectionReason);
    // CRITICAL: Room is NOT CANCELLED, it reverts to WAITING_FOR_DM
    assert.equal(updated.status, GAMEROOM_STATUS.WAITING_FOR_DM);

    // Admin should receive notification
    const adminNotif = sharedStore.data.notifications.find((n) => n.user_id === 'user_admin_01' && n.type === 'DM_REJECTED');
    assert.ok(adminNotif);
    assert.ok(adminNotif.message.includes(rejectionReason));

    // Audit log recorded
    const audit = sharedStore.data.auditLogs.find((a) => a.action === 'REJECT_ASSIGNMENT');
    assert.ok(audit);
    assert.equal(audit.actor_id, dmId);
  });

  test('2. DM Acceptance: Calculates 12h buffer, updates room to WAITING_FOR_ORGANIZATION', async () => {
    const dmId = 'user_dm_01';
    const room = sharedStore.data.gameRooms[1]; // room_002
    room.status = GAMEROOM_STATUS.WAITING_FOR_DM;
    // Set session start time 24h ahead
    room.session_start_time = new Date(Date.now() + 24 * 3600 * 1000).toISOString();
    sharedStore.adminAssignDm(room.id, dmId);

    const bufferCheck = checkDmPrepBuffer(room.session_start_time);
    assert.equal(bufferCheck.valid, true);
    assert.equal(bufferCheck.violated, false);
    assert.ok(bufferCheck.hoursRemaining >= 23);

    const updated = sharedStore.dmRespondAssignment(room.id, dmId, true);
    assert.equal(updated.dm_assignment.status, DM_ASSIGNMENT_STATUS.ACCEPTED);
    assert.equal(updated.status, GAMEROOM_STATUS.WAITING_FOR_ORGANIZATION);
    assert.equal(updated.dm_assignment.prep_buffer_warning, false);

    // Audit log recorded
    const audit = sharedStore.data.auditLogs.find((a) => a.action === 'ACCEPT_ASSIGNMENT');
    assert.ok(audit);
    assert.equal(audit.actor_id, dmId);
  });

  test('3. Availability Matrix: Persists schedule, validates days and shifts, generates audit log', async () => {
    const dmId = 'user_dm_01';
    const initialSchedule = await demoApi.getDmAvailability(dmId);
    assert.ok(initialSchedule.data);

    // Update schedule
    const newSchedule = { ...initialSchedule.data };
    newSchedule[1] = { morning: false, afternoon: false, night: true }; // T2 only night
    newSchedule[6] = { morning: true, afternoon: true, night: true };  // T7 all shifts

    const saveRes = await demoApi.saveDmAvailability(newSchedule, dmId);
    assert.equal(saveRes.success, true);

    // Retrieve again to verify persistence in store
    const retrieved = await demoApi.getDmAvailability(dmId);
    assert.equal(retrieved.data[1].morning, false);
    assert.equal(retrieved.data[1].night, true);
    assert.equal(retrieved.data[6].morning, true);

    // Audit log recorded
    const audit = sharedStore.data.auditLogs.find((a) => a.action === 'UPDATE_DM_AVAILABILITY');
    assert.ok(audit);
    assert.equal(audit.actor_id, dmId);
  });

  test('4. Attendance & In-Store Payment: Marks attendance, collects remaining fee, blocks double payment', async () => {
    const dmId = 'user_dm_01';
    // Room with active accepted DM
    const room = sharedStore.data.gameRooms.find((r) => r.dm_assignment?.dm_id === dmId && r.dm_assignment?.status === 'ACCEPTED');
    assert.ok(room);

    const participant = room.participants[0];
    assert.ok(participant);

    // 1. Mark attendance
    await demoApi.updateParticipantAttendance(room.id, participant.id, true, 'Có mặt đúng giờ');
    const updatedRoom = sharedStore.data.gameRooms.find((r) => r.id === room.id);
    const pAfterAttendance = updatedRoom.participants.find((p) => p.id === participant.id);
    assert.equal(pAfterAttendance.attendance, 'PRESENT');
    assert.equal(pAfterAttendance.attendance_note, 'Có mặt đúng giờ');

    // 2. Collect remaining in-store payment (e.g. 130.000 VNĐ)
    const paymentRes = await demoApi.confirmRemainingPayment(room.id, participant.id, 130000);
    assert.equal(paymentRes.success, true);
    assert.ok(paymentRes.data.receipt_code.startsWith('REC-'));
    assert.equal(paymentRes.data.amount, 130000);

    // Check participant record
    const pAfterPayment = updatedRoom.participants.find((p) => p.id === participant.id);
    assert.equal(pAfterPayment.remaining_payment.status, 'PAID');
    assert.equal(pAfterPayment.remaining_payment.amount, 130000);
    assert.equal(pAfterPayment.remaining_payment.deposit_deducted, 50000);

    // 3. Prevent duplicate payment
    await assert.rejects(async () => {
      await demoApi.confirmRemainingPayment(room.id, participant.id, 130000);
    }, /Không thể thu trùng/);

    // Audit logs recorded
    const attAudit = sharedStore.data.auditLogs.find((a) => a.action === 'RECORD_ATTENDANCE');
    assert.ok(attAudit);
    const payAudit = sharedStore.data.auditLogs.find((a) => a.action === 'COLLECT_REMAINING_PAYMENT');
    assert.ok(payAudit);
  });

  test('5. Live Session Lifecycle: DM starts and completes session', async () => {
    const dmId = 'user_dm_01';
    const room = sharedStore.data.gameRooms.find((r) => r.dm_assignment?.dm_id === dmId && r.dm_assignment?.status === 'ACCEPTED');
    assert.ok(room);

    // Start session
    const startRes = await demoApi.updateSessionStatus(room.id, SESSION_STATUS.IN_PROGRESS);
    assert.equal(startRes.success, true);
    assert.equal(startRes.data.session.status, SESSION_STATUS.IN_PROGRESS);
    assert.ok(startRes.data.session.started_at);

    // Complete session
    const completeRes = await demoApi.updateSessionStatus(room.id, SESSION_STATUS.COMPLETED);
    assert.equal(completeRes.success, true);
    assert.equal(completeRes.data.session.status, SESSION_STATUS.COMPLETED);
    assert.equal(completeRes.data.status, GAMEROOM_STATUS.COMPLETED);
    assert.ok(completeRes.data.session.completed_at);
  });
});
