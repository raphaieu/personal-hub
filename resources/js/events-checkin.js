import { Html5Qrcode } from 'html5-qrcode';

let html5QrCode = null;
let scanning = false;
let bootScheduled = false;

function parseCheckInPayload(text) {
    const trimmed = text.trim();
    try {
        const url = new URL(trimmed);
        const event = url.searchParams.get('event');
        const guest = url.searchParams.get('guest');
        if (event && guest) {
            return { event, guest };
        }
    } catch {
        /* fallback: raw query ?event=&guest= */
    }

    const q = trimmed.includes('?') ? trimmed.slice(trimmed.indexOf('?')) : '';
    if (q) {
        const params = new URLSearchParams(q.startsWith('?') ? q.slice(1) : q);
        const event = params.get('event');
        const guest = params.get('guest');
        if (event && guest) {
            return { event, guest };
        }
    }

    return null;
}

async function stopScanner() {
    if (!html5QrCode) {
        return;
    }

    if (scanning) {
        try {
            await html5QrCode.stop();
        } catch {
            //
        }
    }

    try {
        html5QrCode.clear();
    } catch {
        //
    }

    html5QrCode = null;
    scanning = false;
}

async function startScanner(component) {
    if (scanning) {
        return;
    }

    const regionId = 'events-checkin-qr-region';
    const region = document.getElementById(regionId);
    const root = document.getElementById('events-checkin-root');

    if (!region || !root || root.dataset.scannerIdle !== '1') {
        return;
    }

    await stopScanner();

    html5QrCode = new Html5Qrcode(regionId, { verbose: false });

    let cameras;
    try {
        cameras = await Html5Qrcode.getCameras();
    } catch {
        return;
    }

    if (!cameras?.length) {
        return;
    }

    const back = cameras.find((c) => /back|rear|environment|tr[aá]s/i.test(c.label));
    const cameraId = back ? back.id : cameras[0].id;

    const side = Math.min(280, Math.floor(window.innerWidth - 32));
    const config = {
        fps: 12,
        qrbox: { width: side, height: side },
        aspectRatio: 1,
    };

    try {
        await html5QrCode.start(
            { deviceId: { exact: cameraId } },
            config,
            async (decodedText) => {
                const payload = parseCheckInPayload(decodedText);
                if (!payload || !component) {
                    return;
                }

                await stopScanner();
                await component.call('loadGuestFromScan', payload.event, payload.guest);
            },
            () => {},
        );
        scanning = true;
    } catch {
        scanning = false;
    }
}

function scheduleBootScanner() {
    if (bootScheduled) {
        return;
    }

    bootScheduled = true;

    window.requestAnimationFrame(() => {
        bootScheduled = false;

        const root = document.getElementById('events-checkin-root');
        if (!root || root.dataset.scannerIdle !== '1') {
            return;
        }

        const wireId = root.getAttribute('wire:id');
        if (!wireId || !window.Livewire) {
            return;
        }

        const component = window.Livewire.find(wireId);
        if (!component) {
            return;
        }

        startScanner(component);
    });
}

document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', () => {
        scheduleBootScanner();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    scheduleBootScanner();
});
