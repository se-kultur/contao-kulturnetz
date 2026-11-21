<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Backend;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use SeKultur\ContaoKulturnetzBundle\Service\Export\SekEventCsvExporter;
use SeKultur\ContaoKulturnetzBundle\Service\Export\SekEventPdfExporter;
use SeKultur\ContaoKulturnetzBundle\Service\Export\TableCsvExporter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Exporte der Redaktion als Backend-Routen (bisher die Skripte csvArtists.php,
 * csvHosts.php, csvLocations.php, csvMembers.php, csvEvents.php und
 * pdfEvents.php unter files/se-kultur.de/code, ohne Anmeldung erreichbar).
 *
 * Die Routen liegen unter dem Backend-Pfad (Firewall contao_backend, Anmeldung
 * als Backend-Benutzer erforderlich) und prüfen zusätzlich das Recht auf das
 * Backend-Modul, zu dem der Export gehört. Aufgerufen werden sie über die
 * Schaltflächen "CSV-Download" bzw. "PDF-Download" in den Listen.
 */
#[AsController]
#[Route('%contao.backend.route_prefix%/kulturnetz/export', defaults: ['_scope' => 'backend'], methods: ['GET'])]
final class ExportController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly TableCsvExporter $tableExporter,
        private readonly SekEventCsvExporter $sekEventCsvExporter,
        private readonly SekEventPdfExporter $sekEventPdfExporter,
    ) {
    }

    #[Route('/kuenstler.csv', name: 'kulturnetz_export_artists_csv')]
    public function artistsCsv(): Response
    {
        $this->denyUnlessModuleAccess('Kuenstler');

        return $this->tableExporter->createResponse('tl_artists');
    }

    #[Route('/veranstalter.csv', name: 'kulturnetz_export_hosts_csv')]
    public function hostsCsv(): Response
    {
        $this->denyUnlessModuleAccess('Veranstalter');

        return $this->tableExporter->createResponse('tl_hosts');
    }

    #[Route('/locations.csv', name: 'kulturnetz_export_locations_csv')]
    public function locationsCsv(): Response
    {
        $this->denyUnlessModuleAccess('Locations');

        return $this->tableExporter->createResponse('tl_locations');
    }

    #[Route('/mitglieder.csv', name: 'kulturnetz_export_members_csv')]
    public function membersCsv(): Response
    {
        $this->denyUnlessModuleAccess('member');

        return $this->tableExporter->createResponse('tl_member');
    }

    #[Route('/sekultur-events.csv', name: 'kulturnetz_export_sekevents_csv')]
    public function sekEventsCsv(): Response
    {
        $this->denyUnlessModuleAccess('Events');

        return $this->sekEventCsvExporter->createResponse();
    }

    #[Route('/sekultur-events.pdf', name: 'kulturnetz_export_sekevents_pdf')]
    public function sekEventsPdf(): Response
    {
        $this->denyUnlessModuleAccess('Events');

        return $this->sekEventPdfExporter->createResponse();
    }

    /**
     * Nur angemeldete Backend-Benutzer mit Zugriff auf das Backend-Modul.
     */
    private function denyUnlessModuleAccess(string $module): void
    {
        $this->framework->initialize();

        if (!$this->security->isGranted('ROLE_USER') || !$this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, $module)) {
            throw new AccessDeniedException(\sprintf('Kein Zugriff auf den Export des Moduls "%s".', $module));
        }
    }
}
