<?php

namespace SeKultur\ContaoKulturnetzBundle\Models;

use Contao\Model;
use Contao\Model\Collection;
use SeKultur\ContaoKulturnetzBundle\Models\ArtistsModel;
use SeKultur\ContaoKulturnetzBundle\Models\HostsModel;
use SeKultur\ContaoKulturnetzBundle\Models\LocationsModel;

class FollowersModel extends Model
{
    protected static $strTable = 'tl_followers';
	
	public static function findFollowersFor($id, $type)
    {
		$t = static::$strTable;

		$arrOptions = [
			'column' => [
				"($t.following_id=?)",
				"($t.following_type=?)"
			],
			'value'  => [
				$id,
				$type
			],
			//'return' => 'Array'
		];
		
		$profile = false;
		$data = static::findAll($arrOptions);
		foreach($data as &$d) {
			if($d->follower_type == 'tl_artists') {
				$profile = ArtistsModel::findById($d->follower_id);
			} elseif($d->follower_type == 'tl_hosts') {
				$profile = HostsModel::findById($d->follower_id);
			} elseif($d->follower_type == 'tl_locations') {
				$profile = LocationsModel::findById($d->follower_id);
			}
			
			$d->profile = $profile;
		}
		return $data;
    }
	
	public static function findFollowingsFor($id, $type)
    {
		$t = static::$strTable;

		$arrOptions = [
			'column' => [
				"($t.follower_id=?)",
				"($t.follower_type=?)"
			],
			'value'  => [
				$id,
				$type
			],
			//'return' => 'Array'
		];
		
		$profile = false;
		$data = static::findAll($arrOptions);
		if($data != null) {
			foreach($data as &$d) {
				if($d->following_type == 'tl_artists') {
					$profile = ArtistsModel::findById($d->following_id);
				} elseif($d->following_type == 'tl_hosts') {
					$profile = HostsModel::findById($d->following_id);
				} elseif($d->following_type == 'tl_locations') {
					$profile = LocationsModel::findById($d->following_id);
				}

				$d->profile = $profile;
			}
		}
		return $data;
    }
	
	public static function findFollowingsForMember($id)
    {
		$t = static::$strTable;

		$arrOptions = [
			'column' => [
				"($t.member_id=?)",
			],
			'value'  => [
				$id,
			],
			//'return' => 'Array'
		];
		
		$profile = false;
		$data = static::findAll($arrOptions);
		foreach($data as &$d) {
			if($d->following_type == 'tl_artists') {
				$profile = ArtistsModel::findById($d->following_id);
			} elseif($d->following_type == 'tl_hosts') {
				$profile = HostsModel::findById($d->following_id);
			} elseif($d->following_type == 'tl_locations') {
				$profile = LocationsModel::findById($d->following_id);
			}
			
			$d->profile = $profile;
		}
		return $data;
    }
	
	public static function findMembersFollowingsFor($id, $type, $member_id)
    {
		$t = static::$strTable;

		$arrOptions = [
			'column' => [
				"($t.following_id=?)",
				"($t.following_type=?)",
				"($t.member_id=?)",
			],
			'value'  => [
				$id,
				$type,
				$member_id
			],
			//'return' => 'Array'
		];
		
		return static::findAll($arrOptions);
    }
	
	public static function findAllFor($id, $type)
    {
		$followers = static::findFollowersFor($id, $type);
		$followings = static::findFollowingsFor($id, $type);
		
		$data = [];
		if($followers != null) {
			foreach($followers as $f) {
				$data['followers'][$f->follower_type.'-'.$f->follower_id] = $f;
			}
		}
		
		if($followings != null) {
			foreach($followings as $f) {
				$data['followings'][$f->following_type.'-'.$f->following_id] = $f;
			}
		}
		
		return $data;
    }
}