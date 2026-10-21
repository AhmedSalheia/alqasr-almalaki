import './bootstrap';
import { toBlob } from 'html-to-image';

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
        async render() {
            const node = document.getElementById('event-card');
            await document.fonts.ready;
            // Two renders: the first warms the image/font cache inside html-to-image (WebView quirk).
            const options = { pixelRatio: 2, cacheBust: false, backgroundColor: null, skipAutoScale: true };
            await toBlob(node, options);
            return await toBlob(node, options);
        },
        async run(action) {
            if (this.busy) return;
            this.busy = true;

            // Inside the NativePHP web view window.Native is injected (Android adds it after page load).
            const isNative = opts.native || typeof window.Native !== 'undefined';

            const download = (blob) => {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = opts.fileName;
                document.body.appendChild(a);
                a.click();
                setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
                window.toast(opts.messages.downloaded);
            };

            try {
                const blob = await this.render();
                if (!blob) throw new Error('empty');

                if (!isNative) {
                    download(blob);
                    return;
                }

                const form = new FormData();
                form.append('card', new File([blob], opts.fileName, { type: 'image/png' }));
                form.append('action', action);
                const res = await fetch(opts.url, {
                    method: 'POST',
                    body: form,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.ok) throw new Error(json.message || 'failed');
                if (json.mode === 'browser') {
                    download(blob);
                    return;
                }
                window.toast(json.mode === 'gallery' ? opts.messages.saved : opts.messages.shared);
            } catch (e) {
                console.error('[card]', e);
                window.toast(opts.messages.failed, 'error');
            } finally {
                this.busy = false;
            }
        },
    }));
});
