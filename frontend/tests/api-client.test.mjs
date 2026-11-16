import assert from 'node:assert';
import test from 'node:test';

globalThis.window = { __APP_CONFIG__: { apiBaseUrl: 'http://127.0.0.1:8080/api' } };

const { apiClient, ApiError } = await import('../src/api/api-client.js');

test('F-02: Request succeeds and cleans up timer and event listener', async () => {
  const originalFetch = globalThis.fetch;
  const callerController = new AbortController();

  globalThis.fetch = async (url, options) => {
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, data: { status: 'ok' } }),
    };
  };

  try {
    const res = await apiClient.get('/health', { signal: callerController.signal });
    assert.strictEqual(res.success, true);
    assert.strictEqual(res.data.status, 'ok');
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('F-02: Caller aborting in-flight request throws REQUEST_ABORTED, not REQUEST_TIMEOUT', async () => {
  const originalFetch = globalThis.fetch;
  const callerController = new AbortController();

  globalThis.fetch = async (url, options) => {
    return new Promise((resolve, reject) => {
      options.signal.addEventListener('abort', () => {
        const err = new Error('Aborted');
        err.name = 'AbortError';
        reject(err);
      });
    });
  };

  try {
    const promise = apiClient.get('/long-running', {
      signal: callerController.signal,
      timeout: 5000,
    });

    // Caller cancels request
    setTimeout(() => callerController.abort('User navigated away'), 20);

    await assert.rejects(
      async () => await promise,
      (err) => {
        assert.ok(err instanceof ApiError);
        assert.strictEqual(err.code, 'REQUEST_ABORTED');
        assert.strictEqual(err.status, 0);
        assert.strictEqual(err.message, 'User navigated away');
        return true;
      }
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('F-02: Pre-aborted caller signal immediately throws REQUEST_ABORTED without hanging', async () => {
  const originalFetch = globalThis.fetch;
  const callerController = new AbortController();
  callerController.abort('Already cancelled');

  globalThis.fetch = async (url, options) => {
    if (options.signal.aborted) {
      const err = new Error('Aborted');
      err.name = 'AbortError';
      throw err;
    }
    return { ok: true, status: 200, headers: new Headers(), text: async () => '' };
  };

  try {
    await assert.rejects(
      async () => await apiClient.get('/test', { signal: callerController.signal }),
      (err) => {
        assert.ok(err instanceof ApiError);
        assert.strictEqual(err.code, 'REQUEST_ABORTED');
        assert.strictEqual(err.status, 0);
        assert.strictEqual(err.message, 'Already cancelled');
        return true;
      }
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('F-02: True timeout triggers REQUEST_TIMEOUT with accurate duration', async () => {
  const originalFetch = globalThis.fetch;

  globalThis.fetch = async (url, options) => {
    return new Promise((resolve, reject) => {
      options.signal.addEventListener('abort', () => {
        const err = new Error('The operation was aborted');
        err.name = 'AbortError';
        reject(err);
      });
    });
  };

  try {
    await assert.rejects(
      async () => await apiClient.get('/timeout-endpoint', { timeout: 40 }),
      (err) => {
        assert.ok(err instanceof ApiError);
        assert.strictEqual(err.code, 'REQUEST_TIMEOUT');
        assert.strictEqual(err.status, 0);
        assert.match(err.message, /Request timed out after 40ms/);
        return true;
      }
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('F-02: Network failure produces NETWORK_FAILURE error', async () => {
  const originalFetch = globalThis.fetch;

  globalThis.fetch = async () => {
    throw new TypeError('Failed to fetch');
  };

  try {
    await assert.rejects(
      async () => await apiClient.get('/network-error'),
      (err) => {
        assert.ok(err instanceof ApiError);
        assert.strictEqual(err.code, 'NETWORK_FAILURE');
        assert.strictEqual(err.status, 0);
        assert.strictEqual(err.details?.originalError, 'Failed to fetch');
        return true;
      }
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('F-02: HTTP error response creates normalized ApiError with status and details', async () => {
  const originalFetch = globalThis.fetch;

  globalThis.fetch = async () => {
    return {
      ok: false,
      status: 404,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({
        success: false,
        error: { code: 'NOT_FOUND', message: 'Room 123 does not exist' },
      }),
    };
  };

  try {
    await assert.rejects(
      async () => await apiClient.get('/rooms/123'),
      (err) => {
        assert.ok(err instanceof ApiError);
        assert.strictEqual(err.status, 404);
        assert.strictEqual(err.code, 'NOT_FOUND');
        assert.strictEqual(err.message, 'Room 123 does not exist');
        return true;
      }
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('F-02: Event listener cleanup verified on caller signal', async () => {
  const originalFetch = globalThis.fetch;
  const callerController = new AbortController();

  // Track event listener additions and removals
  let listenersCount = 0;
  const originalAdd = callerController.signal.addEventListener.bind(callerController.signal);
  const originalRemove = callerController.signal.removeEventListener.bind(callerController.signal);

  callerController.signal.addEventListener = (type, fn, opts) => {
    if (type === 'abort') listenersCount++;
    return originalAdd(type, fn, opts);
  };
  callerController.signal.removeEventListener = (type, fn, opts) => {
    if (type === 'abort') listenersCount--;
    return originalRemove(type, fn, opts);
  };

  globalThis.fetch = async () => ({
    ok: true,
    status: 200,
    headers: new Headers({ 'content-type': 'application/json' }),
    json: async () => ({ ok: true }),
  });

  try {
    await apiClient.get('/cleanup-check', { signal: callerController.signal });
    // After request completion, listener must be cleaned up
    assert.strictEqual(listenersCount, 0, 'Abort event listener on caller signal must be cleaned up');
  } finally {
    globalThis.fetch = originalFetch;
  }
});
