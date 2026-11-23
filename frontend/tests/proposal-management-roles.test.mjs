import test from 'node:test';
import assert from 'node:assert/strict';

const BASE_URL = 'http://127.0.0.1:8000/api/v1';

async function createSessionClient() {
  const csrfRes = await fetch(`${BASE_URL}/auth/csrf-token`);
  assert.equal(csrfRes.status, 200);
  const csrfJson = await csrfRes.json();
  const csrfToken = csrfJson.data.csrf_token;
  const cookie = csrfRes.headers.get('set-cookie').split(';')[0];

  return {
    csrfToken,
    cookie,
    async get(path) {
      const res = await fetch(`${BASE_URL}${path}`, {
        headers: {
          'Accept': 'application/json',
          'Cookie': this.cookie,
        },
      });
      const data = await res.json().catch(() => null);
      return { status: res.status, headers: res.headers, data };
    },
    async post(path, body = {}) {
      const res = await fetch(`${BASE_URL}${path}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': this.csrfToken,
          'Cookie': this.cookie,
        },
        body: JSON.stringify(body),
      });
      const setCookie = res.headers.get('set-cookie');
      if (setCookie) {
        this.cookie = setCookie.split(';')[0];
      }
      const data = await res.json().catch(() => null);
      return { status: res.status, headers: res.headers, data };
    },
  };
}

test('Role-Based Proposal Management Suite', async (t) => {
  const guestClient = await createSessionClient();
  const playerClient = await createSessionClient();
  const dmClient = await createSessionClient();
  const adminClient = await createSessionClient();

  await t.test('1. Guest cannot propose schedule (401 Unauthorized)', async () => {
    const res = await guestClient.post('/rooms/1/proposals', {
      proposed_start_at: '2026-12-01 14:00:00',
      proposed_end_at: '2026-12-01 18:00:00',
    });
    assert.strictEqual(res.status, 401);
  });

  await t.test('2. Player logs in, creates room, and proposes schedule', async () => {
    const loginRes = await playerClient.post('/auth/login', {
      email: 'player.hieu@kichbansat.vn',
      password: 'password123',
    });
    assert.strictEqual(loginRes.status, 200);

    // Create room
    const createRoomRes = await playerClient.post('/rooms', {
      game_id: 1,
      title: 'Phòng test quyền đề xuất của người chơi',
    });
    assert.strictEqual(createRoomRes.status, 201);
    const roomId = createRoomRes.data.data.room.id;

    // Submit proposal
    const propRes = await playerClient.post(`/rooms/${roomId}/proposals`, {
      proposed_start_at: '2026-12-15 14:00:00',
      proposed_end_at: '2026-12-15 18:00:00',
    });
    assert.strictEqual(propRes.status, 201);
    const proposalId = propRes.data.data.proposal_id;
    assert.ok(proposalId > 0);

    // Player attempts to approve proposal -> Must be forbidden (403)
    const approveAttempt = await playerClient.post(`/proposals/${proposalId}/approve`, {});
    assert.strictEqual(approveAttempt.status, 403);
  });

  await t.test('3. DM logs in, views pending proposals, and approves proposal', async () => {
    const loginDm = await dmClient.post('/auth/login', {
      email: 'dm.nam@kichbansat.vn',
      password: 'password123',
    });
    assert.strictEqual(loginDm.status, 200);

    // List proposals
    const listRes = await dmClient.get('/proposals?status=PROPOSED');
    assert.strictEqual(listRes.status, 200);
    assert.ok(listRes.data.data.proposals.length > 0);
    const pendingProp = listRes.data.data.proposals[0];

    // DM approves proposal
    const approveRes = await dmClient.post(`/proposals/${pendingProp.id}/approve`, {});
    assert.strictEqual(approveRes.status, 200);
    assert.strictEqual(approveRes.data.data.status, 'CONFIRMED');
    assert.ok(approveRes.data.data.session_id > 0);
  });

  await t.test('4. Admin logs in, views proposals, and rejects proposal with reason', async () => {
    // Player creates another proposal
    const createRoomRes = await playerClient.post('/rooms', {
      game_id: 2,
      title: 'Phòng test Admin từ chối đề xuất',
    });
    const roomId = createRoomRes.data.data.room.id;

    const propRes = await playerClient.post(`/rooms/${roomId}/proposals`, {
      proposed_start_at: '2026-12-20 18:00:00',
      proposed_end_at: '2026-12-20 22:00:00',
    });
    const proposalId = propRes.data.data.proposal_id;

    // Login as Admin
    const loginAdmin = await adminClient.post('/auth/login', {
      email: 'admin@kichbansat.vn',
      password: 'password123',
    });
    assert.strictEqual(loginAdmin.status, 200);

    // Admin rejects proposal
    const rejectRes = await adminClient.post(`/admin/proposals/${proposalId}/reject`, {
      reason: 'Khung giờ này phòng vật lý đã kín lịch bảo trì',
    });
    assert.strictEqual(rejectRes.status, 200);
    assert.strictEqual(rejectRes.data.data.status, 'REJECTED');
  });
});
