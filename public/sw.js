

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload = {};

    try {
        payload = event.data.json();
    } catch (error) {
        payload = { title: 'Notificación', body: event.data.text() };
    }

    const titulo = payload.title || 'MDS — Papeletas de salida';
    const opciones = {
        body: payload.body,
        icon: payload.icon || '/favicon.ico',
        badge: payload.badge,
        tag: payload.tag,
        data: payload.data || {},
        actions: payload.actions || [],
        requireInteraction: payload.requireInteraction || false,
    };

    event.waitUntil(self.registration.showNotification(titulo, opciones));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data && event.notification.data.url;

    if (!url) {
        return;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const cliente of clientList) {
                if (cliente.url === url && 'focus' in cliente) {
                    return cliente.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
