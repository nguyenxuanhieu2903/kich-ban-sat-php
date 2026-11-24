import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

test('DM View: Contains session action buttons and handlers', () => {
  const filePath = path.resolve('frontend/src/pages/dm/dm-view.js');
  const code = fs.readFileSync(filePath, 'utf-8');

  // Verify Start Session button rendered when status is SCHEDULED
  assert.ok(code.includes('btn-start-session-${a.session_id}'), 'Contains Start Session button template');
  assert.ok(code.includes("a.session_status === 'SCHEDULED'"), 'Checks session_status SCHEDULED before showing start button');

  // Verify Complete Session button rendered when status is IN_PROGRESS
  assert.ok(code.includes('btn-complete-session-${a.session_id}'), 'Contains Complete Session button template');
  assert.ok(code.includes("a.session_status === 'IN_PROGRESS'"), 'Checks session_status IN_PROGRESS before showing complete button');

  // Verify event listeners
  assert.ok(code.includes('handleStartSession(a.session_id)'), 'Attaches handleStartSession listener');
  assert.ok(code.includes('handleCompleteSession(a.session_id)'), 'Attaches handleCompleteSession listener');

  // Verify API calls
  assert.ok(code.includes('api.post(`/sessions/${sessionId}/start`)'), 'Calls /sessions/:id/start API');
  assert.ok(code.includes('api.post(`/sessions/${sessionId}/complete`)'), 'Calls /sessions/:id/complete API');

  // Verify session badges
  assert.ok(code.includes('📅 Đã lên lịch'), 'Contains SCHEDULED badge');
  assert.ok(code.includes('▶ Đang diễn ra'), 'Contains IN_PROGRESS badge');
  assert.ok(code.includes('🏁 Đã hoàn thành'), 'Contains COMPLETED badge');
});

test('DM Repository: Queries join session details', () => {
  const filePath = path.resolve('backend/app/Features/DMManagement/Repositories/DMRepository.php');
  const code = fs.readFileSync(filePath, 'utf-8');

  // Verify listAssignments and findAssignmentById select session columns
  assert.ok(code.includes('s.`id` AS `session_id`'), 'Selects session_id');
  assert.ok(code.includes('s.`status` AS `session_status`'), 'Selects session_status');
  assert.ok(code.includes('COALESCE(s.`start_at`, sp.`proposed_start_at`) AS `session_start_at`'), 'Selects session_start_at');
  assert.ok(code.includes('LEFT JOIN `sessions` s'), 'LEFT JOIN sessions present');
  assert.ok(code.includes('LEFT JOIN `venues` v'), 'LEFT JOIN venues present');
  assert.ok(code.includes('LEFT JOIN `physical_rooms` pr'), 'LEFT JOIN physical_rooms present');
});
