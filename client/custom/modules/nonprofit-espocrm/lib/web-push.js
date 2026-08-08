/**
 * Browser Web Push helper — separate from in-app (bell) notifications.
 * Preference `webPushEnabled` only controls this channel.
 * Exposed as window.SafehouseWebPush.
 */
(function () {
    const SW_PATH = '/web-push-sw.js';
    const SW_URL = SW_PATH + '?v=3';

    const isMobile = function () {
        return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
    };

    const isItalian = function () {
        const lang = (document.documentElement.lang || '').toLowerCase();

        return lang.indexOf('it') === 0;
    };

    const urlBase64ToUint8Array = function (base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    };

    const ensureManifest = function () {
        if (document.querySelector('link[rel="manifest"]')) {
            return;
        }

        const link = document.createElement('link');
        link.rel = 'manifest';
        link.href = '/client/custom/modules/nonprofit-espocrm/manifest.webmanifest';
        document.head.appendChild(link);
    };

    /**
     * Non-interactive capability check (no checklist, no prompts).
     * @return {{ok: boolean, permission: string, issues: string[], canRequest: boolean, mobile: boolean}}
     */
    const diagnose = function () {
        const issues = [];
        let permission = 'unsupported';
        let canRequest = false;

        if (!('serviceWorker' in navigator)) {
            issues.push(isItalian()
                ? 'Service Worker non supportato in questo browser.'
                : 'Service Worker is not supported in this browser.');
        }

        if (!('PushManager' in window)) {
            issues.push(isItalian()
                ? 'Push API non supportata in questo browser.'
                : 'Push API is not supported in this browser.');
        }

        if (!('Notification' in window)) {
            issues.push(isItalian()
                ? 'API Notification non supportata.'
                : 'Notifications API is not supported.');
        } else {
            permission = Notification.permission;
            canRequest = permission === 'default';

            if (permission === 'denied') {
                issues.push(isItalian()
                    ? 'Permesso notifiche negato. Abilitalo nelle impostazioni del browser / sistema.'
                    : 'Notification permission denied. Enable it in browser / OS site settings.');
            }
        }

        if (!window.isSecureContext && location.hostname !== 'localhost') {
            issues.push(isItalian()
                ? 'Serve un contesto sicuro (HTTPS).'
                : 'A secure context (HTTPS) is required.');
        }

        return {
            ok: issues.length === 0 && permission !== 'denied',
            permission: permission,
            issues: issues,
            canRequest: canRequest,
            mobile: isMobile(),
        };
    };

    const checklistLines = function () {
        if (isItalian()) {
            return [
                'Consenti le notifiche per questo sito (browser).',
                'Su Brave: Impostazioni → Privacy e sicurezza → attiva «Use Google services for push messaging» (altrimenti compare «push service error»).',
                'Su Brave/Chrome: sistema operativo → Notifiche → consentite per Brave/Chrome (non solo permesso sito).',
                'Su iOS: aggiungi il CRM a Home (Safari → Condividi → Aggiungi a Home) e aprilo dall’icona.',
                'Disattiva limitazioni batteria / “ottimizzazione” che sospendono il browser in background.',
                'Elimina vecchie sottoscrizioni su altri dispositivi (Preferences → disattiva/riattiva push su QUESTO browser).',
                'Ricarica la pagina e riprova «Verifica permessi» / attiva di nuovo le push.',
            ];
        }

        return [
            'Allow notifications for this site in the browser.',
            'On Brave: Settings → Privacy and security → enable “Use Google services for push messaging” (otherwise you get “push service error”).',
            'On Brave/Chrome: OS notification settings must allow the browser app (site permission alone is not enough).',
            'On iOS: add the CRM to the Home Screen, then open it from the icon.',
            'Disable battery restrictions that pause the browser in the background.',
            'Prune stale subscriptions from other devices (Preferences → toggle push OFF/ON on THIS browser).',
            'Reload the page and retry “Check permissions” / enable push again.',
        ];
    };

    /**
     * Whether this browser currently has a PushSubscription (local).
     * @return {Promise<boolean>}
     */
    const hasLocalSubscription = function () {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return Promise.resolve(false);
        }

        return navigator.serviceWorker.getRegistration(SW_PATH)
            .then(reg => {
                if (!reg) {
                    return false;
                }

                return reg.pushManager.getSubscription().then(sub => !!sub);
            })
            .catch(() => false);
    };

    const awaitSwUpdate = function (reg) {
        if (!reg || !reg.update) {
            return Promise.resolve(reg);
        }

        return reg.update()
            .then(() => {
                if (reg.installing) {
                    return new Promise(resolve => {
                        const worker = reg.installing;

                        worker.addEventListener('statechange', () => {
                            if (worker.state === 'activated' || worker.state === 'installed') {
                                resolve(reg);
                            }
                        });

                        setTimeout(() => resolve(reg), 4000);
                    });
                }

                return reg;
            })
            .catch(() => reg);
    };

    const subscribeWithRegistration = function () {
        ensureManifest();

        return navigator.serviceWorker.register(SW_URL, {scope: '/'})
            .then(reg => awaitSwUpdate(reg))
            .then(reg => navigator.serviceWorker.ready.then(() => reg))
            .then(reg => {
                return Espo.Ajax.getRequest('WebPush/action/publicKey')
                    .then(res => {
                        if (!res || !res.publicKey) {
                            throw new Error(isItalian()
                                ? 'Server push non configurato (chiave VAPID assente).'
                                : 'Push server is not configured (missing VAPID public key).');
                        }

                        const appKey = urlBase64ToUint8Array(res.publicKey);

                        // Drop a stale subscription (e.g. after VAPID key rotate),
                        // otherwise Chrome/Brave throw "Registration failed - push service error".
                        return reg.pushManager.getSubscription()
                            .then(existing => (existing ? existing.unsubscribe() : true))
                            .then(() => reg.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: appKey,
                            }));
                    });
            })
            .then(subscription => {
                return Espo.Ajax.postRequest('WebPush/action/subscribe', {
                    subscription: subscription.toJSON(),
                });
            })
            .catch(err => {
                const raw = (err && err.message) ? String(err.message) : String(err || '');
                const lower = raw.toLowerCase();
                let message = raw;

                if (
                    lower.indexOf('push service error') !== -1 ||
                    lower.indexOf('registration failed') !== -1 ||
                    lower.indexOf('aborterror') !== -1
                ) {
                    const brave = /Brave/i.test(navigator.userAgent || '') ||
                        !!(navigator.brave && navigator.brave.isBrave);

                    if (brave) {
                        message = isItalian()
                            ? 'Brave ha bloccato il servizio push. Apri brave://settings/privacy → attiva «Use Google services for push messaging», poi riprova. In alternativa usa Chrome.'
                            : 'Brave blocked the push service. Open brave://settings/privacy → enable “Use Google services for push messaging”, then retry. Or use Chrome.';
                    } else {
                        message = isItalian()
                            ? 'Registrazione push fallita (servizio push del browser). Controlla che le notifiche del sito siano Consentite, ricarica la pagina e riprova. Se usi un browser basato su Chromium con protezioni privacy, abilita i servizi Google per le push.'
                            : 'Push registration failed (browser push service). Ensure site notifications are Allowed, reload, and retry. On privacy-hardened Chromium browsers, enable Google services for push messaging.';
                    }
                }

                const wrapped = new Error(message);
                wrapped.code = 'push-service';
                wrapped.cause = err;

                throw wrapped;
            });
    };

    const SafehouseWebPush = {
        diagnose: diagnose,
        checklistLines: checklistLines,
        hasLocalSubscription: hasLocalSubscription,
        isMobile: isMobile,

        /**
         * Request browser permission when possible (default → prompt).
         * Does not show a checklist.
         */
        requestPermission: function () {
            const d = diagnose();

            if (!('Notification' in window)) {
                return Promise.reject(new Error(d.issues[0] || 'Unsupported'));
            }

            if (Notification.permission === 'granted') {
                return Promise.resolve('granted');
            }

            if (Notification.permission === 'denied') {
                return Promise.reject(new Error(d.issues.join(' ')));
            }

            return Notification.requestPermission();
        },

        /**
         * Opt-in: diagnose → request permission if needed → subscribe.
         * Throws with {code:'permissions', diagnose} when checks fail.
         */
        enable: function () {
            const d = diagnose();

            if (!d.ok && d.permission === 'denied') {
                const err = new Error(d.issues.join(' '));
                err.code = 'permissions';
                err.diagnose = d;

                return Promise.reject(err);
            }

            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                const err = new Error(d.issues.join(' '));
                err.code = 'unsupported';
                err.diagnose = d;

                return Promise.reject(err);
            }

            return this.requestPermission()
                .then(permission => {
                    if (permission !== 'granted') {
                        const err = new Error(isItalian()
                            ? 'Permesso notifiche non concesso.'
                            : 'Notification permission was not granted.');
                        err.code = 'permissions';
                        err.diagnose = diagnose();

                        throw err;
                    }

                    return subscribeWithRegistration();
                });
        },

        disable: function () {
            return navigator.serviceWorker.getRegistration(SW_PATH)
                .then(reg => {
                    if (!reg) {
                        return Espo.Ajax.postRequest('WebPush/action/unsubscribe', {});
                    }

                    return reg.pushManager.getSubscription()
                        .then(sub => {
                            const endpoint = sub ? sub.endpoint : null;

                            return (sub ? sub.unsubscribe() : Promise.resolve())
                                .then(() => Espo.Ajax.postRequest('WebPush/action/unsubscribe', {
                                    endpoint: endpoint,
                                }));
                        });
                })
                .catch(() => Espo.Ajax.postRequest('WebPush/action/unsubscribe', {}));
        },

        /**
         * Open guidance for site notification settings (browsers block deep-links).
         */
        openPermissionHelp: function () {
            const it = isItalian();
            const helpUrl = it
                ? 'https://support.google.com/chrome/answer/3220216?hl=it'
                : 'https://support.google.com/chrome/answer/3220216?hl=en';
            const msg = it
                ? 'Guida: Consenti le notifiche per il sito (lucchetto → Notifiche). Su Brave: Privacy → «Use Google services for push messaging». Controlla anche le Notifiche del sistema operativo per Brave. Su telefono: impostazioni app del browser → Notifiche.'
                : 'Guide: Allow site notifications (padlock → Notifications). On Brave: Privacy → “Use Google services for push messaging”. Also allow OS notifications for Brave. On phone: browser app Settings → Notifications.';

            window.open(helpUrl, '_blank', 'noopener,noreferrer');

            if (window.Espo && Espo.Ui) {
                Espo.Ui.warning(msg);
            }
        },
    };

    window.SafehouseWebPush = SafehouseWebPush;

    document.addEventListener('DOMContentLoaded', function () {
        try {
            ensureManifest();
        } catch (e) {}
    });
})();
