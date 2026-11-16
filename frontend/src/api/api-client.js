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
 * Native Fetch API wrapper providing uniform JSON request handling,
 * error normalization, cancellation, and lifecycle states.
 */
class ApiClient {
  constructor(baseUrl = config.apiBaseUrl) {
    this.baseUrl = baseUrl.replace(/\/+$/, '');
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}/${endpoint.replace(/^\/+/, '')}`;
    const headers = {
      'Accept': 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(options.headers || {}),
    };

    const controller = new AbortController();
    const timeoutMs = options.timeout || config.defaultTimeoutMs;
    let isTimeout = false;
    let abortListener = null;

    const timeout = setTimeout(() => {
      isTimeout = true;
      controller.abort();
    }, timeoutMs);

    // Compose caller signal with internal timeout controller to preserve cancellation
    if (options.signal) {
      if (options.signal.aborted) {
        controller.abort(options.signal.reason);
      } else {
        abortListener = () => {
          controller.abort(options.signal.reason);
        };
        options.signal.addEventListener('abort', abortListener, { once: true });
      }
    }

    try {
      const response = await fetch(url, {
        credentials: options.credentials || 'same-origin',
        ...options,
        headers,
        signal: controller.signal,
      });

      let data = null;
      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('application/json')) {
        try {
          data = await response.json();
        } catch (jsonErr) {
          throw new ApiError('Failed to parse server response as JSON', response.status, 'INVALID_JSON_RESPONSE');
        }
      } else {
        data = await response.text();
      }

      if (!response.ok) {
        const errorInfo = (typeof data === 'object' && data?.error) ? data.error : null;
        const message = errorInfo?.message || `HTTP Request failed with status ${response.status}`;
        const code = errorInfo?.code || `HTTP_${response.status}`;
        const details = errorInfo?.details || null;

        throw new ApiError(message, response.status, code, details);
      }

      return data;
    } catch (err) {
      if (err instanceof ApiError) {
        throw err;
      }

      if (err.name === 'AbortError') {
        if (isTimeout) {
          throw new ApiError(`Request timed out after ${timeoutMs}ms`, 0, 'REQUEST_TIMEOUT');
        }

        const reason = options.signal?.reason;
        const message = typeof reason === 'string'
          ? reason
          : (reason instanceof Error ? reason.message : 'Request was aborted by caller');

        throw new ApiError(message, 0, 'REQUEST_ABORTED', { reason });
      }

      // Network unreachable, DNS failure, CORS blocked
      throw new ApiError(
        'Unable to reach backend service. Please verify that the API server is running.',
        0,
        'NETWORK_FAILURE',
        { originalError: err.message }
      );
    } finally {
      clearTimeout(timeout);
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

  delete(endpoint, options = {}) {
    return this.request(endpoint, { ...options, method: 'DELETE' });
  }
}

export const apiClient = new ApiClient();
