import.meta.glob('../images/*', { eager: true, query: '?url', import: 'default' });
// Small, same-origin enhancements. Native links, GET search and form fields remain the fallback.
// Enhanced layouts (e.g. the overlay menu) only apply once this script runs.
document.documentElement.classList.add('js');
// Matches the CSS breakpoints: wide layouts start at 64rem (1024px).
const narrow = matchMedia('(max-width: 63.99rem)');
for (const menu of document.querySelectorAll('[data-navigation], [data-cms-navigation]')) {
    const sync = () => { menu.open = !narrow.matches; };
    sync(); narrow.addEventListener('change', sync);
    menu.addEventListener('keydown', event => {
        if (event.key === 'Escape' && narrow.matches) {
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
// Wide screens: the expanded menu panel closes when focus or a click moves elsewhere.
const closeBranches = target => {
    if (narrow.matches) return;
    for (const branch of document.querySelectorAll('.site-navigation [data-nav-branch][open]')) if (!branch.contains(target)) branch.open = false;
};
document.addEventListener('click', event => closeBranches(event.target));
document.addEventListener('focusin', event => closeBranches(event.target));
const searchDialog = document.querySelector('[data-search-dialog]');
if (searchDialog?.showModal) {
    let opener;
    for (const trigger of document.querySelectorAll('[data-search-trigger]')) trigger.addEventListener('click', event => {
        event.preventDefault(); opener = trigger; searchDialog.showModal(); searchDialog.querySelector('[data-search-input]').focus();
    });
    searchDialog.querySelector('[data-dialog-close]').addEventListener('click', () => searchDialog.close());
    searchDialog.addEventListener('close', () => opener?.focus());
    const all = searchDialog.querySelector('[data-search-all]'), overlayInput = searchDialog.querySelector('[data-search-input]');
    overlayInput.addEventListener('input', () => { all.href = '/suche' + (overlayInput.value.trim() ? '?q=' + encodeURIComponent(overlayInput.value.trim()) : ''); });
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
    form.addEventListener('keydown', event => {
        const links = [...list.querySelectorAll('a')];
        if (event.key === 'Escape') { controller?.abort(); serial++; clear(); input.focus(); event.stopPropagation(); }
        if (['ArrowDown', 'ArrowUp'].includes(event.key) && links.length) {
            event.preventDefault(); selected = Math.max(-1, Math.min(links.length - 1, selected + (event.key === 'ArrowDown' ? 1 : -1)));
            links.forEach((link, index) => link.setAttribute('aria-selected', String(index === selected)));
            if (selected >= 0) input.setAttribute('aria-activedescendant', links[selected].id);
            else input.removeAttribute('aria-activedescendant');
        }
        if (event.key === 'Enter' && selected >= 0 && links[selected]) { event.preventDefault(); location.assign(links[selected].href); }

    });
}
// Keep the primary content and media metadata visible; optional groups are native disclosures.
for (const section of document.querySelectorAll('details.editor-section')) {
    if (['Suchmaschinen', 'Zuständigkeit und Beziehungen', 'Inhaltsbausteine'].includes(section.querySelector('summary')?.textContent.trim()) && !section.querySelector('[aria-invalid=true], .form-error')) section.open = false;
}
for (const editor of document.querySelectorAll('[data-row-editor]')) {
    const rows = [...editor.querySelectorAll('[data-editor-row]')];
    const announcement = document.createElement('p'); announcement.setAttribute('role', 'status'); editor.append(announcement);
    const normalize = () => [...editor.querySelectorAll('[data-editor-row]')].forEach((row, index) => {
        row.querySelector('[data-row-column="sort_order"] input').value = String(index);
    });
    const button = (text, action) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'button button--secondary'; b.textContent = text; b.addEventListener('click', action); return b; };
    const references = { image: 'media_id', gallery: 'gallery_id', downloads: 'document_id', contact: 'person_id', department: 'department_id', services: 'service_id', events: 'event_id', external: 'external_resource_id', location: 'location_id' };
    for (const row of rows) {
        const toolbar = document.createElement('div'); toolbar.className = 'row-toolbar';
        toolbar.append(button('Nach oben', () => { const previous = row.previousElementSibling; if (previous?.matches('[data-editor-row]')) { const focus = document.activeElement; previous.before(row); normalize(); focus.focus(); announcement.textContent = 'Eintrag nach oben verschoben.'; } }),
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
            type.addEventListener('change', () => {
                for (const column of row.querySelectorAll('[data-row-column]')) if (column.dataset.rowColumn.endsWith('_id') && column.dataset.rowColumn !== references[type.value]) column.querySelector('select').value = '';
                if (type.value !== 'heading') row.querySelector('[data-row-column="heading_level"] select').value = '';
                adapt();
            }); adapt();
            if (row.classList.contains('row-add-slot')) row.hidden = true;
        }
    }
    if (editor.dataset.rowEditor === 'blocks' && rows.some(r => r.classList.contains('row-add-slot'))) {
        const add = document.createElement('div'); add.className = 'row-toolbar';
        for (const option of rows[0].querySelector('[data-row-column="type"] select').options) if (option.value) add.append(button('+ ' + option.textContent, () => {
            const row = rows.find(r => r.hidden); if (!row) { announcement.textContent = 'Bitte speichern, um weitere freie Einträge hinzuzufügen.'; return; }
            row.hidden = false; const type = row.querySelector('[data-row-column="type"] select'); type.value = option.value; type.dispatchEvent(new Event('change')); type.focus(); announcement.textContent = option.textContent + ' hinzugefügt. Änderungen anschließend speichern.';
        }));
        editor.append(add);
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
                for (const name of ['contact_name','contact_email','contact_phone','contact_route_id']) {
                    const control = nextForm.elements.namedItem(name);
                    if (control && control.getAttribute('aria-invalid') !== 'true') control.value = String(snapshot.get(name) ?? '');
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
