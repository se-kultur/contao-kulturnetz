<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use SeKultur\ContaoKulturnetzBundle\Models\HostsModel;

class ModuleHostEdit extends \Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_host_edit';

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

			$objTemplate->wildcard = '### Veranstalter:innenprofil bearbeiten ###';
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
		$memberId = 0;
		if (FE_USER_LOGGED_IN === true) {
            $objUser = \FrontendUser::getInstance();
            $memberId = $objUser->id;
			
			$alias = \Input::get('auto_item');
			$host = HostsModel::findByIdOrAlias($alias);
			
			if($host->member_id == $memberId) {
				$this->Template->host = $host;	
			} else {
				// REDIRECT 
			}
        } else {
			// REDIRECT LOGIN
		}
		
	}
}