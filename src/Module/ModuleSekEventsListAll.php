<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use SeKultur\ContaoKulturnetzBundle\Models\SekEventsModel;

class ModuleSekEventsListAll extends \Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_sekevents_list_all';

	/**
	 * Do not display the module if there are no menu items
	 *
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			/** @var \BackendTemplate|object $objTemplate */
			$objTemplate = new \BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### Alle SE-KulturTage Events ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'contao?do=themes&table=tl_module&act=edit&id=' . $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}

	/**
	 * Generate module
	 */
	protected function compile()
	{
		global $objPage;
		
		$filter = false;
		
		$memberId = 0;
		if (FE_USER_LOGGED_IN === true) {
            $objUser = \FrontendUser::getInstance();
            $memberId = $objUser->id;
        }
		$this->Template->member_id = $memberId;
		
		// $_GET wird ungeprüft vom Frontend befüllt. Nur die bekannten
		// Filterschlüssel übernehmen und jeden Wert auf einen skalaren String
		// reduzieren, Arrays verwerfen. Ohne diese Normalisierung würde z. B.
		// ?text[]=x unter PHP 8 zu einem TypeError in SekEventsModel führen
		// (strlen() erwartet einen String), ?kategorie[]=x bzw. ?format[]=x zu
		// "Array to string conversion"-Warnungen und einer sinnlosen
		// LIKE-Bedingung.
		$searchArr = [];
		foreach (['datum', 'kategorie', 'format', 'text'] as $key) {
			if (isset($_GET[$key]) && is_string($_GET[$key])) {
				$searchArr[$key] = $_GET[$key];
			}
		}

		$events = SekEventsModel::findAllSekEvents(200, $searchArr);

		// Innerhalb eines Tages täglich wechselnde Reihenfolge, damit nicht immer
		// dieselben Veranstaltungen oben stehen. Die Tage bleiben chronologisch.
		// Gilt bewusst nur für die angezeigte Liste - die Facettenmenge unten
		// zählt lediglich Orte, deren Reihenfolge dafür ohne Bedeutung ist.
		$events = SekEventsModel::mischeInnerhalbDerTage($events);

		$this->Template->events = $events;

		// Bereits normalisiertes $searchArr (garantiert skalare Strings, nur
		// bekannte Filterschlüssel) an das Template durchreichen. Das Template
		// liest damit ausschließlich diese Variable statt direkt aus $_GET -
		// sonst würde z. B. ?datum[]=x unter PHP 8 eine "Array to string
		// conversion"-Warnung erzeugen und die Vergangenheits-Ausblendung
		// (empty($_GET['datum'])) ließe sich per Array-Wert aushebeln, da ein
		// nicht-leeres Array nie als "empty" gilt.
		$this->Template->search = $searchArr;

		$filter = true;
		$this->Template->filter = $filter;

		// Statistik (Orts-Badges) und Festival-Zeitraum (Datepicker-Grenzen)
		// werden in einem gemeinsamen Tabellendurchlauf ermittelt, damit
		// tl_sekevents pro Seitenaufruf nur zweimal statt dreimal vollständig
		// gelesen wird (Liste oben + dieser gemeinsame Durchlauf).
		$statsAndDateRange = SekEventsModel::getStatsAndDateRange();
		$this->Template->dateRange = $statsAndDateRange['dateRange'];

		// Die Orts-Badges dürfen nur Orte anbieten, die unter den aktuell gesetzten
		// Filtern auch wirklich Treffer liefern - sonst führt ein angebotener Filter
		// sichtbar ins Leere. Der Ortsfilter selbst (text) wird dabei ausgeklammert,
		// weil ein Badge ihn überschreibt; sonst bliebe nach dem ersten Klick nur
		// noch der bereits gewählte Ort übrig. Ist kein Ortsfilter aktiv, entspricht
		// die Facettenmenge der ohnehin geladenen Liste - dann wird sie
		// wiederverwendet, statt die Tabelle ein weiteres Mal zu lesen.
		$facettenFilter = $searchArr;
		unset($facettenFilter['text']);

		$facettenEvents = empty($searchArr['text'])
			? $events
			: SekEventsModel::findAllSekEvents(200, $facettenFilter);

		$orte = [];
		foreach ($facettenEvents as $facettenEvent) {
			$ort = trim((string) $facettenEvent->location_ort);
			if ($ort === '') {
				continue;
			}
			$orte[$ort] = ($orte[$ort] ?? 0) + 1;
		}
		ksort($orte);

		$stats = $statsAndDateRange['stats'];
		$stats['location_orte'] = $orte;
		$this->Template->stats = $stats;
	}
}