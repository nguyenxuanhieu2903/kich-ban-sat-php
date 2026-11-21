import { spawn } from 'node:child_process';
import fs from 'node:fs';

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const brainDir = 'C:\\Users\\Acer\\.gemini\\antigravity\\brain\\20a9971b-e58a-43e6-b482-48436e3bae68';

const chrome = spawn(chromePath, [
  '--headless=new',
  '--remote-debugging-port=9227',
  '--window-size=1600,1800',
  'http://127.0.0.1:3000/inspect_covers.html'
]);

await new Promise(r => setTimeout(r, 2500));

try {
  const versionRes = await fetch('http://127.0.0.1:9227/json/list');
  const tabs = await versionRes.json();
  const pageTab = tabs.find(t => t.type === 'page');
  if (!pageTab) {
    console.log('No page tab found');
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
    width: 1600,
    height: 1800,
    deviceScaleFactor: 1,
    mobile: false
  });
  await send('Page.navigate', { url: 'http://127.0.0.1:3000/inspect_covers.html' });
  await new Promise(r => setTimeout(r, 2000));
  const shot = await send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync(`${brainDir}\\covers_contact_sheet.png`, Buffer.from(shot.data, 'base64'));
  console.log(`[CAPTURED] covers_contact_sheet.png`);
  ws.close();
} finally {
  chrome.kill();
}
