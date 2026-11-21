<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use Contao\BackendTemplate;
use Contao\Module;
use SeKultur\ContaoKulturnetzBundle\Helper\RequestContext;
use SeKultur\ContaoKulturnetzBundle\Models\HostsModel;

class ModuleHostsListAll extends Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_hosts_list';

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

			$objTemplate->wildcard = '### Alle Veranstalter:innenprofile ###';
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
		
		$memberId = RequestContext::getMemberId();
		$this->Template->member_id = $memberId;

		// $_GET wird ungeprüft vom Frontend befüllt. Nur die vom Model
		// ausgewerteten Filterschlüssel übernehmen und jeden Wert auf einen
		// String reduzieren, Arrays verwerfen. Ohne diese Normalisierung führt
		// z. B. ?text[]=x unter PHP 8 zu einem TypeError (strlen() mit Array).
		// Für normale Parameter bleiben Treffer und Reihenfolge unverändert.
		// Dieselben Werte versorgen das Suchformular im Template.
		$searchArr = [];
		foreach (['text'] as $key) {
			if (isset($_GET[$key]) && is_string($_GET[$key])) {
				$searchArr[$key] = $_GET[$key];
			}
		}
		$this->Template->search = $searchArr;

		if($objPage->id == 7) { // Übersichtsseite SE-KulturNetz
			$hosts = HostsModel::findAllHosts(3);
			$this->Template->hosts = $hosts;	
		} else {
			$hosts = HostsModel::findAllHosts(100, $searchArr);
			$this->Template->hosts = $hosts;	
			$filter = true;
		}
		
		$this->Template->filter = $filter;
	}
}