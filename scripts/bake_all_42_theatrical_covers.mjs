import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const coversDir = path.resolve('frontend/assets/images/covers');

const chrome = spawn(chromePath, [
  '--headless=new',
  '--remote-debugging-port=9233',
  '--window-size=640,420',
  'http://127.0.0.1:3000/poster_renderer.html?id=game_001'
]);

await new Promise(r => setTimeout(r, 2200));

try {
  const versionRes = await fetch('http://127.0.0.1:9233/json/list');
  const tabs = await versionRes.json();
  const pageTab = tabs.find(t => t.type === 'page');
  if (!pageTab) {
    console.error('No page tab found');
    process.exit(1);
  }

  const ws = new WebSocket(pageTab.webSocketDebuggerUrl);
  await new Promise(r => ws.onopen = r);

  let id = 1;
  function send(method, params = {}) {
    return new Promise(resolve => {
      const msgId = id++;
      const handler = (evt) => {
        const data = JSON.parse(evt.data);
        if (data.id === msgId) {
          ws.removeEventListener('message', handler);
          resolve(data.result);
        }
      };
      ws.addEventListener('message', handler);
      ws.send(JSON.stringify({ id: msgId, method, params }));
    });
  }

  await send('Emulation.setDeviceMetricsOverride', {
    width: 640,
    height: 420,
    deviceScaleFactor: 1,
    mobile: false
  });

  console.log(`Starting baking of 42 theatrical Jubensha covers...`);

  for (let i = 1; i <= 42; i++) {
    const gameId = `game_${String(i).padStart(3, '0')}`;
    const url = `http://127.0.0.1:3000/poster_renderer.html?id=${gameId}`;
    await send('Page.navigate', { url });

    // Wait until fonts and background image are fully ready
    for (let attempt = 0; attempt < 25; attempt++) {
      await new Promise(r => setTimeout(r, 80));
      const res = await send('Runtime.evaluate', { expression: 'window.__POSTER_READY__ === true' });
      if (res?.result?.value) break;
    }

    const shot = await send('Page.captureScreenshot', { format: 'jpeg', quality: 88 });
    const filePath = path.join(coversDir, `${gameId}.jpg`);
    fs.writeFileSync(filePath, Buffer.from(shot.data, 'base64'));
    console.log(`[BAKED] ${gameId}.jpg (${Math.round(shot.data.length * 0.75 / 1024)} KB)`);
  }

  console.log(`\nAll 42 theatrical Jubensha covers baked successfully!`);
  ws.close();
} finally {
  chrome.kill();
}
