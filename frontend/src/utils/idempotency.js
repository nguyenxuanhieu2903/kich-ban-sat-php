/**
 * Idempotency Key Manager
 * Generates and caches UUID v4 idempotency keys per operation for side-effect safe retries.
 */

function generateUuid() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

const keyCache = new Map();

/**
 * Get or create an idempotency key for a specific logical action
 * @param {string} actionId (e.g. 'deposit_room_123_user_456')
 * @param {boolean} forceNew (True to generate a brand new key)
 */
export function getIdempotencyKey(actionId, forceNew = false) {
  if (!actionId) return generateUuid();
  if (forceNew || !keyCache.has(actionId)) {
    const newKey = generateUuid();
    keyCache.set(actionId, newKey);
    return newKey;
  }
  return keyCache.get(actionId);
}

export function clearIdempotencyKey(actionId) {
  if (actionId) {
    keyCache.delete(actionId);
  }
}
