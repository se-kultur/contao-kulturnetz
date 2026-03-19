<?php

namespace SeKultur\ContaoKulturnetzBundle\Models;

use Contao\Model;
use Contao\Database;
use Contao\Model\Collection;
use SeKultur\ContaoKulturnetzBundle\Models\FollowersModel;
use SeKultur\ContaoKulturnetzBundle\Models\ArtistsModel;
use SeKultur\ContaoKulturnetzBundle\Models\HostsModel;
use SeKultur\ContaoKulturnetzBundle\Models\LocationsModel;

class PostingsModel extends Model
{
    protected static $strTable = 'tl_postings';
	
	public static function timeAgo($tstamp) {
		$timeDiff = time()-$tstamp;
		
		$r = 'Am '.date('d.m.Y, H:i', $tstamp).' Uhr';
		if($timeDiff < 60) {
			$r = 'Vor wenigen Sekunden';
		} elseif($timeDiff < 3600) {
			$r = 'Vor '.ceil($timeDiff/60).' Minuten';
		} elseif($timeDiff < 86400) {
			$r = 'Vor '.ceil($timeDiff/3600).' Stunden';
		} elseif($timeDiff < 604800) {
			$r = 'Vor '.ceil($timeDiff/86400).' Tagen';
		} elseif($timeDiff < 2419200) {
			$r = 'Vor '.ceil($timeDiff/604800).' Wochen';
		} 
		
		return $r;
	}
	
	public static function findAllFor($id, $type)
    {
		$t = static::$strTable;

		$arrOptions = [
			'column' => [
				"($t.profile_id=?)",
				"($t.profile_type=?)",
			],
			'value'  => [
				$id,
				$type,
			],
			'order' => 'id DESC',
			'limit' => 25
			//'return' => 'Model'
		];

		$data = static::findAll($arrOptions);
		if($data != null) {
			foreach($data as &$d) {
				$d->timeago = static::timeAgo($d->tstamp);
			}
		}
		return $data;
    }
	
	public static function findForMemberId($id) {
		$q = "SELECT * FROM `tl_postings` WHERE `tl_postings`.`profile_id` IN (SELECT following_id FROM `tl_followers` WHERE `tl_followers`.`member_id` = '".$id."') OR `tl_postings`.`member_id` = '".$id."' ORDER BY id DESC LIMIT 25";
		$results = Database::getInstance()->prepare($q)->execute()->fetchAllAssoc();
		
		if($results != null) {
			foreach($results as &$r) {
				$profile = false;
				if($r['profile_type'] == 'tl_artists') {
					$profile = ArtistsModel::findById($r['profile_id']);
				} elseif($r['profile_type'] == 'tl_hosts') {
					$profile = HostsModel::findById($r['profile_id']);
				} elseif($r['profile_type'] == 'tl_locations') {
					$profile = LocationsModel::findById($r['profile_id']);
				}

				$r['profile'] = $profile;
				$r['timeago'] = static::timeAgo($r['tstamp']);
			}
		}

		return $results;
	}
	
	public static function findLastPostings($limit = 50) {
		$q = "SELECT * FROM `tl_postings` ORDER BY id DESC LIMIT ".$limit;
		$results = Database::getInstance()->prepare($q)->execute()->fetchAllAssoc();
		
		if($results != null) {
			foreach($results as &$r) {
				$profile = false;
				if($r['profile_type'] == 'tl_artists') {
					$profile = ArtistsModel::findById($r['profile_id']);
				} elseif($r['profile_type'] == 'tl_hosts') {
					$profile = HostsModel::findById($r['profile_id']);
				} elseif($r['profile_type'] == 'tl_locations') {
					$profile = LocationsModel::findById($r['profile_id']);
				}

				$r['profile'] = $profile;
				$r['timeago'] = static::timeAgo($r['tstamp']);
			}
		}

		return $results;
	}
	
}