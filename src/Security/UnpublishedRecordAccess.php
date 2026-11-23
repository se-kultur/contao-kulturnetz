<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Security;

use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;

/**
 * Entscheidet, ob die Detailansicht einen nicht freigeschalteten Datensatz
 * (disable = 1) ausliefern darf. Gilt für alle fünf Datensatzarten (Künstler*innen,
 * Veranstalter*innen, Locations, Veranstaltungen, SE-KulturTage-Events).
 *
 * Bis Runde 7 lieferten die Detailmodule gesperrte Datensätze an jeden aus, der
 * den Alias oder die ID kannte (wie in Contao 4.13, Entscheidung E39). Der Alias
 * entsteht aus dem Namen und ist damit erratbar; ungeprüfte Mitgliederinhalte
 * waren so öffentlich. Jetzt sehen einen gesperrten Datensatz nur noch:
 *
 *  - das Mitglied, dem er gehört (member_id; auch in der Backend-Vorschau "als
 *    Mitglied", dort authentifiziert Contao das gewählte Mitglied in der
 *    Frontend-Firewall, siehe FrontendMemberProvider),
 *  - angemeldete Backend-Benutzer (TokenChecker::hasBackendUser(), Backend-Sitzung
 *    im selben Browser). Damit funktionieren die Links der Redaktion weiter: Name
 *    in den Backend-Listen ("Im Frontend ansehen"), Link der Redaktionsmail
 *    "Neues ... angelegt", Detail-URLs im CSV- und PDF-Export, Frontend-Vorschau
 *    mit und ohne "Unveröffentlichte Elemente anzeigen". Der Kreis entspricht dem
 *    des Contao-Kerns für unveröffentlichte Inhalte: Jeder Backend-Benutzer kann die
 *    Vorschau öffnen und dort Unveröffentlichtes einblenden.
 *  - Gäste mit einem Vorschaulink des Kerns, der unveröffentlichte Inhalte zeigt
 *    (TokenChecker::isPreviewMode(), tl_preview_link mit showUnpublished).
 *
 * Alle anderen erhalten dieselbe Antwort wie bei einem unbekannten Alias (404),
 * damit sich die Existenz eines gesperrten Datensatzes nicht ablesen lässt. Die
 * Darstellung für Berechtigte ändert sich nicht (Hinweis "Nicht öffentlich" nur für
 * das besitzende Mitglied, Meta-Tag robots "noindex,nofollow").
 */
final class UnpublishedRecordAccess
{
    public function __construct(
        private readonly FrontendMemberProvider $memberProvider,
        private readonly TokenChecker $tokenChecker,
    ) {
    }

    /**
     * True, wenn der Datensatz nicht freigeschaltet ist (Spalte disable "1").
     *
     * @param array<string, mixed> $record Tabellenzeile mit disable
     */
    public function isUnpublished(array $record): bool
    {
        return (int) ($record['disable'] ?? 0) > 0;
    }

    /**
     * True, wenn die Detailansicht den Datensatz in der aktuellen Anfrage ausliefern
     * darf: freigeschaltete immer, gesperrte nur an die oben genannten Rollen.
     *
     * @param array<string, mixed> $record Tabellenzeile mit disable und member_id
     */
    public function canView(array $record): bool
    {
        if (!$this->isUnpublished($record)) {
            return true;
        }

        $memberId = $this->memberProvider->getMemberId();

        if ($memberId > 0 && $memberId === (int) ($record['member_id'] ?? 0)) {
            return true;
        }

        return $this->tokenChecker->hasBackendUser() || $this->tokenChecker->isPreviewMode();
    }
}
