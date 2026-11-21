(() => {
    'use strict';

    const bootstrap = window.SymconVisualization;
    const {options, translations} = bootstrap;
    const text = key => translations[key] || key;
    const byId = id => document.getElementById(id);
    const root = byId('openwoist');
    const sourceFilter = byId('source-filter');
    const list = byId('object-list');
    const panel = byId('objects');
    const fitButton = byId('fit');
    const markers = new Map();
    const lang = document.documentElement.lang || 'en';
    const relativeTime = new Intl.RelativeTimeFormat(lang, {numeric: 'auto'});
    const absoluteTime = new Intl.DateTimeFormat(lang, {dateStyle: 'medium', timeStyle: 'short'});
    const viewStore = window.SymconOpenFreeMap.createViewState(options.viewStateKey, window);
    const saved = viewStore.read();
    let snapshot = bootstrap.state;
    let filter = saved?.filter || '';
    let selected = saved?.selected || null;
    let hasView = Boolean(saved);
    let filterChoices = null;
    let shown = [];
    let vectorController = null;

    const map = L.map('map', {zoomControl: false, minZoom: 1, maxZoom: 19})
        .setView(saved?.center || [32, 10], saved?.zoom || 2);
    L.control.zoom({position: 'topright'}).addTo(map);
    map.attributionControl.setPrefix(false);
    const markerLayer = L.layerGroup().addTo(map);
    const icon = L.divIcon({className: 'location-marker', html: '<span class="symbol" aria-hidden="true"></span>', iconSize: [22, 22], iconAnchor: [11, 11]});

    root.style.setProperty('--symc-tile-header-inset', (options.tileHeaderInset || 0) + 'px');
    root.style.setProperty('--symc-tile-action-inset', (options.tileActionInset || 0) + 'px');
    byId('map').setAttribute('aria-label', text('Location map'));
    byId('filter-label').textContent = text('Source');
    byId('objects-label').textContent = text('Objects');
    fitButton.textContent = text('Show all positions');

    function saveView() {
        if (!hasView) return;
        const center = map.wrapLatLng(map.getCenter());
        viewStore.write({center: [center.lat, center.lng], zoom: map.getZoom(), selected, popup: false, filter, scroll: panel.scrollTop});
    }

    function fit() {
        const points = shown.filter(row => row.object.location).map(row => coordinates(row.object.location));
        if (points.length === 0) return;
        hasView = true;
        map.fitBounds(points, {padding: [30, 30], maxZoom: 15, animate: false});
        saveView();
    }

    function coordinates(location) {
        return [location.latitude, location.longitude];
    }

    function selectObject(id, focusMap) {
        selected = id;
        const row = shown.find(item => item.object.id === id);
        if (focusMap && row?.object.location) {
            hasView = true;
            map.setView(coordinates(row.object.location), Math.max(14, map.getZoom()), {animate: false});
        }
        render();
        saveView();
    }

    function status(id, message) {
        const element = byId(id);
        if (element.textContent !== message) element.textContent = message;
        element.hidden = !message;
    }

    function element(tag, className, value = '') {
        const node = document.createElement(tag);
        node.className = className;
        node.textContent = value;
        return node;
    }

    function ageText(timestamp, now) {
        const seconds = Math.max(0, now - timestamp);
        if (seconds < 60) return relativeTime.format(-seconds, 'second');
        if (seconds < 3600) return relativeTime.format(-Math.floor(seconds / 60), 'minute');
        if (seconds < 86400) return relativeTime.format(-Math.floor(seconds / 3600), 'hour');
        return relativeTime.format(-Math.floor(seconds / 86400), 'day');
    }

    function detail(row, now) {
        const {source, object, stale} = row;
        const item = element('span', 'object-detail');
        if (!object.location) {
            item.textContent = text('No position');
            return item;
        }
        const {timestamp, accuracy} = object.location;
        const time = element('time', '', ageText(timestamp, now));
        time.dateTime = new Date(timestamp * 1000).toISOString();
        time.title = absoluteTime.format(timestamp * 1000);
        if (stale) item.append(text('Last known position') + ' · ');
        item.append(time);
        if (accuracy > 0) item.append(' · ±' + Math.ceil(accuracy) + ' m');
        if (source.kind === 'icloud') item.append(' · ' + source.name);
        return item;
    }

    function syncMarkers() {
        const active = new Set();
        for (const {source, object, stale} of shown) {
            if (!object.location) continue;
            const id = object.id;
            const point = coordinates(object.location);
            active.add(id);
            let marker = markers.get(id);
            if (!marker) {
                marker = L.marker(point, {icon, autoPanOnFocus: false}).addTo(markerLayer);
                marker.on('click', () => selectObject(id, false));
                markers.set(id, marker);
            } else if (!marker.getLatLng().equals(point, 0)) {
                marker.setLatLng(point);
            }
            const node = marker.getElement();
            node.dataset.kind = source.kind;
            node.dataset.stale = String(stale);
            node.dataset.selected = String(id === selected);
            node.setAttribute('aria-label', object.name);
            node.title = object.name;
            marker.setZIndexOffset(id === selected ? 1000 : 0);
        }
        for (const [id, marker] of markers) {
            if (active.has(id)) continue;
            markerLayer.removeLayer(marker);
            markers.delete(id);
        }
    }

    function syncFilter(sources) {
        const choices = JSON.stringify(sources.map(source => [source.id, source.name]));
        if (choices !== filterChoices) {
            filterChoices = choices;
            sourceFilter.replaceChildren(new Option(text('All sources'), ''));
            for (const source of sources) sourceFilter.add(new Option(source.name, source.id));
        }
        if (!sources.some(source => source.id === filter)) filter = '';
        sourceFilter.value = filter;
        byId('filter-control').hidden = sources.length < 2;
    }

    function render() {
        const now = Math.floor(Date.now() / 1000);
        const sources = snapshot.sources;
        const scroll = panel.scrollTop;
        const focused = list.contains(document.activeElement) ? document.activeElement.dataset.id : null;
        syncFilter(sources);
        const visible = sources.filter(source => !filter || source.id === filter);
        shown = visible.flatMap(source => source.objects.map(object => ({
            source, object, stale: !source.available || !object.current || now - object.location?.timestamp >= snapshot.staleAfter
        }))).sort((a, b) => a.object.name.localeCompare(b.object.name, lang));
        if (!sources.some(source => source.objects.some(object => object.id === selected))) selected = null;
        const unavailable = visible.filter(source => !source.available).map(source => source.name);
        status('source-status', unavailable.length ? text('Source unavailable') + ' – ' + unavailable.join(', ') : '');
        status('empty', sources.length === 0 && !snapshot.presentation && options.statePath ? ''
            : sources.length === 0 ? text('No location sources configured.') : shown.length === 0 ? text('No objects in this view.') : '');
        fitButton.disabled = !shown.some(row => row.object.location);

        const rows = document.createDocumentFragment();
        let restoreFocus = null;
        for (const row of shown) {
            const {source, object, stale} = row;
            const item = document.createElement('li');
            const button = element('button', 'object');
            button.type = 'button';
            button.dataset.id = object.id;
            button.dataset.kind = source.kind;
            button.dataset.stale = String(stale);
            button.setAttribute('aria-pressed', String(object.id === selected));
            button.addEventListener('click', () => selectObject(object.id, true));
            const symbol = element('span', 'symbol');
            symbol.setAttribute('aria-hidden', 'true');
            const content = element('span', 'object-content');
            content.append(element('span', 'object-name', object.name), detail(row, now));
            button.append(symbol, content);
            item.append(button);
            rows.append(item);
            if (object.id === focused) restoreFocus = button;
        }
        list.replaceChildren(rows);
        restoreFocus?.focus({preventScroll: true});
        panel.scrollTop = scroll;
        syncMarkers();
        if (!hasView) fit();
    }

    function applyPresentation() {
        const presentation = snapshot.presentation;
        const style = byId('map-presentation');
        const css = presentation?.css || '';
        if (style.textContent !== css) style.textContent = css;
        if (vectorController && presentation?.basemap) void vectorController.update(presentation.basemap);
    }

    function applyState(data) {
        if (!Array.isArray(data?.sources) || !Number.isFinite(data.staleAfter)) return false;
        snapshot = data;
        applyPresentation();
        render();
        return true;
    }

    function connectIPSView() {
        const token = /^#token=([a-f0-9]{64})$/.exec(window.location.hash)?.[1];
        if (!token) {
            status('connection-status', text('IPSView map access is unavailable.'));
            return;
        }
        try {
            window.history.replaceState(null, '', window.location.pathname);
        } catch (_) {
            status('connection-status', text('IPSView map access is unavailable.'));
            return;
        }
        let etag = '';
        let busy = false;
        let timer = null;
        async function poll() {
            if (busy || timer === null || document.hidden) return;
            busy = true;
            const abort = new AbortController();
            const timeout = window.setTimeout(() => abort.abort(), 8000);
            try {
                const headers = {'X-OpenWoIst-Token': token};
                if (etag) headers['If-None-Match'] = etag;
                const response = await fetch(options.statePath, {
                    headers, signal: abort.signal, cache: 'no-store', credentials: 'omit', redirect: 'error'
                });
                if (response.status === 403 || response.status === 404) {
                    applyState(bootstrap.state);
                    vectorController?.destroy();
                    vectorController = null;
                    status('map-status', '');
                    status('connection-status', text('IPSView map access is unavailable.'));
                    window.clearInterval(timer);
                    document.removeEventListener('visibilitychange', poll);
                    timer = null;
                    return;
                }
                if (response.status !== 304) {
                    if (!response.ok || !applyState(await response.json())) throw new Error('Map state unavailable.');
                    etag = response.headers.get('ETag') || '';
                }
                status('connection-status', '');
            } catch (_) {
                status('connection-status', text('IPSView connection interrupted. Retrying.'));
            } finally {
                window.clearTimeout(timeout);
                busy = false;
            }
        }
        status('connection-status', text('Connecting to the IPSView map.'));
        timer = window.setInterval(poll, 5000);
        document.addEventListener('visibilitychange', poll);
        void poll();
    }

    sourceFilter.addEventListener('change', () => {
        filter = sourceFilter.value;
        render();
        saveView();
    });
    fitButton.addEventListener('click', fit);
    map.on('moveend', saveView);
    panel.addEventListener('scroll', saveView);
    new ResizeObserver(() => map.invalidateSize({pan: false, debounceMoveend: true})).observe(byId('map'));
    applyPresentation();
    render();
    panel.scrollTop = saved?.scroll || 0;
    window.setInterval(render, 30000);

    window.SymconVectorAssetsReady
        .then(() => {
            vectorController = window.SymconOpenFreeMap.createController(map, window, code => {
                status('map-status', code ? text('Basemap unavailable. Positions are still shown.') : '');
            });
            applyPresentation();
        })
        .catch(() => status('map-status', text('Basemap unavailable. Positions are still shown.')));

    if (options.statePath) {
        connectIPSView();
    } else {
        window.handleMessage = value => {
            try {
                applyState(typeof value === 'string' ? JSON.parse(value) : value);
            } catch (_) {
                // Keep the current map if the host delivers an invalid message.
            }
        };
        if (typeof window.requestAction === 'function') {
            try {
                Promise.resolve(window.requestAction('GetState', '')).catch(() => {});
            } catch (_) {
                // The embedded snapshot remains usable.
            }
        }
    }
})();
