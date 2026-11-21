<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Page\AutoItemResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formular zum Anlegen und Bearbeiten einer Veranstaltung (Module "ModuleEventAdd",
 * "ModuleEventEdit", "ModuleSekEventAdd" und "ModuleSekEventEdit", bisher
 * Module\ModuleEventAdd usw.). Die konkreten Controller legen die Art der
 * Veranstaltung und den Modus (Anlegen oder Bearbeiten) fest.
 *
 * Beim Bearbeiten lädt der Controller die Veranstaltung per Alias aus der URL. Fehlt
 * der Alias, ist er unbekannt oder gehört die Veranstaltung einem anderen Mitglied,
 * antwortet die Seite mit 404 (Seite "Nicht gefunden" der Website), noch bevor ein
 * Formular oder eine URL erzeugt wird; fremde Veranstaltungen verraten so nicht, ob
 * es sie gibt. Bisher lieferte Contao 4.13 in diesen Fällen ein leeres Formular, das
 * sich nicht speichern ließ. Gespeichert wird zusätzlich immer serverseitig mit
 * Besitzprüfung (Route kulturnetz_profil_aendern). Das Anlegen (ohne Alias) bleibt
 * unverändert.
 */
abstract class AbstractEventFormController extends AbstractEventController
{
    public const MODE_ADD = 'add';

    public const MODE_EDIT = 'edit';

    public function __construct(
        private readonly EventRepository $repository,
        private readonly AutoItemResolver $autoItemResolver,
        private readonly FrontendMemberProvider $memberProvider,
        private readonly EventViewFactory $viewFactory,
        private readonly ClockInterface $clock,
        private readonly KulturnetzConfig $config,
    ) {
    }

    /**
     * MODE_ADD oder MODE_EDIT.
     */
    abstract protected function getMode(): string;

    final protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $type = $this->getEventType();
        $mode = $this->getMode();
        $memberId = $this->memberProvider->getMemberId();
        $event = null;

        if (self::MODE_EDIT === $mode) {
            $alias = $this->autoItemResolver->resolve($request);
            $event = null !== $alias ? $this->repository->findByAliasOrId($type, $alias) : null;

            if (null === $event || $memberId < 1 || $memberId !== (int) $event['member_id']) {
                throw new PageNotFoundException(\sprintf('%s zum Bearbeiten nicht gefunden: %s', EventType::Events === $type ? 'Veranstaltung' : 'SE-Kultur-Event', $alias ?? '(ohne Alias)'));
            }
        }

        $template->set('event_type', $type->value);
        $template->set('form_mode', $mode);
        $template->set('member_id', $memberId);
        $template->set('values', $this->viewFactory->formValues($type, $mode, $event));
        $template->set('formats', EventViewFactory::SEKEVENT_FORMATS);

        $now = $this->clock->now();
        $year = (int) $now->format('Y');
        $template->set('current_year', $year);

        // Termine der SE-KulturTage haben kein Jahr; das Formular begrenzt sie auf den Festivalmonat
        // (Konfiguration kulturnetz.sekevents.festival_month). sekeventform bzw. eventform.js lesen
        // die Grenzen aus den data-Attributen des Templates.
        $dateMin = null;
        $dateMax = null;

        if ($type->datesStoredWithoutYear()) {
            $firstDay = new \DateTimeImmutable(\sprintf('%d-%02d-01', $year, $this->config->getFestivalMonth()));
            $dateMin = $firstDay->format('Y-m-d');
            $dateMax = $firstDay->modify('last day of this month')->format('Y-m-d');
        }

        $template->set('date_min', $dateMin);
        $template->set('date_max', $dateMax);

        return $template->getResponse();
    }
}
