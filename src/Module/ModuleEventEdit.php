<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use Contao\BackendTemplate;
use Contao\Input;
use Contao\Module;
use SeKultur\ContaoKulturnetzBundle\Helper\RequestContext;
use SeKultur\ContaoKulturnetzBundle\Models\EventsModel;

class ModuleEventEdit extends Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_event_edit';

	/**
	 * Do not display the module if there are no menu items
	 *
	 * @return string
	 */
	public function generate()
	{
		if (RequestContext::isBackendRequest())
		{
			/** @var BackendTemplate|object $objTemplate */
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### Veranstaltung bearbeiten ###';
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
		$memberId = RequestContext::getMemberId();
		if ($memberId > 0) {
			
			$alias = (string) Input::get('auto_item');
			$event = EventsModel::findByAlias($alias);

			if ($event !== null && $event->member_id == $memberId) {
				$this->Template->event = $event;
			} else {
				// REDIRECT
				// Unbekannte oder fremde Veranstaltung: Die Maske bleibt wie bisher
				// leer. Ein leeres Model statt null, damit das Template unter PHP 8
				// keine Warnungen für jeden Feldzugriff erzeugt.
				$this->Template->event = $this->createEmptyEvent();
			}
        } else {
			// REDIRECT LOGIN
		}
		
	}

	/**
	 * Leeres Model mit den Zusatzfeldern, die das Bearbeiten-Template ausliest
	 *
	 * @return EventsModel
	 */
	protected function createEmptyEvent()
	{
		$event = new EventsModel();
		$event->linked_artists_data = [];
		$event->linked_locations_data = [];
		$event->linked_hosts_data = [];
		$event->dates = '[]';

		return $event;
	}
}