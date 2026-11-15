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
 * error normalization, and lifecycle states.
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
    const timeout = setTimeout(() => controller.abort(), options.timeout || config.defaultTimeoutMs);

    try {
      const response = await fetch(url, {
        ...options,
        headers,
        signal: controller.signal,
      });

      clearTimeout(timeout);

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
      clearTimeout(timeout);

      if (err instanceof ApiError) {
        throw err;
      }

      if (err.name === 'AbortError') {
        throw new ApiError('Request timed out after ' + config.defaultTimeoutMs + 'ms', 0, 'REQUEST_TIMEOUT');
      }

      // Network unreachable, DNS failure, CORS blocked
      throw new ApiError(
        'Unable to reach backend service. Please verify that the API server is running.',
        0,
        'NETWORK_FAILURE',
        { originalError: err.message }
      );
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
