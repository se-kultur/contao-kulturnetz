<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventRepository;
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
 * Beim Bearbeiten lädt der Controller die Veranstaltung per Alias aus der URL. Ist
 * sie unbekannt oder gehört sie einem anderen Mitglied, zeigt das Formular wie
 * bisher leere Felder (Entscheidung E39); gespeichert wird immer serverseitig mit
 * Besitzprüfung (Route kulturnetz_profil_aendern).
 */
abstract class AbstractEventFormController extends AbstractEventController
{
    public const MODE_ADD = 'add';

    public const MODE_EDIT = 'edit';

    /**
     * Monat der SE-KulturTage: Der Datepicker des Formulars erlaubt nur Termine
     * im September des laufenden Jahres (bisher fest in sekeventform.js). Eine
     * Konfiguration dafür steht noch aus (Hinweis in OFFENE-FRAGEN.md).
     */
    private const FESTIVAL_MONTH = 9;

    public function __construct(
        private readonly EventRepository $repository,
        private readonly AutoItemResolver $autoItemResolver,
        private readonly FrontendMemberProvider $memberProvider,
        private readonly EventViewFactory $viewFactory,
        private readonly ClockInterface $clock,
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
            $found = null !== $alias ? $this->repository->findByAliasOrId($type, $alias) : null;

            if (null !== $found && $memberId > 0 && $memberId === (int) $found['member_id']) {
                $event = $found;
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

        // Termine der SE-KulturTage haben kein Jahr; das Formular begrenzt sie auf den Festivalmonat.
        $template->set('date_min', $type->datesStoredWithoutYear() ? \sprintf('%d-%02d-01', $year, self::FESTIVAL_MONTH) : null);
        $template->set('date_max', $type->datesStoredWithoutYear() ? (new \DateTimeImmutable(\sprintf('%d-%02d-01', $year, self::FESTIVAL_MONTH)))->modify('last day of this month')->format('Y-m-d') : null);

        return $template->getResponse();
    }
}
