<?php

namespace SeKultur\ContaoKulturnetzBundle\Models;

use Contao\Model;
use Contao\Model\Collection;

class HostsModel extends Model
{
    protected static $strTable = 'tl_hosts';
	
	protected static function getAvatar($avatar) 
	{
		$file = \Contao\FilesModel::findByUuid($avatar);
		
		$return = [
			'uuid' => $avatar,
		];
		
		return $file;
	}
	
	protected static function formatData($data) {
		// Ohne Titelbild keine Dateisuche: findByUuid(null) löst in PHP 8.5 eine
		// Deprecation aus. avatar_file bleibt wie bisher null.
		$data->avatar_file = $data->avatar ? \Contao\FilesModel::findByUuid($data->avatar) : null;
		
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
			foreach($data as &$host) {
				$host = static::formatData($host);
			}
			return $data;
			
			//return static::findAll($arrOptions);
		}
		return NULL;
    }
	
	public static function findAllHosts($limit = 100, $searchArr = false)
    {
		$t = static::$strTable;

		$arrOptions = [
			'column' => array("($t.disable!=?)"),
			'value'  => array('1'),
			'order' => '(id = 7) DESC, id DESC',
			'limit' => $limit
			//'return' => 'Array'
		];
		
		if(isset($searchArr['text']) && strlen($searchArr['text']) > 0) {
			$arrOptions['column'][] = "(name LIKE ? OR ort LIKE ?)";
			$arrOptions['value'][] = '%'.$searchArr['text'].'%';
			$arrOptions['value'][] = '%'.$searchArr['text'].'%';
		}

		$data = static::findAll($arrOptions);
			// findAll() liefert ohne Treffer null; ohne diese Prüfung meldet
			// PHP 8 bei foreach() eine Warnung. Rückgabe bleibt wie bisher null.
			if ($data === null) {
				return null;
			}
			foreach($data as &$host) {
				$host = static::formatData($host);
			}
			return $data;
		
		//return static::findAll($arrOptions);
    }
	
	// Contao\Model::findById() ist seit Contao 5 eine echte Methode (in 4.13
	// lief der Aufruf über __callStatic). Die Signatur muss deshalb
	// kompatibel sein, sonst bricht PHP schon beim Laden der Klasse ab.
	// Der Rückgabetyp ?static entspricht dem @return der Elternmethode.
	public static function findById($intId, array $arrOptions = array()): ?static
	{
		$rawdata = parent::findById($intId, $arrOptions);

		// Ohne Datensatz (z. B. gelöschtes Profil) gibt es nichts zu formatieren;
		// Aufrufer prüfen auf null. Sonst bricht formatData() mit einem Error ab.
		if (null === $rawdata) {
			return null;
		}

		return static::formatData($rawdata);
	}
}