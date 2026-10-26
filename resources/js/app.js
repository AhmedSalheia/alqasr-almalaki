import './bootstrap';
import { toBlob } from 'html-to-image';

/* ------------------------------------------------------- Card rendering helpers
 * html-to-image normally embeds EVERY web font of the page (dozens of files) into one
 * huge SVG, which Android's WebView can fail to decode ("[object Event]"). Fetching the
 * built font files from JS was not reliable on phones either, so the server hands us one
 * small stylesheet with only the card's fonts already embedded (/card/fonts.css), and the
 * card's logo is already a data: URL. Nothing is fetched while drawing.
 */
let cardFontCss = null;

function blobToDataUrl(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(new Error('could not read file'));
        reader.readAsDataURL(blob);
    });
}

async function urlToDataUrl(url) {
    const res = await fetch(url, { cache: 'force-cache' });
    if (!res.ok) throw new Error('could not load ' + url.split('/').pop() + ' (' + res.status + ')');
    return blobToDataUrl(await res.blob());
}

async function loadCardFontCss(url) {
    if (cardFontCss) return cardFontCss;
    const res = await fetch(url, { headers: { Accept: 'text/css' } });
    if (!res.ok) throw new Error('could not load card fonts (' + res.status + ')');
    const css = await res.text();
    if (!css.includes('@font-face')) throw new Error('card fonts are empty');
    // Make sure the same faces are usable by the page too, so measurements match the drawing.
    if (!document.getElementById('card-font-css')) {
        const style = document.createElement('style');
        style.id = 'card-font-css';
        style.textContent = css;
        document.head.appendChild(style);
    }
    return (cardFontCss = css);
}

async function inlineImages(node) {
    for (const img of node.querySelectorAll('img')) {
        if (img.src.startsWith('data:')) continue;
        img.src = await urlToDataUrl(img.src);
        await img.decode().catch(() => {});
    }
}

function describeError(e) {
    if (e instanceof Event) {
        return 'the card image could not be drawn (' + e.type + ')';
    }
    return (e && e.message) || String(e);
}

/* ------------------------------------------------------------------ Theme */
const media = window.matchMedia('(prefers-color-scheme: dark)');
function applyTheme() {
    const pref = document.documentElement.dataset.theme || 'system';
    const dark = pref === 'dark' || (pref === 'system' && media.matches);
    document.documentElement.classList.toggle('dark', dark);
}
media.addEventListener?.('change', applyTheme);
document.addEventListener('livewire:navigated', applyTheme);

/* ------------------------------------------------------------------ Toasts */
window.toast = (message, type = 'success') => {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
};

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /*
     * Calls a Livewire method every `seconds`, passing navigator.onLine, so the server can
     * skip network work while the phone is offline (no frozen screens waiting on timeouts).
     */
    Alpine.data('netPoll', (method, seconds, { immediate = true, onlyOnline = false } = {}) => ({
        timer: null,
        init() {
            const call = () => {
                if (onlyOnline && !navigator.onLine) return;
                this.$wire.call(method, navigator.onLine);
            };
            if (immediate) call();
            if (seconds > 0) this.timer = setInterval(() => { if (!document.hidden) call(); }, seconds * 1000);
            this.onOnline = () => call();
            window.addEventListener('online', this.onOnline);
        },
        destroy() {
            clearInterval(this.timer);
            window.removeEventListener('online', this.onOnline);
        },
    }));

    Alpine.data('toaster', () => ({
        items: [],
        push(detail) {
            const id = Date.now() + Math.random();
            this.items.push({ id, ...detail });
            setTimeout(() => this.items = this.items.filter(i => i.id !== id), 3800);
        },
    }));

    /* Horizontal swipe -> previous / next (direction aware for RTL) */
    Alpine.data('swipe', (onPrev, onNext) => ({
        x: 0, y: 0,
        start(e) { this.x = e.touches[0].clientX; this.y = e.touches[0].clientY; },
        end(e) {
            const dx = e.changedTouches[0].clientX - this.x;
            const dy = e.changedTouches[0].clientY - this.y;
            if (Math.abs(dx) < 60 || Math.abs(dx) < Math.abs(dy) * 1.5) return;
            const rtl = document.documentElement.dir === 'rtl';
            // Swiping toward the reading direction's start goes forward in time.
            const forward = rtl ? dx > 0 : dx < 0;
            forward ? onNext() : onPrev();
        },
    }));

    /* Fit a fixed-size element (the event card) into its container */
    Alpine.data('fitScale', (baseWidth) => ({
        scale: 1,
        init() {
            const fit = () => { this.scale = Math.min(1, this.$el.clientWidth / baseWidth); };
            fit();
            new ResizeObserver(fit).observe(this.$el);
        },
    }));

    /* Render the event card to PNG and save / share it */
    Alpine.data('cardExporter', (opts) => ({
        busy: false,
        withTimeout(promise, ms, label) {
            return Promise.race([
                promise,
                new Promise((_, reject) => setTimeout(() => reject(new Error(label + ' timed out')), ms)),
            ]);
        },
        async render() {
            const node = document.getElementById('event-card');
            if (!node) throw new Error('card element not found');
            await this.withTimeout(inlineImages(node), 20000, 'loading images');
            const base = { pixelRatio: 2, cacheBust: false, backgroundColor: null, skipAutoScale: true };
            // Two renders: the first warms the image/font cache inside html-to-image (WebView quirk).
            const draw = async (options) => {
                await this.withTimeout(toBlob(node, options), 30000, 'render');
                return await this.withTimeout(toBlob(node, options), 30000, 'render');
            };

            try {
                const fontEmbedCSS = await this.withTimeout(loadCardFontCss(opts.fontsUrl), 20000, 'loading fonts');
                await document.fonts.ready;
                return await draw({ ...base, fontEmbedCSS });
            } catch (e) {
                // Last resort: draw without embedded fonts (system fonts) rather than fail.
                console.warn('[card] render with fonts failed, retrying without', e);
                this.report('render-fonts', describeError(e));
                return await draw({ ...base, skipFonts: true });
            }
        },
        csrf() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },
        report(stage, error) {
            // Send the failure to laravel.log so it shows up in `php artisan native:tail`.
            try {
                fetch(opts.logUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify({ _token: this.csrf(), stage, message: describeError(error), ua: navigator.userAgent }),
                });
            } catch (e) { /* ignore */ }
        },
        async post(action, blob, asBase64) {
            let body, headers = { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' };
            if (asBase64) {
                const dataUrl = await new Promise((resolve, reject) => {
                    const r = new FileReader();
                    r.onload = () => resolve(r.result);
                    r.onerror = () => reject(new Error('could not read image'));
                    r.readAsDataURL(blob);
                });
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify({ _token: this.csrf(), action, card_base64: dataUrl });
            } else {
                body = new FormData();
                // The token also goes in the body: the native web view does not always pass custom headers to PHP.
                body.append('_token', this.csrf());
                body.append('card', new File([blob], opts.fileName, { type: 'image/png' }));
                body.append('action', action);
            }
            const res = await this.withTimeout(fetch(opts.url, { method: 'POST', body, headers }), 60000, 'upload');
            const json = await res.json().catch(() => ({}));
            return { res, json };
        },
        download(blob) {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = opts.fileName;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
            window.toast(opts.messages.downloaded);
        },
        async run(action) {
            if (this.busy) return;
            this.busy = true;

            // Inside the NativePHP web view window.Native is injected (Android adds it after page load).
            const isNative = opts.native || typeof window.Native !== 'undefined';
            let stage = 'render';

            try {
                const blob = await this.render();
                if (!blob) throw new Error('empty image');

                if (!isNative) {
                    this.download(blob);
                    return;
                }

                stage = 'upload';
                let { res, json } = await this.post(action, blob, false);
                if (!res.ok && json.retry_base64) {
                    // The multipart upload did not reach PHP: send the same PNG as base64 text instead.
                    ({ res, json } = await this.post(action, blob, true));
                }
                if (!res.ok || !json.ok) {
                    stage = json.stage || 'server';
                    throw new Error(json.message || ('HTTP ' + res.status));
                }
                if (json.mode === 'browser') {
                    this.download(blob);
                    return;
                }
                window.toast(json.mode === 'gallery' ? opts.messages.saved : opts.messages.shared);
            } catch (e) {
                console.error('[card]', stage, e);
                this.report(stage, e);
                window.toast(opts.messages.failed + ' (' + stage + ': ' + describeError(e) + ')', 'error');
            } finally {
                this.busy = false;
            }
        },
    }));
});
