<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use SeKultur\ContaoKulturnetzBundle\Models\ArtistsModel;

class ModuleArtistEdit extends \Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_artist_edit';

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

			$objTemplate->wildcard = '### Künstlerprofil bearbeiten ###';
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
			$artist = ArtistsModel::findByIdOrAlias($alias);
			
			if($artist->member_id == $memberId) {
				$this->Template->artist = $artist;	
			} else {
				// REDIRECT 
			}
        } else {
			// REDIRECT LOGIN
		}
		
	}
}