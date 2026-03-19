<?php

namespace SeKultur\ContaoKulturnetzBundle\Models;

use Contao\Model;
use Contao\Model\Collection;

class LocationsModel extends Model
{
    protected static $strTable = 'tl_locations';
	
	protected static function getAvatar($avatar) 
	{
		$file = \Contao\FilesModel::findByUuid($avatar);
		
		$return = [
			'uuid' => $avatar,
		];
		
		return $file;
	}
	
	protected static function formatData($data) {
		$avatar = \Contao\FilesModel::findByUuid($data->avatar);
		$data->avatar_file = $avatar;
		
		return $data;
	}
	
	public static function findByMemberId($id)
    {
		if(is_numeric($id)) {
			$t = static::$strTable;

			$arrOptions = [
				'column' => array("($t.member_id=?)"),
				'value'  => array($id),
				//'return' => 'Model'
			];

			$data = static::findAll($arrOptions);
			foreach($data as &$location) {
				$location = static::formatData($location);
			}
			return $data;
			
			//return static::findAll($arrOptions);
		}
		return NULL;
    }
	
	public static function findAllLocations($limit = 100, $searchArr = false)
    {
		$t = static::$strTable;

		$arrOptions = [
			'column' => array("($t.disable!=?)"),
			'value'  => array('1'),
			'order' => '(id = 16) DESC, id DESC',
			'limit' => $limit
			//'return' => 'Array'
		];
		
		if(isset($searchArr['text']) && strlen($searchArr['text']) > 0) {
			$arrOptions['column'][] = "(name LIKE ? OR ort LIKE ?)";
			$arrOptions['value'][] = '%'.$searchArr['text'].'%';
			$arrOptions['value'][] = '%'.$searchArr['text'].'%';
		}
		
		$data = static::findAll($arrOptions);
			foreach($data as &$location) {
				$location = static::formatData($location);
			}
			return $data;

		//return static::findAll($arrOptions);
    }
	
	public static function findById($id)
	{
		$rawdata = parent::findById($id);
		$data = static::formatData($rawdata);
		
		return $data;
	}
}