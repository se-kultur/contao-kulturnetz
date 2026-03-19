<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use SeKultur\ContaoKulturnetzBundle\Models\LocationsModel;

class ModuleLocationsListAll extends \Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_locations_list';

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

			$objTemplate->wildcard = '### Alle Locationprofile ###';
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
		
		if($objPage->id == 7) { // Übersichtsseite SE-KulturNetz
			$locations = LocationsModel::findAllLocations(3);
			$this->Template->locations = $locations;	
		} else {
			$locations = LocationsModel::findAllLocations(100, @$_GET);
			$this->Template->locations = $locations;	
			$filter = true;
		}
		
		$this->Template->filter = $filter;		
	}
}