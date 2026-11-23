import assert from 'node:assert/strict';
import {execFileSync, spawn} from 'node:child_process';
import {mkdtempSync, readFileSync, realpathSync, rmSync} from 'node:fs';
import {createServer} from 'node:http';
import {tmpdir} from 'node:os';
import {dirname, join} from 'node:path';
import {setTimeout as delay} from 'node:timers/promises';

const chrome = process.env.OPENWOIST_CHROME;
assert.ok(chrome, 'Set OPENWOIST_CHROME to a Chromium executable.');
const fixture = JSON.parse(execFileSync('php', ['tests/run.php', '--visualization-fixtures'], {maxBuffer: 4 * 1024 * 1024}));
const renderer = readFileSync('libs/helper/maps/vendor/maplibre-gl.js');
const observeWheels = html => html.replace('<head>', `<head><script>
    window.testWheels = {map: 0, objects: 0};
    window.addEventListener('wheel', event => {
        const panel = event.target.closest?.('#map, #objects');
        if (panel && event.isTrusted) window.testWheels[panel.id]++;
    }, {capture: true, passive: true});
</script>`);
const routes = new Map([
    ['/tile', ['text/html', observeWheels(fixture.tile)]],
    ['/ipsview', ['text/html', fixture.frame]],
    [fixture.options.page, ['text/html', observeWheels(fixture.page)]],
    [fixture.options.page.replace('/page', '/assets/maplibre-gl.js'), ['application/javascript', renderer]]
]);
const server = createServer((request, response) => {
    if (request.url === '/host/tile' || request.url === '/host/ipsview') {
        response.writeHead(200, {'Content-Type': 'text/html; charset=utf-8'}).end(
            '<!doctype html><style>body{margin:0}iframe{display:block;width:100vw;height:100vh;border:0}</style>' +
            `<iframe id="output" src="/${request.url.split('/').at(-1)}"></iframe>`
        );
        return;
    }
    if (request.url === fixture.options.page.replace('/page', '/state')) {
        if (request.headers['x-openwoist-token'] !== fixture.options.token) {
            response.writeHead(403).end();
            return;
        }
        response.writeHead(200, {'Content-Type': 'application/json'}).end(fixture.state);
        return;
    }
    const route = routes.get(request.url);
    if (route) response.writeHead(200, {'Content-Type': route[0] + '; charset=utf-8'}).end(route[1]);
    else response.writeHead(404).end();
});

async function until(condition, message) {
    const deadline = Date.now() + 12000;
    while (Date.now() < deadline) {
        const result = await condition();
        if (result) return result;
        await delay(80);
    }
    throw new Error(message);
}

const temp = realpathSync(tmpdir());
const profile = mkdtempSync(join(temp, 'openwoist-compact-'));
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const browser = spawn(chrome, ['--headless', '--remote-debugging-port=0', '--user-data-dir=' + profile,
    '--disable-background-networking', '--disable-component-update', '--use-gl=angle', '--use-angle=swiftshader',
    '--enable-unsafe-swiftshader', 'about:blank'], {stdio: 'ignore', windowsHide: true});
const stopped = new Promise(resolve => {
    browser.once('exit', resolve);
    browser.once('error', resolve);
});
let socket;
try {
    const port = await until(() => {
        try { return readFileSync(join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0].trim(); } catch { return null; }
    }, 'Chromium did not expose a DevTools port.');
    const tabs = await fetch(`http://127.0.0.1:${port}/json/list`).then(response => response.json());
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, {once: true});
        socket.addEventListener('error', reject, {once: true});
    });
    let sequence = 0;
    const pending = new Map();
    function call(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = ++sequence;
            pending.set(id, {resolve, reject});
            socket.send(JSON.stringify({id, method, params}));
        });
    }
    socket.addEventListener('message', ({data}) => {
        const message = JSON.parse(data);
        if (message.id) {
            const response = pending.get(message.id);
            pending.delete(message.id);
            if (message.error) response.reject(new Error(message.error.message));
            else response.resolve(message.result);
        } else if (message.method === 'Fetch.requestPaused') {
            const style = {version: 8, sources: {}, layers: [{id: 'background', type: 'background', paint: {'background-color': '#e0ebe7'}}]};
            void call('Fetch.fulfillRequest', {requestId: message.params.requestId, responseCode: 200,
                responseHeaders: [{name: 'Content-Type', value: 'application/json'}, {name: 'Access-Control-Allow-Origin', value: '*'}],
                body: Buffer.from(JSON.stringify(style)).toString('base64')});
        }
    });
    await call('Fetch.enable', {patterns: [{urlPattern: 'https://tiles.openfreemap.org/styles/*'}]});
    const resize = (width, height) => call('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: false});
    const wheel = (point, deltaY) => call('Input.dispatchMouseEvent', {type: 'mouseWheel', x: point[0], y: point[1], deltaX: 0, deltaY});

    for (const mode of ['tile', 'ipsview']) {
        async function inspect(code) {
            const frame = mode === 'tile' ? 'outer' : 'outer?.document.querySelector("#ipsview-map")?.contentWindow';
            const result = await call('Runtime.evaluate', {returnByValue: true, expression: `(() => {
                const outer = document.querySelector('#output')?.contentWindow;
                const w = ${frame};
                if (!w?.document.getElementById('openwoist')) return null;
                const d = w.document;
                ${code}
            })()`});
            assert.ok(!result.exceptionDetails, result.result.description);
            return result.result.value;
        }
        const geometry = () => inspect(`const map = d.querySelector('#map').getBoundingClientRect();
            const list = d.querySelector('#objects').getBoundingClientRect();
            const toolbar = d.querySelector('.toolbar').getBoundingClientRect();
            return {mapHeight:map.height, mapRight:map.right, mapBottom:map.bottom, listLeft:list.left, listTop:list.top,
                listBottom:list.bottom, toolbarHeight:toolbar.height};`);
        const point = selector => inspect(`const r = d.querySelector('${selector}').getBoundingClientRect();
            return [Math.round(r.left + r.width * .36), Math.round(r.top + r.height * .45)];`);
        const state = () => inspect(`return {zoomed: d.querySelector('.location-marker[data-kind="demo"]').style.transform,
            scroll: d.querySelector('#objects').scrollTop, events: w.testWheels};`);

        await resize(288, 189);
        await call('Page.navigate', {url: `http://127.0.0.1:${server.address().port}/host/${mode}`});
        await until(() => inspect('return d.querySelectorAll(".location-marker").length === 26;'), `${mode} marker fixture did not load.`);
        const compact = await geometry();
        assert.ok(compact.mapHeight >= 72 && compact.listLeft >= compact.mapRight, `${mode} must give the compact map room beside the list: ${JSON.stringify(compact)}`);
        assert.ok(compact.listBottom <= 189 && compact.toolbarHeight <= 32, `${mode} toolbar and list must fit inside the viewport.`);
        const mapPoint = await point('#map');
        const listPoint = await point('#objects');
        const before = await state();
        await wheel(mapPoint, -180);
        await until(async () => (await state()).zoomed !== before.zoomed, `${mode} map wheel must zoom in.`);
        await delay(450);
        const zoomed = await state();
        await wheel(mapPoint, 180);
        await until(async () => (await state()).zoomed !== zoomed.zoomed, `${mode} map wheel must zoom out.`);
        await delay(450);
        const unzoomed = await state();
        assert.equal(unzoomed.scroll, before.scroll);
        assert.ok(unzoomed.events.map >= 2);
        await wheel(listPoint, 120);
        await until(async () => (await state()).scroll > before.scroll, `${mode} list wheel must scroll down.`);
        const scrolled = await state();
        await wheel(listPoint, -120);
        await until(async () => (await state()).scroll < scrolled.scroll, `${mode} list wheel must scroll up.`);
        const restored = await state();
        assert.ok(restored.events.objects >= 2);
        assert.equal(scrolled.zoomed, unzoomed.zoomed);
        assert.equal(restored.zoomed, unzoomed.zoomed);

        await inspect('d.documentElement.style.setProperty("--openwoist-font-size", "28px");');
        assert.ok((await geometry()).mapHeight >= 72, `${mode} must keep a compact map at the largest tile font.`);
        await inspect('d.documentElement.style.removeProperty("--openwoist-font-size");');
        await resize(390, 600);
        await until(async () => {
            const g = await geometry();
            return g.listTop >= g.mapBottom && g.mapHeight >= 150;
        }, `${mode} must keep the stacked layout at portrait height.`);
        console.log(`${mode}: ${Math.floor(compact.mapHeight)}px map at 288×189, independent zoom and scroll passed.`);
    }
} finally {
    socket?.close();
    browser.kill();
    await stopped;
    server.close();
    if (dirname(realpathSync(profile)) === temp) rmSync(profile, {recursive: true, force: true, maxRetries: 3, retryDelay: 100});
}
