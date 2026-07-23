/*
 | Global loading feedback for full-page navigations and form submits.
 |
 | Livewire already shows its own inline wire:loading states; this covers the
 | gaps: clicking a link or submitting a normal <form> (login, "Bayar Sekarang",
 | booking lookup, cancel) now shows a top progress bar and turns the clicked
 | button into a spinner, so a slow request (e.g. creating a DOKU payment) never
 | looks like nothing happened.
 |
 | Opt out on any element with data-no-loading.
 */

function injectStyles() {
    if (document.getElementById('np-loading-styles')) return;
    const css = `
    #np-progress{position:fixed;top:0;left:0;height:3px;width:0;z-index:9999;
      background:linear-gradient(90deg,#0d9488,#2dd4bf);
      box-shadow:0 0 8px rgba(45,212,191,.6);opacity:0;
      transition:width .2s ease,opacity .3s ease;pointer-events:none}
    #np-progress.active{opacity:1}
    .np-spin{display:inline-block;width:1em;height:1em;vertical-align:-.15em;
      margin-right:.5em;border:2px solid currentColor;border-right-color:transparent;
      border-radius:50%;animation:np-spin .6s linear infinite}
    @keyframes np-spin{to{transform:rotate(360deg)}}
    [aria-busy="true"]{cursor:progress;opacity:.85}`;
    const style = document.createElement('style');
    style.id = 'np-loading-styles';
    style.textContent = css;
    document.head.appendChild(style);
}

let bar;
let timer;

function startBar() {
    if (!bar) return;
    clearInterval(timer);
    bar.classList.add('active');
    let width = 8;
    bar.style.width = width + '%';
    // Ease toward 90% while the request is in flight; the page unload finishes it.
    timer = setInterval(() => {
        width += (90 - width) * 0.12;
        bar.style.width = width.toFixed(1) + '%';
    }, 200);
}

function finishBar() {
    if (!bar) return;
    clearInterval(timer);
    bar.style.width = '100%';
    setTimeout(() => {
        bar.classList.remove('active');
        bar.style.width = '0';
    }, 300);
}

function spinButton(btn) {
    if (!btn || btn.dataset.npBusy) return;
    btn.dataset.npBusy = '1';
    btn.setAttribute('aria-busy', 'true');
    // Keep width stable so the layout doesn't jump.
    const rect = btn.getBoundingClientRect();
    btn.style.minWidth = Math.ceil(rect.width) + 'px';
    btn.dataset.npLabel = btn.innerHTML;
    const busyText = btn.dataset.loadingText || 'Memproses…';
    btn.innerHTML = `<span class="np-spin"></span>${busyText}`;
    // Disable a touch later so the value still posts with the form.
    setTimeout(() => {
        btn.disabled = true;
    }, 0);
}

function isPlainLeftClick(e) {
    return !e.defaultPrevented && e.button === 0 &&
        !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey;
}

function setup() {
    injectStyles();
    bar = document.getElementById('np-progress');
    if (!bar) {
        bar = document.createElement('div');
        bar.id = 'np-progress';
        document.body.appendChild(bar);
    }

    // Full-page form submits (not Livewire — those use wire: attributes).
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-no-loading') || form.hasAttribute('wire:submit')) return;
        if (form.getAttribute('onsubmit')) { /* still fine */ }

        const submitter = e.submitter || form.querySelector('[type="submit"]');
        if (submitter && !submitter.hasAttribute('data-no-loading')) {
            spinButton(submitter);
        }
        startBar();
    });

    // Same-origin link navigations.
    document.addEventListener('click', (e) => {
        if (!isPlainLeftClick(e)) return;
        const link = e.target.closest('a[href]');
        if (!link) return;
        if (link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('data-no-loading')) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        // Pure in-page anchor to the same path → no navigation.
        if (url.pathname === window.location.pathname && url.hash) return;

        startBar();
    });

    // Reset when returning via the back/forward cache.
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) {
            finishBar();
            document.querySelectorAll('[data-np-busy]').forEach((btn) => {
                btn.disabled = false;
                btn.removeAttribute('aria-busy');
                if (btn.dataset.npLabel) btn.innerHTML = btn.dataset.npLabel;
                delete btn.dataset.npBusy;
                delete btn.dataset.npLabel;
                btn.style.minWidth = '';
            });
        }
    });

    window.addEventListener('pagehide', finishBar);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup);
} else {
    setup();
}
