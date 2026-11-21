/**
 * Unified API Service Gateway
 * Switches dynamically between liveApi and demoApi based on config.DATA_MODE without UI modifications.
 */
import { config } from '../utils/config.js';
import { demoApi } from './demo/demo-api.js';
import { liveApi } from './live-api.js';

export const apiService = new Proxy({}, {
  get(target, propKey) {
    return async function (...args) {
      const mode = config.DATA_MODE;

      if (mode === 'live' && typeof liveApi[propKey] === 'function') {
        try {
          return await liveApi[propKey](...args);
        } catch (err) {
          // If endpoint is provisional or network failed in live, surface explicit error
          console.warn(`[LiveApi] Fallback check for method ${propKey}:`, err.message);
          throw err;
        }
      }

      // Default to demoApi
      if (typeof demoApi[propKey] === 'function') {
        return await demoApi[propKey](...args);
      }

      throw new Error(`API method "${propKey}" is not implemented.`);
    };
  },
});
