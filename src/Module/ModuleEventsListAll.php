<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use SeKultur\ContaoKulturnetzBundle\Models\EventsModel;
 
class ModuleEventsListAll extends \Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_events_list_all';

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

			$objTemplate->wildcard = '### Alle Veranstaltungen ###';
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
		// reduzieren, Arrays verwerfen - analog zu ModuleSekEventsListAll::compile().
		// Die Normalisierung muss vor der Abfrage stehen: Ein Array-Parameter
		// (z. B. ?text[]=x) löst in EventsModel::findAllEvents() unter PHP 8
		// sonst einen TypeError aus (strlen() mit Array). Dieselben Werte
		// versorgen anschließend die Template-Anzeige (Formularwerte,
		// Zurücksetzen-Button, Orts-Badges, Vergangenheits-Ausblendung).
		$searchArr = [];
		foreach (['datum', 'kategorie', 'text'] as $key) {
			if (isset($_GET[$key]) && is_string($_GET[$key])) {
				$searchArr[$key] = $_GET[$key];
			}
		}
		$this->Template->search = $searchArr;

		$events = EventsModel::findAllEvents(0, $searchArr);
		$this->Template->events = $events;

		$filter = true;
		$this->Template->filter = $filter;

		$stats = EventsModel::getStats();

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
			: EventsModel::findAllEvents(0, $facettenFilter);

		$orte = [];
		foreach ($facettenEvents as $facettenEvent) {
			$ort = trim((string) $facettenEvent->location_ort);
			if ($ort === '') {
				continue;
			}
			$orte[$ort] = ($orte[$ort] ?? 0) + 1;
		}
		ksort($orte);

		$stats['location_orte'] = $orte;
		$this->Template->stats = $stats;
	}
}