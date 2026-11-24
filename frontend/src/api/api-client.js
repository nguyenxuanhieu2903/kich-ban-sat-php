import { config } from '../utils/config.js';

export class ApiError extends Error {
  constructor(message, status = 0, code = 'NETWORK_ERROR', details = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

/**
 * Standard API Client with automatic CSRF token management and PHP Session support.
 */
export class ApiClient {
  constructor(baseUrl = config.apiBaseUrl) {
    this.baseUrl = (baseUrl || '').replace(/\/+$/, '');
    this.csrfToken = null;
    this.csrfPromise = null;
  }

  clearCsrfToken() {
    this.csrfToken = null;
    this.csrfPromise = null;
  }

  async ensureCsrfToken() {
    if (this.csrfToken) {
      return this.csrfToken;
    }

    if (!this.csrfPromise) {
      this.csrfPromise = (async () => {
        try {
          const res = await fetch(`${this.baseUrl}/auth/csrf-token`, {
            credentials: 'include',
            headers: { 'Accept': 'application/json' },
          });
          const data = await res.json();
          if (data && data.success && data.data && data.data.csrf_token) {
            this.csrfToken = data.data.csrf_token;
            return this.csrfToken;
          }
        } catch {
          // Ignore prefetch error in non-browser or disconnected environment
        } finally {
          this.csrfPromise = null;
        }
        return null;
      })();
    }

    return this.csrfPromise;
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}/${endpoint.replace(/^\/+/, '')}`;
    const method = (options.method || 'GET').toUpperCase();

    // Immediate check if caller already aborted
    if (options.signal && options.signal.aborted) {
      const reason = typeof options.signal.reason === 'string' ? options.signal.reason : 'Request aborted by caller';
      throw new ApiError(reason, 0, 'REQUEST_ABORTED');
    }

    // Attach CSRF token for mutations
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      await this.ensureCsrfToken();
    }

    const headers = {
      'Accept': 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(this.csrfToken ? { 'X-CSRF-Token': this.csrfToken } : {}),
      ...(options.headers || {})
    };

    const controller = new AbortController();
    const timeoutMs = options.timeout || config.defaultTimeoutMs;
    let isTimeout = false;

    // Propagate caller signal abort to internal controller
    let abortListener = null;
    if (options.signal) {
      abortListener = () => {
        controller.abort(options.signal.reason);
      };
      options.signal.addEventListener('abort', abortListener);
    }

    const timer = setTimeout(() => {
      isTimeout = true;
      controller.abort();
    }, timeoutMs);

    try {
      const response = await fetch(url, {
        credentials: 'include',
        ...options,
        headers,
        signal: controller.signal,
      });

      clearTimeout(timer);

      let data = null;
      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('application/json')) {
        try {
          data = await response.json();
        } catch {
          throw new ApiError('Failed to parse server response as JSON', response.status, 'INVALID_JSON_RESPONSE');
        }
      } else {
        data = await response.text();
      }

      if (!response.ok) {
        const errorInfo = (typeof data === 'object' && data?.error) ? data.error : null;
        const message = errorInfo?.message || data?.message || `HTTP error ${response.status}`;
        const code = errorInfo?.code || `HTTP_${response.status}`;
        const details = errorInfo?.details || null;

        if (code === 'CSRF_TOKEN_INVALID' || (typeof message === 'string' && message.includes('CSRF'))) {
          this.clearCsrfToken();
          if (!options._csrfRetried && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            await this.ensureCsrfToken();
            return this.request(endpoint, { ...options, _csrfRetried: true });
          }
        }

        throw new ApiError(message, response.status, code, details);
      }

      return data;
    } catch (err) {
      clearTimeout(timer);

      if (err instanceof ApiError) {
        throw err;
      }

      if (err.name === 'AbortError') {
        if (isTimeout) {
          throw new ApiError(`Request timed out after ${timeoutMs}ms`, 0, 'REQUEST_TIMEOUT');
        }
        const reason = options.signal?.reason;
        const msg = typeof reason === 'string' ? reason : 'Request aborted by caller';
        throw new ApiError(msg, 0, 'REQUEST_ABORTED');
      }

      throw new ApiError('Failed to fetch', 0, 'NETWORK_FAILURE', { originalError: err.message });
    } finally {
      if (options.signal && abortListener) {
        options.signal.removeEventListener('abort', abortListener);
      }
    }
  }

  get(endpoint, options = {}) {
    return this.request(endpoint, { ...options, method: 'GET' });
  }

  post(endpoint, body = {}, options = {}) {
    return this.request(endpoint, {
      ...options,
      method: 'POST',
      body: JSON.stringify(body),
    });
  }

  put(endpoint, body = {}, options = {}) {
    return this.request(endpoint, {
      ...options,
      method: 'PUT',
      body: JSON.stringify(body),
    });
  }

  patch(endpoint, body = {}, options = {}) {
    return this.request(endpoint, {
      ...options,
      method: 'PATCH',
      body: JSON.stringify(body),
    });
  }

  delete(endpoint, options = {}) {
    return this.request(endpoint, { ...options, method: 'DELETE' });
  }
}

export const api = new ApiClient();
export const apiClient = api;

if (typeof window !== 'undefined') {
  window.apiClient = api;
  window.api = api;
}

// Wrapper for feature/frontend compatibility
export async function apiRequest(endpoint, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  if (method === 'GET') {
    return api.get(endpoint, options);
  }
  return api.request(endpoint, options);
}
