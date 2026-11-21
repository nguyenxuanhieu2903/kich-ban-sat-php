import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { sharedStore } from '../src/api/demo/shared-store.js';
import { demoApi } from '../src/api/demo/demo-api.js';
import { GAMEROOM_STATUS, DM_ASSIGNMENT_STATUS } from '../src/utils/status.js';

describe('Admin Portal — Tactical Ops Center Logic Tests', () => {
  beforeEach(() => {
    // Reset to fresh demo data
    sharedStore.reset();
  });

  describe('1. DM Assignment & Revocation with Immutable History', () => {
    test('Revocation only allowed on PENDING or ACCEPTED status; requires reason >= 5 chars', () => {
      // Create a test room
      const testRoom = {
        id: 'room_test_revoke',
        code: 'REV-001',
        game_id: 'game_01',
        session_start_time: new Date(Date.now() + 24 * 3600 * 1000).toISOString(),
        status: GAMEROOM_STATUS.WAITING_FOR_DM,
        participants: [
          { user_id: 'p1', user_name: 'Player 1', status: 'CONFIRMED' },
          { user_id: 'p2', user_name: 'Player 2', status: 'CONFIRMED' },
        ],
        dm_assignment: null,
        assignment_history: [],
      };
      sharedStore.data.gameRooms.push(testRoom);

      // Attempt to revoke when no assignment exists -> should fail
      assert.throws(
        () => sharedStore.adminRevokeDm('room_test_revoke', 'Lý do hợp lệ', { id: 'admin1', name: 'Admin', role: 'ADMIN' }),
        /Chỉ có thể thu hồi phân công Quản trò đang ở trạng thái PENDING hoặc ACCEPTED/
      );

      // Assign a DM (user_dm_01 is an active DM in demo staff)
      sharedStore.adminAssignDm('room_test_revoke', 'user_dm_01', { id: 'admin1', name: 'Admin', role: 'ADMIN' });
      assert.equal(testRoom.dm_assignment.status, DM_ASSIGNMENT_STATUS.PENDING);

      // Attempt revocation without reason or < 5 chars -> should fail
      assert.throws(
        () => sharedStore.adminRevokeDm('room_test_revoke', 'ngắn', { id: 'admin1', name: 'Admin', role: 'ADMIN' }),
        /Bắt buộc phải cung cấp lý do cụ thể/
      );

      // Successful revocation
      const reason = 'Quản trò bận việc đột xuất gia đình, đổi phân công';
      const updated = sharedStore.adminRevokeDm('room_test_revoke', reason, { id: 'admin1', name: 'Admin', role: 'ADMIN' });

      // Room should revert to WAITING_FOR_DM and dm_assignment cleared
      assert.equal(updated.status, GAMEROOM_STATUS.WAITING_FOR_DM);
      assert.equal(updated.dm_assignment, null);

      // Assignment history should contain an immutable snapshot with CANCELLED status
      assert.equal(updated.assignment_history.length, 1);
      const snapshot = updated.assignment_history[0];
      assert.equal(snapshot.dm_id, 'user_dm_01');
      assert.equal(snapshot.status, DM_ASSIGNMENT_STATUS.CANCELLED);
      assert.equal(snapshot.revocation_reason, reason);
      assert.ok(snapshot.revoked_at);

      // Check notification generated for the DM
      const notif = sharedStore.data.notifications.find(
        (n) => n.user_id === 'user_dm_01' && n.type === 'DM_ASSIGNMENT_REVOKED'
      );
      assert.ok(notif, 'Notification should be created for revoked DM');
      assert.ok(notif.message.includes(reason));

      // Check audit log
      const audit = sharedStore.data.auditLogs.find(
        (a) => a.action === 'ADMIN_REVOKE_ASSIGNMENT' && a.target_id === snapshot.id
      );
      assert.ok(audit, 'Audit log should record ADMIN_REVOKE_ASSIGNMENT');
    });

    test('Enforces hard block on DM assignment if session start time < 12h', () => {
      const urgentRoom = {
        id: 'room_urgent_buffer',
        code: 'BUF-001',
        game_id: 'game_01',
        session_start_time: new Date(Date.now() + 6 * 3600 * 1000).toISOString(), // 6h away < 12h
        status: GAMEROOM_STATUS.WAITING_FOR_DM,
        participants: [],
      };
      sharedStore.data.gameRooms.push(urgentRoom);

      assert.throws(
        () => sharedStore.adminAssignDm('room_urgent_buffer', 'user_dm_01', { id: 'admin1', name: 'Admin', role: 'ADMIN' }),
        /Vi phạm quy tắc V16 Freeze: Quản trò bắt buộc có tối thiểu 12 giờ chuẩn bị/
      );
    });
  });

  describe('2. Physical Venue Scheduling, Capacity & 30-min Cleanup Buffer Conflicts', () => {
    test('Enforces capacity >= active players + 1 DM', () => {
      // Find room_phy_02 (capacity 8)
      const phyRoom = sharedStore.data.physicalRooms.find((p) => p.id === 'room_phy_02'); // Capacity 8
      assert.ok(phyRoom);

      // Create a room with 8 players -> total occupants = 8 + 1 DM = 9 > 8 capacity
      const bigRoom = {
        id: 'room_too_many_people',
        code: 'BIG-001',
        game_id: 'game_01',
        session_start_time: new Date(Date.now() + 30 * 3600 * 1000).toISOString(),
        status: GAMEROOM_STATUS.WAITING_FOR_ORGANIZATION,
        participants: [
          { user_id: 'p1', status: 'CONFIRMED' },
          { user_id: 'p2', status: 'CONFIRMED' },
          { user_id: 'p3', status: 'CONFIRMED' },
          { user_id: 'p4', status: 'CONFIRMED' },
          { user_id: 'p5', status: 'CONFIRMED' },
          { user_id: 'p6', status: 'CONFIRMED' },
          { user_id: 'p7', status: 'CONFIRMED' },
          { user_id: 'p8', status: 'CONFIRMED' },
        ],
      };
      sharedStore.data.gameRooms.push(bigRoom);

      assert.throws(
        () => sharedStore.adminConfirmVenueAndSchedule('room_too_many_people', 'room_phy_02', { id: 'admin1', name: 'Admin', role: 'ADMIN' }),
        /không đủ cho 8 thám tử và 1 Quản trò DM/
      );
    });

    test('Enforces 30-min cleanup buffer conflict detection and excludes cancelled rooms', () => {
      const baseTime = Date.now() + 48 * 3600 * 1000;
      // Session 1: 14:00, duration 240m (4 hours) -> Session ends 18:00.
      // With 30-min cleanup buffer, room is blocked until 18:30.
      const room1Time = new Date(baseTime).toISOString();

      const room1 = {
        id: 'room_sched_1',
        code: 'SCH-001',
        game_id: 'game_01', // 240m
        session_start_time: room1Time,
        status: GAMEROOM_STATUS.WAITING_FOR_ORGANIZATION,
        participants: [{ user_id: 'p1', status: 'CONFIRMED' }],
      };
      sharedStore.data.gameRooms.push(room1);

      // Successfully schedule room 1 in room_phy_01 (capacity 10)
      sharedStore.adminConfirmVenueAndSchedule('room_sched_1', 'room_phy_01', { id: 'admin1', name: 'Admin', role: 'ADMIN' });
      assert.equal(room1.status, GAMEROOM_STATUS.WAITING_FOR_DEPOSIT);
      assert.equal(room1.physical_room.id, 'room_phy_01');

      // Room 2 starts at 18:15 (which is before 18:30 buffer ends) -> Conflict!
      const room2Time = new Date(baseTime + (4 * 60 + 15) * 60 * 1000).toISOString();
      const room2 = {
        id: 'room_sched_2',
        code: 'SCH-002',
        game_id: 'game_01',
        session_start_time: room2Time,
        status: GAMEROOM_STATUS.WAITING_FOR_ORGANIZATION,
        participants: [{ user_id: 'p2', status: 'CONFIRMED' }],
      };
      sharedStore.data.gameRooms.push(room2);

      assert.throws(
        () => sharedStore.adminConfirmVenueAndSchedule('room_sched_2', 'room_phy_01', { id: 'admin1', name: 'Admin', role: 'ADMIN' }),
        /Xung đột lịch mật thất/
      );

      // Now cancel room 1 -> Room 2 should no longer conflict!
      room1.status = GAMEROOM_STATUS.CANCELLED;

      assert.doesNotThrow(() => {
        sharedStore.adminConfirmVenueAndSchedule('room_sched_2', 'room_phy_01', { id: 'admin1', name: 'Admin', role: 'ADMIN' });
      });
      assert.equal(room2.status, GAMEROOM_STATUS.WAITING_FOR_DEPOSIT);
    });
  });

  describe('3. Refund Approval & Exact 24.0h Boundary', () => {
    test('Exact 24.0h qualifies for refund; < 24.0h is ineligible; duplicate refund is blocked; FAILED can retry', () => {
      const now = Date.now();
      const sessionTime = new Date(now + 24 * 3600 * 1000).toISOString(); // Exactly 24h away

      const room = {
        id: 'room_refund_test',
        code: 'REF-001',
        session_start_time: sessionTime,
        status: GAMEROOM_STATUS.CANCELLED,
        participants: [],
      };
      sharedStore.data.gameRooms.push(room);

      // Refund created right now (exactly 24.0h difference)
      const eligibleRefund = {
        id: 'ref_exact_24h',
        room_id: 'room_refund_test',
        room_code: 'REF-001',
        user_id: 'user_player_01',
        amount_vnd: 50000,
        status: 'REQUESTED',
        created_at: new Date(now).toISOString(),
      };
      sharedStore.data.refunds.push(eligibleRefund);

      // Process approval: exactly 24.0h should succeed
      const approved = sharedStore.adminProcessRefund('ref_exact_24h', true, 'Đủ điều kiện hủy trước 24h', {
        id: 'admin1',
        name: 'Admin',
        role: 'ADMIN',
      });
      assert.equal(approved.status, 'SUCCEEDED');
      assert.ok(approved.reference_code.startsWith('BANK-REF-'));

      // Attempt duplicate refund on SUCCEEDED refund -> should throw
      assert.throws(
        () => sharedStore.adminProcessRefund('ref_exact_24h', true, 'Duyệt lại', { id: 'admin1', name: 'Admin', role: 'ADMIN' }),
        /Yêu cầu hoàn tiền này đã được hoàn tất thành công trước đó/
      );

      // Ineligible refund (< 24.0h, e.g. 23.5h before session)
      const ineligibleRefund = {
        id: 'ref_under_24h',
        room_id: 'room_refund_test',
        room_code: 'REF-001',
        user_id: 'user_player_02',
        amount_vnd: 50000,
        status: 'REQUESTED',
        created_at: new Date(now + 0.5 * 3600 * 1000).toISOString(), // 23.5h before session
      };
      sharedStore.data.refunds.push(ineligibleRefund);

      // Trying to approve ineligible refund without override -> should throw
      assert.throws(
        () => sharedStore.adminProcessRefund('ref_under_24h', true, 'Cố duyệt', { id: 'admin1', name: 'Admin', role: 'ADMIN' }),
        /không đủ điều kiện xét hoàn cọc/
      );

      // Rejection with reason >= 5 chars
      const rejected = sharedStore.adminProcessRefund('ref_under_24h', false, 'Hủy phòng muộn dưới 24h quy định', {
        id: 'admin1',
        name: 'Admin',
        role: 'ADMIN',
      });
      assert.equal(rejected.status, 'REJECTED');
      assert.equal(rejected.rejected_reason, 'Hủy phòng muộn dưới 24h quy định');

      // Test retry on FAILED status
      const failedRefund = {
        id: 'ref_failed',
        room_id: 'room_refund_test',
        room_code: 'REF-001',
        user_id: 'user_player_03',
        amount_vnd: 50000,
        status: 'FAILED',
        created_at: new Date(now).toISOString(),
      };
      sharedStore.data.refunds.push(failedRefund);

      const retried = sharedStore.adminProcessRefund('ref_failed', true, 'Thử lại giao dịch ngân hàng thành công', {
        id: 'admin1',
        name: 'Admin',
        role: 'ADMIN',
      });
      assert.equal(retried.status, 'SUCCEEDED');
    });
  });

  describe('4. Game Catalog Pricing & Booking Price Snapshot Immutability', () => {
    test('Updating catalog pricing does not mutate existing game room booking snapshots', () => {
      // Find an existing game and room
      const game = sharedStore.data.games[0];
      const originalDayPrice = game.price_day_vnd;
      const originalNightPrice = game.price_night_vnd;

      // Existing room with pricing snapshot
      const existingRoom = {
        id: 'room_with_snapshot',
        code: 'SNP-001',
        game_id: game.id,
        pricing_snapshot: {
          slot_type: 'DAY',
          base_price_vnd: originalDayPrice,
          deposit_vnd: 50000,
          remaining_vnd: originalDayPrice - 50000,
        },
        participants: [],
      };
      sharedStore.data.gameRooms.push(existingRoom);

      // Admin updates catalog pricing (e.g. increase by 50,000)
      const newDayPrice = originalDayPrice + 50000;
      const newNightPrice = originalNightPrice + 60000;
      sharedStore.adminUpdateGamePricing(
        game.id,
        { price_day_vnd: newDayPrice, price_night_vnd: newNightPrice },
        { id: 'admin1', name: 'Admin', role: 'ADMIN' }
      );

      // Catalog prices are updated
      assert.equal(game.price_day_vnd, newDayPrice);
      assert.equal(game.price_night_vnd, newNightPrice);

      // Verify existing room pricing_snapshot remains COMPLETELY UNTOUCHED
      assert.equal(existingRoom.pricing_snapshot.base_price_vnd, originalDayPrice);
      assert.equal(existingRoom.pricing_snapshot.remaining_vnd, originalDayPrice - 50000);

      // Verify audit log recorded
      const audit = sharedStore.data.auditLogs.find(
        (a) => a.action === 'UPDATE_GAME_PRICING' && a.target_id === game.id
      );
      assert.ok(audit, 'Audit log should record UPDATE_GAME_PRICING');
    });
  });

  describe('5. RBAC User Role Management & Audit Trail', () => {
    test('Non-admin cannot update role; Admin updates role and generates audit log', () => {
      // Non-admin actor throws
      assert.throws(
        () => sharedStore.adminUpdateUserRole('user_player_01', 'DM', { id: 'user_player_01', name: 'Player', role: 'PLAYER' }),
        /Chỉ Quản trị viên \(ADMIN\) mới có quyền thay đổi vai trò tài khoản/
      );

      // Admin actor succeeds
      const result = sharedStore.adminUpdateUserRole('user_player_01', 'DM', { id: 'admin1', name: 'Tổng Quản Trị', role: 'ADMIN' });
      assert.equal(result.success, true);
      assert.equal(result.newRole, 'DM');

      // Audit log check
      const audit = sharedStore.data.auditLogs.find(
        (a) => a.action === 'UPDATE_USER_ROLE' && a.target_id === 'user_player_01'
      );
      assert.ok(audit, 'Audit log should record UPDATE_USER_ROLE');
      assert.ok(audit.details.includes('sang vai trò DM'));
    });

    test('Validates role and prevents demoting last admin', () => {
      // Invalid role throws
      assert.throws(
        () => sharedStore.adminUpdateUserRole('user_player_01', 'SUPERMAN', { id: 'admin1', name: 'Admin', role: 'ADMIN' }),
        /không hợp lệ/
      );

      // Attempt to demote last admin throws
      assert.throws(
        () => sharedStore.adminUpdateUserRole('user_admin_01', 'PLAYER', { id: 'admin1', name: 'Admin', role: 'ADMIN' }),
        /Không thể hạ quyền Quản trị viên cuối cùng/
      );
    });
  });
});
