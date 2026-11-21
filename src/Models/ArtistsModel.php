<?php

namespace SeKultur\ContaoKulturnetzBundle\Models;

use Contao\Model;
use Contao\Model\Collection;

class ArtistsModel extends Model
{
    protected static $strTable = 'tl_artists';
	
	protected static function getAvatar($avatar) 
	{
		$file = \Contao\FilesModel::findByUuid($avatar);
		
		$return = [
			'uuid' => $avatar,
		];
		
		return $file;
	}
	
	protected static function formatData($data) {
		if($avatar = \Contao\FilesModel::findByUuid($data->avatar)) {
			$data->avatar_file = $avatar;	
		} else {
			$data->avatar_file = '';
		}
		
		
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
			// findAll() liefert ohne Treffer null; ohne diese Prüfung meldet
			// PHP 8 bei foreach() eine Warnung. Rückgabe bleibt wie bisher null.
			if ($data === null) {
				return null;
			}
			foreach($data as &$artist) {
				$artist = static::formatData($artist);
			}
			return $data;

			//return static::findAll($arrOptions);
		}
		return NULL;
    }
	
	public static function findAllArtists($limit = 100, $searchArr = false)
    {
		$t = static::$strTable;
		
		$arrOptions = [
			'column' => [
				"($t.disable!=?)"
			],
			'value' => [
				'1'
			],
			'order' => '(id = 17) DESC, id DESC',
			'limit' => $limit
			//'return' => 'Array'
		];
		
		if(isset($searchArr['kategorie']) && $searchArr['kategorie'] !== 'Alle') {
			$arrOptions['column'][] = "(sparten LIKE ?)";
			$arrOptions['value'][] = '%'.$searchArr['kategorie'].'%';
		}
		
		if(isset($searchArr['text']) && strlen($searchArr['text']) > 0) {
			$arrOptions['column'][] = "(name LIKE ? OR ort LIKE ?)";
			$arrOptions['value'][] = '%'.$searchArr['text'].'%';
			$arrOptions['value'][] = '%'.$searchArr['text'].'%';
		}
		
		//var_dump($arrOptions);
		//exit;
		
		$data = static::findAll($arrOptions);
		// findAll() liefert ohne Treffer null; ohne diese Prüfung meldet
		// PHP 8 bei foreach() eine Warnung. Rückgabe bleibt wie bisher null.
		if ($data === null) {
			return null;
		}
		foreach($data as &$artist) {
			$artist = static::formatData($artist);
		}
		return $data;

		//return static::findAll($arrOptions);
    }
	
	// Contao\Model::findById() ist seit Contao 5 eine echte Methode (in 4.13
	// lief der Aufruf über __callStatic). Die Signatur muss deshalb
	// kompatibel sein, sonst bricht PHP schon beim Laden der Klasse ab.
	public static function findById($intId, array $arrOptions = array())
	{
		$rawdata = parent::findById($intId, $arrOptions);
		$data = static::formatData($rawdata);
		
		return $data;
	}
}