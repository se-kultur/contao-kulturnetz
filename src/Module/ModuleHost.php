<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use Contao\BackendTemplate;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Input;
use Contao\Module;
use SeKultur\ContaoKulturnetzBundle\Helper\RequestContext;
use SeKultur\ContaoKulturnetzBundle\Models\ArtistsModel;
use SeKultur\ContaoKulturnetzBundle\Models\HostsModel;
use SeKultur\ContaoKulturnetzBundle\Models\LocationsModel;
use SeKultur\ContaoKulturnetzBundle\Models\FollowersModel;
use SeKultur\ContaoKulturnetzBundle\Models\EventsModel;
use SeKultur\ContaoKulturnetzBundle\Models\SekEventsModel;
use SeKultur\ContaoKulturnetzBundle\Models\PostingsModel;

class ModuleHost extends Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_host';

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

			$objTemplate->wildcard = '### Detailansicht Veranstalter:innenprofil ###';
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
		$alias = Input::get('auto_item');
		// Ohne Alias gibt es kein Profil; findByIdOrAlias() erwartet in Contao 5.7 einen String.
		$host = (null !== $alias && '' !== $alias) ? HostsModel::findByIdOrAlias($alias) : null;

		if ($host === null) {
			throw new PageNotFoundException('Veranstalter:innenprofil nicht gefunden: ' . $alias);
		}

		$memberProfiles = [];
		$followingProfiles = [];

		$memberId = RequestContext::getMemberId();
		if ($memberId > 0) {
			$memberProfiles = [
				'tl_artists' => ArtistsModel::findByMemberId($memberId),
				'tl_hosts' => HostsModel::findByMemberId($memberId),
				'tl_locations' => LocationsModel::findByMemberId($memberId),
			];

			$fpData = FollowersModel::findMembersFollowingsFor($host->id, 'tl_hosts', $memberId);
			// Ohne Treffer liefert das Model null.
			foreach($fpData ?? [] as $fp) {
				$followingProfiles[] = $fp->follower_type.'-'.$fp->follower_id;
			}
		}
		$this->Template->member_id = $memberId;
		$this->Template->member_profiles = $memberProfiles;
		$this->Template->following_profiles = $followingProfiles;

		$this->Template->sekevents = SekEventsModel::findByProfileId($host->id, 'hosts');
		$this->Template->events = EventsModel::findByProfileId($host->id, 'hosts');
		$this->Template->followers = FollowersModel::findAllFor($host->id, 'tl_hosts');
		$this->Template->postings = PostingsModel::findAllFor($host->id, 'tl_hosts');
		$this->Template->host = $host;
	}
}