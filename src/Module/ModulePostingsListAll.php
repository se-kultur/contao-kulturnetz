<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use SeKultur\ContaoKulturnetzBundle\Models\PostingsModel;

class ModulePostingsListAll extends \Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_postings_list_all';

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

			$objTemplate->wildcard = '### Liste des öffentlichen Posting-News-Feeds ###';
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
		
		if($objPage->id == 7) { // Übersichtsseite SE-KulturNetz
			$postings = PostingsModel::findLastPostings(5);
		} else {
			$postings = PostingsModel::findLastPostings();
		}		
		$this->Template->postings = $postings;
		
		$memberId = 0;
		if (FE_USER_LOGGED_IN === true) {
            $objUser = \FrontendUser::getInstance();
            $memberId = $objUser->id;
        }
		$this->Template->member_id = $memberId;
		
	}
}