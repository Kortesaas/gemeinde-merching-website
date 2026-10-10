<dialog class="display-panel" id="display-panel" aria-labelledby="display-panel-title" aria-describedby="display-panel-intro" data-display-panel>
    <div class="display-panel__header">
        <h2 id="display-panel-title">Darstellung<span class="visually-hidden"> &amp; Barrierefreiheit</span></h2>
        <form method="dialog"><button class="button button--ghost display-panel__close" aria-label="Darstellung schließen" autofocus><x-icon name="close" /></button></form>
    </div>
    <p id="display-panel-intro" class="display-panel__intro">Schrift, Farben und Bewegung anpassen.</p>
    <p class="display-hint" data-storage-hint>Die Auswahl bleibt in diesem Browser und gilt auf allen Seiten und in anderen Tabs. „Alles zurücksetzen“ löscht sie.</p>
    <form data-display-form>
        <fieldset class="display-group">
            <legend>Schriftgröße</legend>
            <div class="display-font">
                <button class="button button--ghost" type="button" data-font-step="-1" aria-label="Schrift verkleinern"><x-icon name="minus" /></button>
                <output data-font-output aria-live="polite" aria-label="Schriftgröße">100 %</output>
                <button class="button button--ghost" type="button" data-font-step="1" aria-label="Schrift vergrößern"><x-icon name="plus" /></button>
                <button class="button button--ghost" type="button" data-font-reset>Standard</button>
            </div>
            <p class="display-hint">100–150 %. Der Browser-Zoom bleibt zusätzlich verfügbar.</p>
        </fieldset>
        <fieldset class="display-group">
            <legend>Farben &amp; Bewegung</legend>
            <label class="display-toggle"><span>Kontrast erhöhen</span><input type="checkbox" name="contrast"></label>
            <label class="display-toggle"><span>Dunkler Modus</span><input type="checkbox" name="dark"></label>
            <label class="display-toggle"><span>Bewegungen reduzieren</span><input type="checkbox" name="motion" aria-describedby="display-motion-hint"></label>
            <p class="display-hint" id="display-motion-hint" data-motion-hint>Die Bewegungseinstellung Ihres Geräts wird immer berücksichtigt.</p>
            <label class="display-toggle"><span>Blaufilter</span><input type="checkbox" name="warm" aria-describedby="display-warm-hint"></label>
            <p class="display-hint" id="display-warm-hint">Wärmere Flächen und Akzentfarben.</p>
            <label class="display-select" for="display-vision">Farbwahrnehmung / Farbschwäche</label>
            <select class="form-input" id="display-vision" name="vision" aria-describedby="display-vision-hint">
                <option value="default">Standard</option>
                <option value="red-green">Rot-Grün: Blau &amp; Gelb</option>
                <option value="blue-yellow">Blau-Gelb: Türkis &amp; Magenta</option>
            </select>
            <p class="display-hint" id="display-vision-hint">Optionale Farbhilfen für Hinweise und Statusangaben mit deutlichen Umrissen. Texte und Symbole bleiben erhalten. Keine Korrektur von Bildern.</p>
        </fieldset>
        <fieldset class="display-group">
            <legend>Inhalte</legend>
            <label class="display-toggle"><span>Bilder ausblenden</span><input type="checkbox" name="images" aria-describedby="display-images-hint"></label>
            <p class="display-hint" id="display-images-hint">Bildflächen behalten ihre Größe und zeigen den Beschreibungstext auf neutralem Hintergrund. Wappen, Symbole und Bildunterschriften bleiben erhalten.</p>
        </fieldset>
        <button class="button button--secondary button--block" type="reset">Alles zurücksetzen</button>
        <p class="display-status" role="status" data-display-status></p>
    </form>
    <section class="display-group display-reader" aria-labelledby="display-reader-title">
        <h3 id="display-reader-title">Webseite vorlesen</h3>
        <p class="display-hint" id="display-reader-hint" data-read-hint>Verfügbarkeit wird beim Öffnen geprüft. Nur lokale deutsche Stimmen werden verwendet.</p>
        <div class="display-actions">
            <button class="button button--secondary" type="button" data-read-start aria-describedby="display-reader-hint" disabled>Vorlesen starten</button>
            <button class="button button--ghost" type="button" data-read-stop disabled>Vorlesen stoppen</button>
        </div>
        <p class="display-status" role="status" data-read-status></p>
    </section>
    <p class="display-hint display-panel__note">Diese freiwilligen Hilfen ergänzen die barrierearme Website. Sie ersetzen weder die Bedienungshilfen Ihres Geräts noch einen Screenreader.</p>
</dialog>
