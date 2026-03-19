<?php

namespace SeKultur\ContaoKulturnetzBundle\Models;

use Contao\Model;
use Contao\Model\Collection;

class EventsModel extends Model
{
    protected static $strTable = 'tl_events';
	
	public static function findByProfileId($id, $type)
	{
		if(is_numeric($id)) {
			$t = static::$strTable;

			$arrOptions = [
				'column' => array("($t.linked_$type LIKE ?)"),
				'value'  => array("%".$id."%"),
				//'return' => 'Model'
			];

			$rawdata = static::findAll($arrOptions);
			$data = [];
		
			if($rawdata != null) {
				foreach($rawdata as $d) {
					$format = static::formatData($d);

					//if(1 == 2) { 	// VARIANTE 1: Ein Event wird nur 1x mit dem nächstmöglichen Datum angezeigt
					//	$data[$format->nextdate.$format->id] = $format;
					//} else { 		// VARIANTE 2: Ein Event wird zu jedem angegebenen Datum angezeigt 
					$date = strtotime(date('Y-m-d',$format->nextdate));
					$data[$date.'_'.$format->id] = $format;
					//}
				}

				ksort($data);
			}
			return $data;
		}
		return NULL;
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

			$rawdata = static::findAll($arrOptions);
			$data = [];
		
			if($rawdata != null) {
				foreach($rawdata as $d) {
					$format = static::formatData($d);

					//if(1 == 2) { 	// VARIANTE 1: Ein Event wird nur 1x mit dem nächstmöglichen Datum angezeigt
					//	$data[$format->nextdate.$format->id] = $format;
					//} else { 		// VARIANTE 2: Ein Event wird zu jedem angegebenen Datum angezeigt 
					$date = strtotime(date('Y-m-d',$format->nextdate));
					$data[$date.'_'.$format->id] = $format;
					//}
				}

				ksort($data);
			}
			return $data;
		}
		return NULL;
    }
	
	/*public static function findAllSekEvents()
    {
		$t = static::$strTable;

		$arrOptions = [
			'column' => array("($t.disable!=?)"),
			'value'  => array('1'),
			'order' => 'id DESC'
			//'return' => 'Array'
		];

		return static::findAll($arrOptions);
    }*/
	
	protected static function getAvatar($avatar) 
	{
		$file = \Contao\FilesModel::findByUuid($avatar);
		
		$return = [
			'uuid' => $avatar,
			//'path' => $file->path,
		];
		
		return $file;
	}
	
	protected static function formatData($data) {
		//$avatar = static::getAvatar($data->avatar); 
		$avatar = \Contao\FilesModel::findByUuid($data->avatar);
		$data->avatar_file = $avatar;
		//var_dump($data);
		//exit;
		
		$artists = [];
		$linked_artists = json_decode($data->linked_artists,true);
		foreach($linked_artists as $id) {
			if($a = ArtistsModel::findById($id)) {
				$a->avatar_file = \Contao\FilesModel::findByUuid($a->avatar);
				$artists[] = $a;
			}
		} 
		$data->linked_artists_data = $artists;
		
		$locations = [];
		$linked_locations = json_decode($data->linked_locations,true);
		foreach($linked_locations as $id) {
			if($l = LocationsModel::findById($id)) {
				$l->avatar_file = \Contao\FilesModel::findByUuid($l->avatar);
				$locations[] = $l;
			}
		} 
		$data->linked_locations_data = $locations;
		
		$hosts = [];
		$linked_hosts = json_decode($data->linked_hosts,true);
		foreach($linked_hosts as $id) {
			if($h = HostsModel::findById($id)) {
				$h->avatar_file = \Contao\FilesModel::findByUuid($h->avatar);
				$hosts[] = $h;
			}
		} 
		$data->linked_hosts_data = $hosts;
		
		$dates = [];
		$i = 0;
		$json_dates = json_decode($data->dates,true);
		$weekdays = [
			'Sonntag',
			'Montag',
			'Dienstag',
			'Mittwoch',
			'Donnerstag',
			'Freitag',
			'Samstag',
		];
		foreach($json_dates as $d) {
			$tstamp = strtotime($d['date'].' '.$d['start'].':00');
			$d['text'] = $weekdays[date('w', $tstamp)].', '.date('d.m.Y', $tstamp);
			$dates[$tstamp.'_'.$i] = $d;
			$i++;
		}
		ksort($dates);
		$data->dates_formatted = $dates;
		
		$nextdate = false;
		foreach($dates as $tstamp => $x) {
			$tstamp = explode('_', $tstamp)[0];
			if($nextdate == false && $tstamp > time()) {
				$nextdate = $tstamp;
				break;
			} 
		}
		$data->nextdate = $nextdate;
		
		return $data;
	}
	
	public static function findByAlias($alias) 
	{
		$data = static::findByIdOrAlias($alias);
		
		static::formatData($data);
		
		return $data;
	}
	
	public static function getStats()
	{
		$t = static::$strTable;

		$arrOptions = [
			'column' => array("($t.disable!=?)"),
			'value'  => array('1'),
			//'group' => 'location_ort'
			//'return' => 'Array'
		];
		
		$data = [];
		$location_orte = [];
		
		//$rawdata = static::findAll($arrOptions);
		$rawdata = static::findAllEvents();
		
		foreach($rawdata as $r) {
			$key = trim($r->location_ort);
			$location_orte[$key] = ($location_orte[$key] ?? 0) + 1;
			$data[] = $r->location_adresse.', '.$r->location_plz.' '.$r->location_ort;
		}
		return [
			'count' => count($rawdata),
			'location_orte' => $location_orte,
			'locations' => $data
		];
	}
	
	public static function findAllEvents($limit = 0, $searchArr = false)
    {
		$t = static::$strTable;

		$arrOptions = [
			'column' => array("($t.disable!=?)"),
			'value'  => array('1'),
			'order' => 'id DESC',
		];
		
		if(isset($searchArr['kategorie']) && $searchArr['kategorie'] !== 'Alle') {
			$arrOptions['column'][] = "(sparten LIKE ?)";
			$arrOptions['value'][] = '%'.$searchArr['kategorie'].'%';
		}
		
		if(isset($searchArr['format']) && $searchArr['format'] !== 'Alle') {
			$arrOptions['column'][] = "(kulturform LIKE ?)";
			$arrOptions['value'][] = '%'.$searchArr['format'].'%';
		}
		
		if(isset($searchArr['text']) && strlen($searchArr['text']) > 0) {
			$arrOptions['column'][] = "(name LIKE ? OR location_ort LIKE ?)";
			$arrOptions['value'][] = '%'.$searchArr['text'].'%';
			$arrOptions['value'][] = '%'.$searchArr['text'].'%';
		}
		
		if(isset($searchArr['datum']) && $searchArr['datum'] !== '') {
			$arrOptions['column'][] = "(dates LIKE ?)";
			$arrOptions['value'][] = '%"date":"'.$searchArr['datum'].'"%';
		}

		$rawdata = static::findAll($arrOptions);
		$data = [];
		
		foreach($rawdata as $d) {
			$format = static::formatData($d);
			
			//if(1 == 2) { 	// VARIANTE 1: Ein Event wird nur 1x mit dem nächstmöglichen Datum angezeigt
			//	$data[$format->nextdate.$format->id] = $format;
			//} else { 		// VARIANTE 2: Ein Event wird zu jedem angegebenen Datum angezeigt 
			foreach($format->dates_formatted as $tstamp => $x) {
				$tstamp = explode('_', $tstamp)[0];
				
				if(isset($searchArr['datum']) && $searchArr['datum'] !== '') {
					$searchdate = explode('.', $searchArr['datum']);
					$searchday = $searchdate[0];
					$searchmonth = $searchdate[1];
					$searchyear = $searchdate[2];

					$searchstart = mktime(0,0,0,$searchmonth,$searchday,$searchyear);
					$searchend = mktime(23,59,59,$searchmonth,$searchday,$searchyear);

					if($tstamp > $searchstart && $tstamp < $searchend) {
						$date = strtotime(date('Y-m-d',$tstamp));
						$data[$date.'_'.$format->id] = $format;
					}
				} else {
					$date = strtotime(date('Y-m-d',$tstamp));
					$data[$date.'_'.$format->id] = $format;
				}
			}
			//}
		}
		
		ksort($data);
		return $data;
    }
	
}