const STORAGE_SEEN = 'authz_notify_seen_id';
const STORAGE_SKIPPED = 'authz_notify_skipped_id';
const POLL_MS = 20000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function config() {
    const el = document.getElementById('authorization-notify-config');
    if (!el) {
        return null;
    }
    try {
        return JSON.parse(el.textContent || '{}');
    } catch (e) {
        return null;
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }
    return output;
}

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }
    return navigator.serviceWorker.register('/sw.js', { scope: '/' });
}

async function ensurePushSubscription(cfg) {
    if (!cfg?.enabled || !cfg?.vapidPublicKey) {
        return;
    }
    if (!('Notification' in window) || !('PushManager' in window)) {
        return;
    }

    const registration = await registerServiceWorker();
    if (!registration) {
        return;
    }

    let permission = Notification.permission;
    if (permission === 'default') {
        permission = await Notification.requestPermission();
    }
    if (permission !== 'granted') {
        return;
    }

    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(cfg.vapidPublicKey),
        });
    }

    await fetch(cfg.storeUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(subscription.toJSON()),
    });
}

function seenId() {
    return Number(sessionStorage.getItem(STORAGE_SEEN) || '0');
}

function setSeenId(id) {
    sessionStorage.setItem(STORAGE_SEEN, String(id || 0));
}

function skippedId() {
    return Number(sessionStorage.getItem(STORAGE_SKIPPED) || '0');
}

function setSkippedId(id) {
    sessionStorage.setItem(STORAGE_SKIPPED, String(id || 0));
}

function onAuthorizationsPage() {
    return window.location.pathname.replace(/\/+$/, '').endsWith('/authorizations');
}

let modalOpen = false;

async function showMandatoryModal(item) {
    if (!item || modalOpen || typeof Swal === 'undefined') {
        return;
    }
    if (onAuthorizationsPage()) {
        setSeenId(item.id);
        return;
    }
    if (item.id <= skippedId() || item.id <= seenId()) {
        return;
    }

    modalOpen = true;
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Otorisasi menunggu',
        html: `<p class="text-sm text-slate-600">${item.module_label || ''} · <strong>${item.document_number || ''}</strong></p>
               <p class="text-xs text-slate-500 mt-2">Dokumen baru menunggu otorisasi. Lewati jika sedang mengerjakan dokumen lain, atau buka antrian modul sekarang.</p>`,
        showDenyButton: true,
        showCancelButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        confirmButtonText: 'Buka antrian modul',
        denyButtonText: 'Lewati dulu',
        confirmButtonColor: '#4f46e5',
        denyButtonColor: '#64748b',
    });
    modalOpen = false;

    if (result.isConfirmed) {
        setSeenId(item.id);
        window.location.href = item.url;
        return;
    }

    if (result.isDenied) {
        setSkippedId(item.id);
        setSeenId(item.id);
    }
}

async function pollPending(cfg) {
    if (!cfg?.feedUrl) {
        return;
    }
    try {
        const response = await fetch(cfg.feedUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            return;
        }
        const data = await response.json();
        if (!data?.item?.id) {
            return;
        }
        if (!seenId()) {
            setSeenId(data.latest_id || data.item.id);
            return;
        }
        await showMandatoryModal(data.item);
    } catch (e) {
        // ignore transient network errors
    }
}

function bootAuthorizationNotify() {
    const cfg = config();
    if (!cfg?.canNotify) {
        return;
    }

    ensurePushSubscription(cfg).catch(() => {});
    pollPending(cfg);
    window.setInterval(() => pollPending(cfg), POLL_MS);

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'authorization-open' && event.data.url) {
                window.location.href = event.data.url;
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAuthorizationNotify);
} else {
    bootAuthorizationNotify();
}
