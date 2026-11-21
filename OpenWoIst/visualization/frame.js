(() => {
    'use strict';

    const {origin, page, token, error, inactive} = window.SymconVisualization.options;
    const frame = document.getElementById('ipsview-map');
    const notice = document.getElementById('ipsview-notice');
    const server = origin || window.SymconHTTPOrigin.resolve(window);
    if (/^[a-f0-9]{64}$/.test(token) && server) {
        const url = new URL(page, server);
        url.hash = 'token=' + token;
        frame.src = url.href;
        frame.hidden = false;
        return;
    }
    notice.textContent = origin === null || !server ? error : inactive;
    notice.hidden = false;
})();
