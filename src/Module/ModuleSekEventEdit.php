<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use SeKultur\ContaoKulturnetzBundle\Models\SekEventsModel;

class ModuleSekEventEdit extends \Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_sekevent_edit';

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

			$objTemplate->wildcard = '### SE-KulturTage Event bearbeiten ###';
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
			$event = SekEventsModel::findByAlias($alias);
			
			//var_dump($event);
			//exit;
			
			if($event->member_id == $memberId) {
				$this->Template->event = $event;	
			} else {
				// REDIRECT 
			}
        } else {
			// REDIRECT LOGIN
		}
		
	}
}