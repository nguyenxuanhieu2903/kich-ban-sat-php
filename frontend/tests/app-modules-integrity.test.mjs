import assert from 'node:assert/strict';
import test from 'node:test';
import fs from 'node:fs';
import path from 'node:path';

test('Frontend Module Integrity: All JS files in src must import with 0 syntax errors', async () => {
  const srcDir = path.resolve(process.cwd(), 'frontend/src');

  async function checkDir(dir) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const entry of entries) {
      const full = path.resolve(dir, entry.name);
      if (entry.isDirectory()) {
        await checkDir(full);
      } else if (entry.isFile() && full.endsWith('.js')) {
        const fileUrl = 'file:///' + full.replace(/\\/g, '/');
        try {
          const mod = await import(fileUrl);
          assert.ok(mod !== undefined, `Module imported: ${entry.name}`);
        } catch (err) {
          assert.fail(`Syntax/Import error in ${full}: ${err.message}`);
        }
      }
    }
  }

  await checkDir(srcDir);
});
