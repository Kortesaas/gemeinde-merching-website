// Optional presentation helpers. Only explicitly chosen settings are saved locally.
export function initDisplayPreferences() {
    const panel = document.querySelector('[data-display-panel]');
    if (!panel?.showModal) return;
    const root = document.documentElement;
    const form = panel.querySelector('[data-display-form]');
    const status = panel.querySelector('[data-display-status]');
    const output = panel.querySelector('[data-font-output]');
    const sizes = [100, 112.5, 125, 137.5, 150];
    const classes = { contrast: 'display-contrast', dark: 'display-dark', motion: 'display-reduce-motion', warm: 'display-warm', images: 'display-hide-images' };
    const storageKey = 'merching.display-preferences.v1';
    const storageHint = panel.querySelector('[data-storage-hint]');
    const unavailableStorageHint = 'Ihr Browser erlaubt keine Speicherung. Die Auswahl gilt deshalb nur für diese geöffnete Seite.';
    let storage;
    try { storage = window.localStorage; } catch { /* Browser privacy settings may deny storage. */ }
    if (!storage) storageHint.textContent = unavailableStorageHint;
    const parsePreferences = value => {
        try {
            const preferences = JSON.parse(value);
            return preferences && Number.isInteger(preferences.font) && preferences.font >= 0 && preferences.font < sizes.length
                && Object.keys(classes).every(name => typeof preferences[name] === 'boolean')
                && ['default', 'red-green', 'blue-yellow'].includes(preferences.vision) ? preferences : null;
        } catch { return null; }
    };
    const savePreferences = () => {
        const preferences = { font, vision: form.elements.vision.value };
        for (const name of Object.keys(classes)) preferences[name] = form.elements[name].checked;
        const customized = font || preferences.vision !== 'default' || Object.keys(classes).some(name => preferences[name]);
        try {
            if (!storage) throw new Error('Storage unavailable');
            if (customized) storage.setItem(storageKey, JSON.stringify(preferences));
            else storage.removeItem(storageKey);
            storageHint.textContent = 'Ihre Auswahl wird nur in diesem Browser gespeichert und gilt auch auf anderen Seiten und in geöffneten Tabs. „Alles zurücksetzen“ löscht sie.';
        } catch {
            storageHint.textContent = unavailableStorageHint;
        }
    };
    let font = 0, opener;

    const syncFont = () => {
        if (font) root.dataset.displayFont = String(font); else delete root.dataset.displayFont;
        output.textContent = `${String(sizes[font]).replace('.', ',')} %`;
        panel.querySelector('[data-font-step="-1"]').disabled = font === 0;
        panel.querySelector('[data-font-step="1"]').disabled = font === sizes.length - 1;
    };
    for (const button of panel.querySelectorAll('[data-font-step]')) button.addEventListener('click', () => {
        font = Math.max(0, Math.min(sizes.length - 1, font + Number(button.dataset.fontStep)));
        syncFont(); savePreferences();
    });
    panel.querySelector('[data-font-reset]').addEventListener('click', () => { font = 0; syncFont(); savePreferences(); });
    syncFont();

    const images = new Map();
    const imageResizeObserver = new ResizeObserver(entries => {
        for (const { target } of entries) {
            if (target.scrollHeight > target.clientHeight || target.scrollWidth > target.clientWidth) target.tabIndex = 0;
            else target.removeAttribute('tabindex');
        }
    });
    const hideImages = () => {
        for (const image of document.querySelectorAll('main img:not([data-display-essential])')) {
            if (images.has(image)) continue;
            const original = image.closest('picture') ?? image;
            const frame = document.createElement('span');
            frame.className = 'display-image-frame';
            original.before(frame);
            frame.append(original);
            let replacement = null;
            if (image.alt.trim()) {
                replacement = document.createElement('span');
                replacement.className = 'display-image-description';
                replacement.textContent = `Bild: ${image.alt.trim()}`;
                frame.append(replacement);
                imageResizeObserver.observe(replacement);
            }
            image.setAttribute('data-display-hidden-image', '');
            images.set(image, { original, frame, replacement });
        }
    };
    const imageObserver = new MutationObserver(hideImages);
    const syncImages = () => {
        imageObserver.disconnect();
        if (form.elements.images.checked) {
            hideImages();
            imageObserver.observe(document.querySelector('main'), { childList: true, subtree: true });
        } else {
            for (const [image, { original, frame, replacement }] of images) {
                image.removeAttribute('data-display-hidden-image');
                if (replacement) imageResizeObserver.unobserve(replacement);
                frame.replaceWith(original);
            }
            images.clear();
        }
    };
    const applyPreferences = preferences => {
        for (const [name, className] of Object.entries(classes)) {
            form.elements[name].checked = preferences?.[name] ?? false;
            root.classList.toggle(className, form.elements[name].checked);
        }
        form.elements.vision.value = preferences?.vision ?? 'default';
        if (form.elements.vision.value === 'default') delete root.dataset.displayVision;
        else root.dataset.displayVision = form.elements.vision.value;
        font = preferences?.font ?? 0;
        syncFont(); syncImages();
    };
    try { applyPreferences(parsePreferences(storage?.getItem(storageKey) ?? null)); } catch {
        storageHint.textContent = unavailableStorageHint;
        applyPreferences(null);
    }
    window.addEventListener('storage', event => {
        if (event.storageArea !== storage || (event.key !== storageKey && event.key !== null)) return;
        applyPreferences(parsePreferences(event.newValue));
        status.textContent = 'Darstellung aus einem anderen Tab übernommen.';
    });
    window.addEventListener('pageshow', event => {
        if (!event.persisted || !storage) return;
        try { applyPreferences(parsePreferences(storage.getItem(storageKey))); } catch { /* Keep the in-memory settings. */ }
    });
    form.addEventListener('change', () => {
        for (const [name, className] of Object.entries(classes)) root.classList.toggle(className, form.elements[name].checked);
        if (form.elements.vision.value === 'default') delete root.dataset.displayVision;
        else root.dataset.displayVision = form.elements.vision.value;
        syncImages(); savePreferences();
        status.textContent = 'Darstellung angepasst.';
    });
    const motionPreference = matchMedia('(prefers-reduced-motion: reduce)');
    const syncMotionHint = () => {
        panel.querySelector('[data-motion-hint]').textContent = motionPreference.matches
            ? 'Ihr Gerät reduziert Bewegungen bereits. Diese Einstellung bleibt immer wirksam.'
            : 'Die Bewegungseinstellung Ihres Geräts wird immer berücksichtigt.';
    };
    syncMotionHint();
    motionPreference.addEventListener('change', syncMotionHint);

    const start = panel.querySelector('[data-read-start]');
    const stops = [...document.querySelectorAll('[data-read-stop]')];
    const hint = panel.querySelector('[data-read-hint]');
    const readingStatus = panel.querySelector('[data-read-status]');
    const speech = window.speechSynthesis;
    const supported = !!speech && typeof window.SpeechSynthesisUtterance === 'function';
    let voice, reading = false, generation = 0, currentUtterance;
    const syncReader = () => {
        start.disabled = !voice || reading;
        for (const stop of stops) {
            stop.disabled = !reading;
            if (!panel.contains(stop)) stop.hidden = !reading;
        }
    };
    const updateVoices = () => {
        try {
            voice = supported ? speech.getVoices().find(item => item.localService === true && /^de(?:[-_]|$)/i.test(item.lang)) : undefined;
        } catch { voice = undefined; }
        hint.textContent = !supported
            ? 'Dieser Browser unterstützt das Vorlesen nicht. Sie können die Vorlesefunktion Ihres Geräts oder einen Screenreader verwenden.'
            : voice ? 'Liest den sichtbaren Hauptinhalt mit einer lokalen deutschen Stimme. Eingaben und Formulare werden ausgelassen.'
                : 'Keine lokale deutsche Stimme verfügbar. Prüfen Sie die Spracheinstellungen Ihres Geräts. Online-Stimmen werden nicht verwendet.';
        syncReader();
    };
    const stopReading = (message = 'Vorlesen gestoppt.') => {
        const focusedStop = stops.includes(document.activeElement);
        generation++;
        if (reading && supported) speech.cancel();
        currentUtterance = null;
        reading = false;
        readingStatus.textContent = message;
        syncReader();
        if (focusedStop) {
            if (panel.open) (start.disabled ? panel.querySelector('.display-panel__close') : start).focus();
            else document.querySelector('[data-display-trigger]')?.focus({ preventScroll: true });
        }
    };
    for (const stop of stops) stop.addEventListener('click', () => {
        stopReading();
    });
    start.addEventListener('click', () => {
        updateVoices();
        if (!voice || reading) return;
        const main = document.querySelector('main');
        const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT, {
            acceptNode: node => {
                const parent = node.parentElement;
                const closed = parent?.closest('details:not([open])');
                return parent && !parent.closest('form, input, textarea, select, button, nav, script, style, [hidden], [aria-hidden="true"]')
                    && (!closed || closed.querySelector(':scope > summary')?.contains(parent))
                    && parent.getClientRects().length && getComputedStyle(parent).visibility !== 'hidden'
                    ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            },
        });
        const parts = [];
        while (walker.nextNode()) parts.push(walker.currentNode.textContent.trim());
        let text = parts.join(' ').replace(/\s+/g, ' ').trim();
        if (!text) { readingStatus.textContent = 'Auf dieser Seite gibt es keinen vorlesbaren Hauptinhalt.'; return; }
        const chunks = [];
        while (text.length > 220) {
            const space = text.lastIndexOf(' ', 220);
            const end = space > 0 ? space : 220;
            chunks.push(text.slice(0, end)); text = text.slice(end).trimStart();
        }
        if (text) chunks.push(text);
        const run = ++generation;
        const selectedVoice = voice;
        reading = true;
        readingStatus.textContent = 'Der Hauptinhalt wird vorgelesen.';
        syncReader();
        const next = () => {
            if (generation !== run) return;
            if (!chunks.length) { stopReading('Vorlesen abgeschlossen.'); return; }
            currentUtterance = new SpeechSynthesisUtterance(chunks.shift());
            currentUtterance.voice = selectedVoice;
            currentUtterance.lang = selectedVoice.lang;
            currentUtterance.onend = next;
            currentUtterance.onerror = () => {
                if (generation === run) stopReading('Das Vorlesen ist in diesem Browser gerade nicht möglich. Bitte versuchen Sie es erneut oder nutzen Sie die Vorlesefunktion Ihres Geräts.');
            };
            try { speech.speak(currentUtterance); } catch { currentUtterance.onerror(); }
        };
        // Read after dismissing the panel; stop remains available in the panel and footer.
        panel.close();
        next();
    });
    let voicesInitialized = false;
    for (const trigger of document.querySelectorAll('[data-display-trigger]')) {
        trigger.hidden = false;
        trigger.addEventListener('click', () => {
            opener = trigger;
            if (!voicesInitialized && supported) { speech.addEventListener('voiceschanged', updateVoices); voicesInitialized = true; }
            updateVoices();
            panel.showModal();
            trigger.setAttribute('aria-expanded', 'true');
        });
    }
    panel.addEventListener('close', () => {
        opener?.setAttribute('aria-expanded', 'false');
        opener?.focus({ preventScroll: true });
    });
    // Clicking outside the compact dialog (including its visible launcher) dismisses it.
    panel.addEventListener('click', event => {
        const rect = panel.getBoundingClientRect();
        if (event.target === panel && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) panel.close();
    });
    form.addEventListener('reset', event => {
        event.preventDefault();
        for (const [name, className] of Object.entries(classes)) {
            form.elements[name].checked = false;
            root.classList.remove(className);
        }
        form.elements.vision.value = 'default';
        delete root.dataset.displayVision;
        font = 0; syncFont(); syncImages(); savePreferences(); stopReading('');
        status.textContent = 'Alle Darstellungseinstellungen zurückgesetzt.';
    });
    window.addEventListener('pagehide', () => stopReading(''));
}
