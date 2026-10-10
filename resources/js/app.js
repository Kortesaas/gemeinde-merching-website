import.meta.glob('../images/*', { eager: true, query: '?url', import: 'default' });
import { initDisplayPreferences } from './display-preferences.js';
// Small, same-origin enhancements. Native links, GET search and form fields remain the fallback.
// Enhanced layouts (e.g. the overlay menu) only apply once this script runs.
document.documentElement.classList.add('js');
initDisplayPreferences();
const reduceMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('display-reduce-motion');
// Native overscroll reveals the page canvas: white at the top, footer blue below.
const publicFooter = document.querySelector('.site-footer');
if (publicFooter) {
    const syncOverscroll = () => document.documentElement.classList.toggle('has-footer-overscroll',
        window.scrollY > 0 && publicFooter.getBoundingClientRect().top < window.innerHeight);
    syncOverscroll();
    window.addEventListener('scroll', syncOverscroll, { passive: true });
    window.addEventListener('resize', syncOverscroll);
    window.addEventListener('pageshow', syncOverscroll);
    new ResizeObserver(syncOverscroll).observe(document.body);
}
// Matches the CSS breakpoints: wide layouts start at 64rem (1024px).
const narrow = matchMedia('(max-width: 63.99rem)');
for (const menu of document.querySelectorAll('[data-navigation], [data-cms-navigation]')) {
    const menuNarrow = menu.matches('[data-cms-navigation]') ? matchMedia('(max-width: 64rem)') : narrow;
    const sync = () => { menu.open = !menuNarrow.matches; };
    sync(); menuNarrow.addEventListener('change', sync);
    menu.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menuNarrow.matches) {
            menu.open = false; menu.querySelector('summary').focus();
        }
    });
}
for (const branch of document.querySelectorAll('[data-nav-branch]')) {
    branch.addEventListener('toggle', () => {
        if (branch.open) for (const other of document.querySelectorAll('[data-nav-branch]')) {
            if (other !== branch && !other.contains(branch) && !branch.contains(other)) other.open = false;
        }
    });
    branch.addEventListener('keydown', event => {
        if (event.key === 'Escape' && branch.open) { event.stopPropagation(); branch.open = false; branch.querySelector('summary').focus(); }
    });
}
// Wide screens: an open panel closes when focus or a click moves outside its menu entry.
const closeBranches = target => {
    if (narrow.matches) return;
    for (const branch of document.querySelectorAll('.site-navigation [data-nav-branch][open]')) {
        if (!(branch.closest('[data-nav-item]') ?? branch).contains(target)) branch.open = false;
    }
};
document.addEventListener('click', event => closeBranches(event.target));
document.addEventListener('focusin', event => closeBranches(event.target));
// Mouse users on wide screens: an entry opens after a short pause and closes on leaving.
// The label stays a normal link to the overview; keyboard and touch use the toggle.
const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
for (const item of document.querySelectorAll('.site-navigation [data-nav-item]')) {
    const branch = item.querySelector('[data-nav-branch]');
    let timer;
    item.addEventListener('pointerenter', event => {
        if (event.pointerType !== 'mouse' || narrow.matches || !finePointer.matches) return;
        clearTimeout(timer); timer = setTimeout(() => { if (!branch.open) { branch.open = true; branch.dataset.hoverOpened = '1'; } }, 140);
    });
    item.addEventListener('pointerleave', event => {
        if (event.pointerType !== 'mouse' || narrow.matches) return;
        clearTimeout(timer); timer = setTimeout(() => { if (!branch.contains(document.activeElement) || document.activeElement === branch.querySelector('summary')) { branch.open = false; delete branch.dataset.hoverOpened; } }, 220);
    });
    // A click on the toggle right after hover-opening keeps the panel open instead of closing it.
    branch.querySelector('summary').addEventListener('click', event => {
        if (branch.dataset.hoverOpened && branch.open) event.preventDefault();
        delete branch.dataset.hoverOpened;
    });
}
// Event calendar ⇄ list: pointing at a day highlights its events and vice versa.
// Month arrows replace both the calendar and its event list; native links remain usable without JavaScript.
const calendarStatus = document.createElement('p');
calendarStatus.className = 'visually-hidden'; calendarStatus.setAttribute('role', 'status');
const initEventCalendar = calendar => {
    if (!calendar) return;
    const view = calendar.closest('[data-events-view]');
    if (!view) return;
    if (!calendarStatus.isConnected) view.after(calendarStatus);
    const items = [...document.querySelectorAll('[data-event-dates]')];
    const cells = [...calendar.querySelectorAll('[data-cal-date]')];
    const mark = dates => {
        for (const item of items) item.classList.toggle('is-highlighted', dates.some(date => item.dataset.eventDates.split(' ').includes(date)));
        for (const cell of cells) cell.classList.toggle('is-highlighted', dates.includes(cell.dataset.calDate));
    };
    for (const cell of cells) {
        for (const type of ['pointerenter', 'focusin']) cell.addEventListener(type, () => mark([cell.dataset.calDate]));
        for (const type of ['pointerleave', 'focusout']) cell.addEventListener(type, () => mark([]));
    }
    for (const item of items) {
        item.onpointerenter = () => mark(item.dataset.eventDates.split(' '));
        item.onpointerleave = () => mark([]);
    }
    for (const link of view.querySelectorAll('[data-cal-nav], [data-month-nav]')) link.addEventListener('click', async event => {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        if (view.hasAttribute('aria-busy')) return;
        view.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(link.href, { credentials: 'omit', headers: { Accept: 'text/html' } });
            if (!response.ok) throw new Error('calendar');
            const next = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('[data-events-view]');
            if (!next) throw new Error('calendar');
            const wasOpen = view.querySelector('[data-calendar-disclosure]').open;
            next.querySelector('[data-calendar-disclosure]').open = wasOpen;
            view.replaceWith(next);
            const url = new URL(link.href); url.hash = '';
            history.replaceState(null, '', url.pathname + url.search);
            document.querySelector('.filter-bar input[name=monat]').value = url.searchParams.get('monat');
            initEventCalendar(next.querySelector('[data-event-calendar]'));
            const navAttribute = link.hasAttribute('data-month-nav') ? 'data-month-nav' : 'data-cal-nav';
            next.querySelector(`[${navAttribute}="${link.getAttribute(navAttribute)}"]`)?.focus({ preventScroll: true });
            calendarStatus.textContent = next.querySelector('.event-calendar__title')?.textContent.trim() + ': ' + next.querySelector('.result-count')?.textContent.trim();
        } catch { location.assign(link.href); }
    });
};
initEventCalendar(document.querySelector('[data-event-calendar]'));
// Site alerts can be hidden for the current page view (nothing is stored).
for (const button of document.querySelectorAll('[data-alert-dismiss]')) {
    button.hidden = false;
    button.addEventListener('click', () => {
        const alert = button.closest('[data-alert]');
        alert.classList.add('is-leaving');
        const done = () => { alert.hidden = true; document.getElementById('inhalt')?.focus({ preventScroll: true }); };
        reduceMotion() ? done() : alert.addEventListener('animationend', done, { once: true });
    });
}
const searchDialog = document.querySelector('[data-search-dialog]');
if (searchDialog?.showModal) {
    let opener;
    for (const trigger of document.querySelectorAll('[data-search-trigger]')) trigger.addEventListener('click', event => {
        event.preventDefault(); opener = trigger; searchDialog.showModal(); searchDialog.querySelector('[data-search-input]').focus();
    });
    // Close with a short fade (the header in the panel matches the page header, so nothing jumps).
    const closeSearch = () => {
        if (reduceMotion()) { searchDialog.close(); return; }
        searchDialog.classList.add('is-closing');
        searchDialog.addEventListener('animationend', () => { searchDialog.classList.remove('is-closing'); searchDialog.close(); }, { once: true });
    };
    searchDialog.querySelector('[data-dialog-close]').addEventListener('click', closeSearch);
    // Escape is handled here (a search field would otherwise swallow it); suggestions close first in the form.
    searchDialog.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        if (!searchDialog.classList.contains('is-closing')) closeSearch();
    });
    searchDialog.addEventListener('cancel', event => {
        event.preventDefault();
        if (!searchDialog.classList.contains('is-closing')) closeSearch();
    });
    searchDialog.addEventListener('close', () => opener?.focus());
    const all = searchDialog.querySelector('[data-search-all]'), overlayInput = searchDialog.querySelector('[data-search-input]');
    const shortcuts = searchDialog.querySelector('[data-search-shortcuts]');
    overlayInput.addEventListener('input', () => {
        const phrase = overlayInput.value.trim();
        all.href = '/suche' + (phrase ? '?q=' + encodeURIComponent(phrase) : '');
        if (shortcuts) shortcuts.hidden = Boolean(phrase);
    });
}
for (const form of document.querySelectorAll('[data-search-form]')) {
    const input = form.querySelector('[data-search-input]');
    const list = form.querySelector('[data-suggestions]');
    const status = form.querySelector('[data-search-status]');
    let timer, controller, serial = 0, selected = -1;
    input.setAttribute("role", "combobox");
    input.setAttribute("aria-autocomplete", "list");
    list.setAttribute("role", "listbox");
    list.setAttribute("aria-label", "Suchvorschläge");
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-expanded', 'false');
    const clear = () => { selected = -1; input.removeAttribute('aria-activedescendant'); list.replaceChildren(); list.hidden = true; input.setAttribute('aria-expanded', 'false'); status.textContent = ''; };
    input.addEventListener('input', () => {
        clearTimeout(timer); controller?.abort(); const request = ++serial; clear();
        const phrase = input.value.trim(); if (!phrase) return;
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const typeFilter = form.querySelector('[name=type]')?.value;
                const response = await fetch('/suche/vorschlaege?q=' + encodeURIComponent(phrase) + (typeFilter ? '&type=' + encodeURIComponent(typeFilter) : ''), { signal: controller.signal, credentials: 'omit', headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const data = await response.json(); if (request !== serial) return;
                for (const result of data.results) {
                    const li = document.createElement('li'), a = document.createElement('a'), title = document.createElement('span'), type = document.createElement('small');
                    const target = new URL(result.url, location.origin);
                    // Server URLs can use the configured canonical host; only navigate to their local path.
                    a.href = target.pathname; a.id = list.id + '-' + list.children.length; a.setAttribute('role', 'option'); a.setAttribute('aria-selected', 'false'); a.tabIndex = -1; li.setAttribute('role', 'presentation'); type.textContent = result.type;
                    // Emphasise the typed text without inserting markup from the response.
                    const at = result.title.toLowerCase().indexOf(phrase.toLowerCase());
                    if (at >= 0) { const mark = document.createElement('mark'); mark.textContent = result.title.slice(at, at + phrase.length); title.append(result.title.slice(0, at), mark, result.title.slice(at + phrase.length)); }
                    else title.textContent = result.title;
                    a.append(title, type); li.append(a); list.append(li);
                }
                list.hidden = !list.children.length; input.setAttribute('aria-expanded', String(!list.hidden));
                status.textContent = list.children.length ? `${list.children.length} Vorschläge verfügbar. Mit Pfeil nach unten auswählen oder Eingabetaste zum Suchen.` : 'Keine Vorschläge. Mit der Eingabetaste alle Ergebnisse suchen.';
            } catch (error) { if (error.name !== 'AbortError') clear(); }
        }, 250);
    });
    // The floating list closes when focus or a click leaves this search form.
    form.addEventListener('focusout', event => { if (!form.contains(event.relatedTarget)) { controller?.abort(); serial++; clear(); } });
    document.addEventListener('pointerdown', event => { if (!list.hidden && !form.contains(event.target)) { controller?.abort(); serial++; clear(); } });
    form.addEventListener('keydown', event => {
        const links = [...list.querySelectorAll('a')];
        if (event.key === 'Escape' && !list.hidden) {
            // With suggestions open, Escape only closes them; a further Escape closes the overlay.
            event.preventDefault(); event.stopPropagation();
            controller?.abort(); serial++; clear(); input.focus();
        }
        if (['ArrowDown', 'ArrowUp'].includes(event.key) && links.length) {
            event.preventDefault(); selected = Math.max(-1, Math.min(links.length - 1, selected + (event.key === 'ArrowDown' ? 1 : -1)));
            links.forEach((link, index) => link.setAttribute('aria-selected', String(index === selected)));
            if (selected >= 0) input.setAttribute('aria-activedescendant', links[selected].id);
            else input.removeAttribute('aria-activedescendant');
        }
        if (event.key === 'Enter' && selected >= 0 && links[selected]) { event.preventDefault(); location.assign(links[selected].href); }

    });
}
// CMS sidebar: keep its scroll position between pages (signed-in area only) and
// always show the active entry, so clicking a lower entry does not jump to the top.
const cmsSidebar = document.querySelector('.cms-sidebar');
if (cmsSidebar) {
    const key = 'cms-sidebar-scroll';
    try { const saved = Number(sessionStorage.getItem(key)); if (saved > 0) cmsSidebar.scrollTop = saved; } catch {}
    const active = cmsSidebar.querySelector('nav a[aria-current]');
    if (active && cmsSidebar.scrollHeight > cmsSidebar.clientHeight) {
        const box = active.getBoundingClientRect(), frame = cmsSidebar.getBoundingClientRect();
        if (box.top < frame.top || box.bottom > frame.bottom) cmsSidebar.scrollTop += box.top - frame.top - frame.height / 3;
    }
    const remember = () => { try { sessionStorage.setItem(key, String(cmsSidebar.scrollTop)); } catch {} };
    cmsSidebar.addEventListener('scroll', remember, { passive: true });
    addEventListener('pagehide', remember);
}
for (const editor of document.querySelectorAll('[data-row-editor]')) {
    const rows = [...editor.querySelectorAll('[data-editor-row]')];
    const announcement = document.createElement('p'); announcement.setAttribute('role', 'status'); editor.append(announcement);
    const normalize = () => [...editor.querySelectorAll('[data-editor-row]')].forEach((row, index) => {
        const order = row.querySelector('[data-row-column="sort_order"] input');
        if (order && !order.disabled) order.value = String(index);
    });
    // Icon buttons keep their full text as accessible name; paths are static constants.
    const icons = { 'Nach oben': 'M12 19V5M6 11l6-6 6 6', 'Nach unten': 'M12 5v14M18 13l-6 6-6-6', 'Entfernen': 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3' };
    const button = (text, action) => {
        const b = document.createElement('button'); b.type = 'button'; b.addEventListener('click', action);
        if (icons[text]) {
            b.className = 'icon-button'; b.title = text;
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path'); path.setAttribute('d', icons[text]); svg.append(path);
            const label = document.createElement('span'); label.className = 'visually-hidden'; label.textContent = text;
            b.append(svg, label);
        } else { b.className = 'chip-button'; b.textContent = text; }
        return b;
    };
    const references = { image: 'media_id', gallery: 'gallery_id', downloads: 'document_id', contact: 'person_id', department: 'department_id', services: 'service_id', events: 'event_id', external: 'external_resource_id', location: 'location_id' };
    for (const row of rows) {
        const toolbar = document.createElement('div'); toolbar.className = 'row-toolbar';
        toolbar.append(button('Nach oben', () => { const previous = row.previousElementSibling; if (previous?.matches('[data-editor-row]') && !previous.hidden) { const focus = document.activeElement; previous.before(row); normalize(); focus.focus(); announcement.textContent = 'Eintrag nach oben verschoben.'; } }),
            button('Nach unten', () => { const next = row.nextElementSibling; if (next?.matches('[data-editor-row]') && !next.hidden) { const focus = document.activeElement; next.after(row); normalize(); focus.focus(); announcement.textContent = 'Eintrag nach unten verschoben.'; } }));
        const remove = row.querySelector('input[type="checkbox"][name$="[_remove]"]');
        if (remove && !remove.disabled) toolbar.append(button('Entfernen', () => {
            if (confirm('Diesen Eintrag beim nächsten Speichern entfernen?')) { remove.checked = true; row.classList.add('is-removed'); announcement.textContent = 'Entfernen vorgemerkt. Zum Rückgängigmachen das Kontrollkästchen abwählen.'; }
        }));
        remove?.addEventListener('change', () => row.classList.toggle('is-removed', remove.checked));
        if (!row.querySelector('input:disabled')) row.querySelector('legend').after(toolbar);
        const type = row.querySelector('[data-row-column="type"] select');
        if (type) {
            const adapt = () => {
                for (const column of row.querySelectorAll('[data-row-column]')) {
                    const key = column.dataset.rowColumn;
                    const show = ['type','sort_order'].includes(key) || (key === 'text' && ['text','callout','accordion'].includes(type.value)) || (key === 'heading' && ['heading','callout','accordion'].includes(type.value)) || (key === 'heading_level' && type.value === 'heading') || references[type.value] === key;
                    column.hidden = !show;
                }
            };
            const title = row.querySelector('[data-row-title]');
            type.addEventListener('change', () => {
                if (title) title.textContent = type.value ? type.selectedOptions[0].textContent : 'Neuer Eintrag';
                for (const column of row.querySelectorAll('[data-row-column]')) if (column.dataset.rowColumn.endsWith('_id') && column.dataset.rowColumn !== references[type.value]) column.querySelector('select').value = '';
                if (type.value !== 'heading') row.querySelector('[data-row-column="heading_level"] select').value = '';
                adapt();
            }); adapt();
            if (row.classList.contains('row-add-slot')) row.hidden = true;
        }
    }
    const slots = rows.filter(row => row.classList.contains('row-add-slot') && !row.querySelector(':disabled'));
    if (slots.length) {
        for (const row of slots) row.hidden = true;
        const add = document.createElement('div'); add.className = 'row-add-bar';
        const typeTemplate = slots[0].querySelector('[data-row-column="type"] select');
        let select;
        if (typeTemplate) {
            const label = document.createElement('label');
            select = document.createElement('select'); select.className = 'form-input'; select.id = editor.id + '-add-type';
            label.htmlFor = select.id; label.textContent = 'Baustein hinzufügen';
            for (const option of typeTemplate.options) if (option.value) select.add(new Option(option.textContent, option.value));
            add.append(label, select);
        }
        const addButton = button(typeTemplate ? 'Baustein hinzufügen' : 'Eintrag hinzufügen', () => {
            const row = slots.find(row => row.hidden);
            if (!row) return;
            row.hidden = false;
            const type = row.querySelector('[data-row-column="type"] select');
            if (type && select) { type.value = select.value; type.dispatchEvent(new Event('change')); }
            row.querySelector('.row-grid input:not([type="hidden"]), .row-grid select, .row-grid textarea')?.focus();
            announcement.textContent = 'Eintrag hinzugefügt. Änderungen anschließend speichern.';
            if (slots.every(row => !row.hidden)) {
                addButton.disabled = true;
                announcement.textContent += ' Bitte speichern, um weitere Einträge hinzuzufügen.';
            }
        });
        add.append(addButton); editor.append(add);
    }
}
// Opening an editor anchor also opens any enclosing native disclosures.
for (const anchor of document.querySelectorAll('a[href^="#"]')) anchor.addEventListener('click', () => {
    const target = document.getElementById(anchor.hash.slice(1));
    for (let parent = target; parent; parent = parent.parentElement) if (parent.tagName === 'DETAILS') parent.open = true;
});
// Keep valid contact details in the browser after server validation, without session flashing.
// The message is deliberately cleared, as in the existing privacy contract.
function enhanceContact(form) {
    if (!form) return;
    const recipient = form.querySelector('#contact_route_id'), recipientHint = form.querySelector('[data-contact-recipient]');
    if (recipient && recipientHint) {
        const describeRecipient = () => {
            recipientHint.textContent = recipient.value ? recipient.selectedOptions[0].textContent : '';
            recipientHint.hidden = recipientHint.textContent.length <= 40;
        };
        recipient.addEventListener('change', describeRecipient);
        describeRecipient();
    }
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const snapshot = new FormData(form);
        const button = form.querySelector('button[type="submit"]'); button.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: snapshot, credentials: 'same-origin', headers: { Accept: 'text/html' } });
            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = parsed.querySelector('main#inhalt');
            if (!replacement) { location.assign(response.url); return; }
            const nextForm = replacement.querySelector('[data-contact-form]');
            if (nextForm && parsed.title.startsWith('Fehler:')) {
                for (const name of ['contact_name','contact_email','contact_phone','contact_route_id','contact_street','contact_postal_code','contact_city','contact_subject']) {
                    const control = nextForm.elements.namedItem(name);
                    if (control && control.getAttribute('aria-invalid') !== 'true') control.value = String(snapshot.get(name) ?? '');
                }
                for (const name of ['contact_reply_by','contact_privacy']) {
                    for (const control of nextForm.querySelectorAll(`[name="${name}"]`)) {
                        if (control.getAttribute('aria-invalid') !== 'true') control.checked = control.value === snapshot.get(name);
                    }
                }
            }
            document.querySelector('main#inhalt').replaceWith(replacement); document.title = parsed.title;
            history.replaceState(null, '', new URL(response.url).pathname + new URL(response.url).search);
            enhanceContact(nextForm);
            (replacement.querySelector('.error-summary') ?? replacement).focus();
        } catch {
            const error = document.createElement('p'); error.className = 'form-error'; error.setAttribute('role','alert');
            error.textContent = 'Die Verbindung konnte nicht hergestellt werden. Bitte versuchen Sie es erneut.'; form.prepend(error);
        } finally { button.disabled = false; }
    });
}
enhanceContact(document.querySelector('[data-contact-form]'));
for (const form of document.querySelectorAll('form[data-confirm]')) form.addEventListener('submit', event => {
    if (!confirm(form.dataset.confirm)) event.preventDefault();
});

// Employee account menu: native disclosure with predictable dismissal and focus return.
for (const cmsAccount of document.querySelectorAll('.cms-account, .editor-more')) {
    cmsAccount.addEventListener('keydown', event => {
        if (event.key === 'Escape' && cmsAccount.open) {
            cmsAccount.open = false; cmsAccount.querySelector('summary').focus();
        }
    });
    document.addEventListener('pointerdown', event => { if (!cmsAccount.contains(event.target)) cmsAccount.open = false; });
    cmsAccount.addEventListener('focusout', event => { if (!cmsAccount.contains(event.relatedTarget)) cmsAccount.open = false; });
}
// Saving stays a normal form submission. Its label reflects the selected visibility.
const publicationStatus = document.querySelector('#editor-form [name="status"]');
if (publicationStatus) {
    const start = document.querySelector('#editor-form [name="publish_at"]');
    const end = document.querySelector('#editor-form [name="expires_at"]');
    const syncSave = () => {
        const parts = new Intl.DateTimeFormat('sv-SE', { timeZone: publicationStatus.dataset.timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date());
        const part = name => parts.find(part => part.type === name).value;
        const now = `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
        const scheduled = start?.value && start.value > now;
        const expired = end?.value && end.value <= now;
        const labels = { draft: 'Entwurf speichern', published: scheduled ? 'Speichern und planen' : 'Speichern und veröffentlichen', archived: 'Speichern und archivieren' };
        const feedback = { draft: 'Nur intern sichtbar. Der Entwurf wird nicht veröffentlicht.', published: scheduled ? 'Wird zum gewählten Startzeitpunkt öffentlich sichtbar.' : 'Wird auf der Website sichtbar. Ein Enddatum begrenzt die Veröffentlichung.', archived: 'Wird gespeichert und ist nicht mehr öffentlich sichtbar.' };
        if (publicationStatus.value === 'published' && expired) {
            labels.published = 'Speichern';
            feedback.published = publicationStatus.dataset.publicArchive === '1' ? 'Das Enddatum ist erreicht. Aus aktuellen Listen entfernt; frühere Veröffentlichungen bleiben öffentlich erreichbar.' : 'Das Enddatum ist erreicht. Der Inhalt ist nicht öffentlich sichtbar.';
        }
        if (publicationStatus.value === 'archived' && publicationStatus.dataset.publicArchive === '1') feedback.archived = 'Aus aktuellen Listen entfernt. Frühere Veröffentlichungen bleiben öffentlich erreichbar.';
        for (const button of document.querySelectorAll('[data-save-label]')) button.textContent = labels[publicationStatus.value] ?? 'Speichern';
        const note = document.querySelector('[data-save-feedback]');
        if (note) { note.setAttribute('role', 'status'); note.textContent = feedback[publicationStatus.value] ?? ''; }
    };
    publicationStatus.addEventListener('change', syncSave); start?.addEventListener('change', syncSave); end?.addEventListener('change', syncSave); syncSave();
}
// Selection state follows the checkbox; the static server class must not outlive an edit.
for (const option of document.querySelectorAll('.form-option')) {
    const checkbox = option.querySelector('input[type="checkbox"]');
    if (checkbox) {
        const sync = () => option.classList.toggle('is-selected', checkbox.checked);
        checkbox.addEventListener('change', sync); sync();
    }
}
// Navigation has one target. Hide the unused alternatives after choosing a type;
// without JavaScript all three original fields and server validation remain available.
const navigationHint = document.querySelector('[data-navigation-targets]');
if (navigationHint) {
    const names = ['public_route_id', 'external_resource_id', 'url'];
    const inputs = names.map(name => document.querySelector(`#editor-form [name="${name}"]`));
    if (inputs.every(Boolean) && inputs.every(input => !input.disabled)) {
        const field = document.createElement('div'); field.className = 'form-field';
        const label = document.createElement('label'); label.className = 'form-label'; label.htmlFor = 'navigation-target-type'; label.textContent = 'Art des Linkziels';
        const select = document.createElement('select'); select.className = 'form-input'; select.id = label.htmlFor;
        const labels = ['Seite der Gemeinde', 'Gespeicherter externer Link', 'Externe Internetadresse'];
        names.forEach((name, index) => select.add(new Option(labels[index], name)));
        const populated = inputs.filter(input => input.value);
        if (populated.length > 1) select.add(new Option('Ziele prüfen', 'all'));
        select.value = populated.length > 1 ? 'all' : (populated[0]?.name ?? names[0]);
        const sync = () => inputs.forEach(input => { input.closest('.form-field').hidden = select.value !== 'all' && input.name !== select.value; });
        select.addEventListener('change', () => {
            if (select.value !== 'all') for (const input of inputs) if (input.name !== select.value) input.value = '';
            sync();
        });
        field.append(label, select); navigationHint.after(field); sync();
    }
}

// Printable stored upload records use the browser's local print dialog.
document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

// Package actions use persisted content; prevent silently losing unsaved editorial changes.
const budgetEditor = document.querySelector('[data-budget-editor]');
if (budgetEditor) {
    const state = () => JSON.stringify([...new FormData(budgetEditor).entries()]);
    const initial = state();
    const actions = document.querySelectorAll('[form="budget-generate"], button[form="budget-upload"]');
    const note = document.querySelector('[data-budget-unsaved]');
    const syncActions = () => {
        const changed = budgetEditor.hasAttribute('data-budget-save-required') || state() !== initial;
        for (const action of actions) action.disabled = changed;
        if (note) note.hidden = !changed;
    };
    syncActions();
    for (const event of ['input', 'change', 'click']) budgetEditor.addEventListener(event, () => {
        // Row controls can move their DOM nodes during the current event.
        queueMicrotask(syncActions);
    });
}
// Keep the date list immediately reachable on small screens; the complete
// calendar remains a native disclosure and stays open without JavaScript.
const calendarDisclosure = document.querySelector('[data-calendar-disclosure]');
if (calendarDisclosure && window.matchMedia('(max-width: 48rem)').matches) {
    calendarDisclosure.open = location.hash === '#kalender';
}
