/*
 * Feld "Informationen zum Mitglied" in den Backend-Masken des KulturNetz-Bundles.
 *
 * 1. Zeichenzähler: Unter dem Feld, das ein Element mit
 *    data-kulturnetz-char-counter="<ID des Feldes>" nennt, steht "n / <Grenze>"
 *    (Grenze aus data-kulturnetz-char-limit), aktualisiert bei jeder Eingabe.
 * 2. Details des Mitglieds: Links mit data-kulturnetz-modal-url öffnen die
 *    Detailansicht im Contao-Dialog, wie die Operation "Details" der
 *    Mitgliederliste.
 *
 * Das Backend lädt Seiten per Turbo; das Skript wird bei jeder Darstellung
 * erneut ausgeführt und richtet nur fehlende Zähler ein. Die Ereignisse hängen
 * einmalig am Dokument.
 */
(() => {
    const render = () => {
        document.querySelectorAll('[data-kulturnetz-char-counter]').forEach((config) => {
            const field = document.getElementById(config.dataset.kulturnetzCharCounter);

            if (!field) {
                return;
            }

            let output = field.nextElementSibling;

            if (!output || !output.classList.contains('kulturnetz-char-counter')) {
                output = document.createElement('div');
                output.className = 'kulturnetz-char-counter';
                field.insertAdjacentElement('afterend', output);
            }

            output.dataset.kulturnetzLimit = config.dataset.kulturnetzCharLimit;
            output.textContent = `${field.value.length} / ${output.dataset.kulturnetzLimit}`;
        });
    };

    render();

    if (window.kulturnetzMemberInfosBound) {
        return;
    }

    window.kulturnetzMemberInfosBound = true;

    document.addEventListener('input', (event) => {
        const output = event.target.nextElementSibling;

        if (output && output.classList.contains('kulturnetz-char-counter')) {
            output.textContent = `${event.target.value.length} / ${output.dataset.kulturnetzLimit}`;
        }
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-kulturnetz-modal-url]');

        if (!link || !window.Backend || typeof window.Backend.openModalIframe !== 'function') {
            return;
        }

        event.preventDefault();
        window.Backend.openModalIframe({ title: link.dataset.kulturnetzModalTitle, url: link.dataset.kulturnetzModalUrl });
    });

    document.addEventListener('turbo:render', render);
})();
