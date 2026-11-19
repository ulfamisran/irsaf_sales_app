/* Authorization Web Push service worker */
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'Otorisasi', body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'Otorisasi baru';
    const options = {
        body: data.body || 'Ada dokumen menunggu otorisasi.',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        data: {
            url: data.url || '/authorizations?tab=siap',
            module: data.module || null,
            request_id: data.request_id || null,
        },
        requireInteraction: true,
        tag: data.request_id ? ('auth-req-' + data.request_id) : 'authorization',
        renotify: true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/authorizations?tab=siap';

    event.waitUntil((async () => {
        const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of allClients) {
            if ('focus' in client) {
                await client.focus();
                if ('navigate' in client) {
                    await client.navigate(targetUrl);
                } else {
                    client.postMessage({ type: 'authorization-open', url: targetUrl });
                }
                return;
            }
        }
        if (self.clients.openWindow) {
            await self.clients.openWindow(targetUrl);
        }
    })());
});
