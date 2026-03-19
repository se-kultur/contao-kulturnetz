<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use SeKultur\ContaoKulturnetzBundle\Models\HostsModel;

class ModuleHostsListAll extends \Module
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
		if (TL_MODE == 'BE')
		{
			/** @var \BackendTemplate|object $objTemplate */
			$objTemplate = new \BackendTemplate('be_wildcard');

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
		
		$memberId = 0;
		if (FE_USER_LOGGED_IN === true) {
            $objUser = \FrontendUser::getInstance();
            $memberId = $objUser->id;
        }
		$this->Template->member_id = $memberId;
		
		if($objPage->id == 7) { // Übersichtsseite SE-KulturNetz
			$hosts = HostsModel::findAllHosts(3);
			$this->Template->hosts = $hosts;	
		} else {
			$hosts = HostsModel::findAllHosts(100, @$_GET);
			$this->Template->hosts = $hosts;	
			$filter = true;
		}
		
		$this->Template->filter = $filter;
	}
}