<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use SeKultur\ContaoKulturnetzBundle\Models\SekEventsModel;

class ModuleSekEvent extends \Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_sekevent';

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

			$objTemplate->wildcard = '### Detailansicht SE-KulturTage Event ###';
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
		$alias = \Input::get('auto_item');
		//$event = SekEventsModel::findByIdOrAlias($alias);
		$event = SekEventsModel::findByAlias($alias);
		
		if($event !== NULL) {
			$memberId = 0;
			if (FE_USER_LOGGED_IN === true) {
				$objUser = \FrontendUser::getInstance();
				$memberId = $objUser->id;
			}
			$this->Template->member_id = $memberId;
			
			$this->Template->event = $event;		
		} else {
			// TODO: REDIRECT OR ERROR PAGE
		}
		
	}
}