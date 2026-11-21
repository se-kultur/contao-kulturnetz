<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use Contao\BackendTemplate;
use Contao\Module;
use SeKultur\ContaoKulturnetzBundle\Helper\RequestContext;
use SeKultur\ContaoKulturnetzBundle\Models\ArtistsModel;


class ModuleArtistsListAll extends Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_artists_list';

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

			$objTemplate->wildcard = '### Alle Künstler:innenprofile ###';
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

		if($objPage->id == 7) { // Übersichtsseite SE-KulturNetz
			$artists = ArtistsModel::findAllArtists(3);
			$this->Template->artists = $artists;	
		} else {
			$artists = ArtistsModel::findAllArtists(100, @$_GET);
			$this->Template->artists = $artists;	
			$filter = true;
		}		
		
		$this->Template->filter = $filter;
	}
}