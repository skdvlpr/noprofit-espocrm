/* Safehouse CRM — Web Push service worker (site root scope). */
/* v4 — Windows-friendly: explicit silent:false; unique renotify tags */
self.addEventListener('install', event => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', event => {
    let data = {
        title: 'Safehouse CRM',
        body: 'New notification',
        url: '#',
        tag: 'safehouse-web-push',
    };

    try {
        if (event.data) {
            const parsed = event.data.json();
            data = Object.assign(data, parsed || {});
        }
    } catch (e) {
        try {
            data.body = event.data ? event.data.text() : data.body;
        } catch (e2) {}
    }

    const title = data.title || 'Safehouse CRM';
    const tag = data.tag || ('safehouse-web-push-' + Date.now());
    const options = {
        body: data.body || '',
        tag: tag,
        renotify: true,
        silent: false,
        data: {url: data.url || '#'},
        icon: '/client/img/favicon-196.png',
        badge: '/client/img/favicon-196.png',
    };

    event.waitUntil(
        self.registration.showNotification(title, options).catch(() => {
            return self.registration.showNotification(title, {
                body: String(options.body || 'Safehouse CRM'),
                tag: tag,
                silent: false,
                data: options.data,
            });
        })
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();

    const target = (event.notification.data && event.notification.data.url) || '#';
    const absolute = target.indexOf('http') === 0
        ? target
        : self.location.origin + '/' + (target.indexOf('#') === 0 ? target : '#' + target.replace(/^#/, ''));

    event.waitUntil(
        self.clients.matchAll({type: 'window', includeUncontrolled: true}).then(clientList => {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];

                if ('focus' in client) {
                    client.navigate(absolute);
                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(absolute);
            }
        })
    );
});
