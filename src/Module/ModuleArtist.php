<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use SeKultur\ContaoKulturnetzBundle\Models\ArtistsModel;
use SeKultur\ContaoKulturnetzBundle\Models\HostsModel;
use SeKultur\ContaoKulturnetzBundle\Models\LocationsModel;
use SeKultur\ContaoKulturnetzBundle\Models\FollowersModel;
use SeKultur\ContaoKulturnetzBundle\Models\EventsModel;
use SeKultur\ContaoKulturnetzBundle\Models\SekEventsModel;
use SeKultur\ContaoKulturnetzBundle\Models\PostingsModel;

class ModuleArtist extends \Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_artist';

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

			$objTemplate->wildcard = '### Detailansicht Künstler:innenprofil ###';
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
		$artist = ArtistsModel::findByIdOrAlias($alias);
		
		if($artist !== NULL) {
			$memberId = 0;
			$memberProfiles = [];
			if (FE_USER_LOGGED_IN === true) {
				$objUser = \FrontendUser::getInstance();
				$memberId = $objUser->id;
				
				$memberProfiles = [
					'tl_artists' => ArtistsModel::findByMemberId($memberId),
					'tl_hosts' => HostsModel::findByMemberId($memberId),
					'tl_locations' => LocationsModel::findByMemberId($memberId),
				];
				
				$followingProfiles = [];
				$fpData = FollowersModel::findMembersFollowingsFor($artist->id, 'tl_artists', $memberId);
				foreach($fpData as $fp) {
					$followingProfiles[] = $fp->follower_type.'-'.$fp->follower_id;
				}
			}
			$this->Template->member_id = $memberId;
			$this->Template->member_profiles = $memberProfiles;
			$this->Template->following_profiles = $followingProfiles;
			
			$sekevents = SekEventsModel::findByProfileId($artist->id, 'artists');
			$this->Template->sekevents = $sekevents;
			
			$events = EventsModel::findByProfileId($artist->id, 'artists');
			$this->Template->events = $events;
			
			$followers = FollowersModel::findAllFor($artist->id, 'tl_artists');
			$this->Template->followers = $followers;
			
			$postings = PostingsModel::findAllFor($artist->id, 'tl_artists');
			$this->Template->postings = $postings;
			
			$this->Template->artist = $artist;		
			
		} else {
			// TODO: REDIRECT OR ERROR PAGE
		}
		
	}
}