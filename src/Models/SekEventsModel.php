<?php

namespace SeKultur\ContaoKulturnetzBundle\Models;

use Contao\Model;
use Contao\Model\Collection;

class SekEventsModel extends Model
{
    protected static $strTable = 'tl_sekevents';
	
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
					$date = strtotime(date('Y-m-d-His',$format->nextdate));
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
		// Die Spalte kann laut DCA NULL sein. json_decode(null,...) löst unter
		// PHP 8.1 eine Deprecation aus ("Passing null to parameter #1 ($json)
		// of type string is deprecated"), das "?: []" fängt außerdem ein
		// ungültiges/leeres JSON ab, damit der foreach darunter nicht über
		// null läuft.
		$linked_artists = json_decode((string) $data->linked_artists, true) ?: [];
		foreach($linked_artists as $id) {
			if($a = ArtistsModel::findById($id)) {
				$a->avatar_file = \Contao\FilesModel::findByUuid($a->avatar);
				$artists[] = $a;
			}
		} 
		$data->linked_artists_data = $artists;
		
		$locations = [];
		// Siehe Kommentar bei $linked_artists weiter oben: NULL-sicher decodieren.
		$linked_locations = json_decode((string) $data->linked_locations, true) ?: [];
		foreach($linked_locations as $id) {
			if($l = LocationsModel::findById($id)) {
				$l->avatar_file = \Contao\FilesModel::findByUuid($l->avatar);
				$locations[] = $l;
			}
		} 
		$data->linked_locations_data = $locations;
		
		$hosts = [];
		// Siehe Kommentar bei $linked_artists weiter oben: NULL-sicher decodieren.
		$linked_hosts = json_decode((string) $data->linked_hosts, true) ?: [];
		foreach($linked_hosts as $id) {
			if($h = HostsModel::findById($id)) {
				$h->avatar_file = \Contao\FilesModel::findByUuid($h->avatar);
				$hosts[] = $h;
			}
		} 
		$data->linked_hosts_data = $hosts;
		
		$dates = [];
		$i = 0;
		// NULL-sicher decodieren, siehe Kommentar bei $linked_artists weiter oben.
		$json_dates = json_decode((string) $data->dates, true) ?: [];
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
			// 'start' ist ein ungeprüftes Freitextfeld. Fehlt die Uhrzeit oder
			// ist sie ungültig, wird auf Mitternacht zurückgefallen statt den
			// Termin stillschweigend zu verwerfen: strtotime() lieferte bei
			// z. B. "15.09.2026 :00" (leeres 'start') false, wodurch der
			// Termin hier übersprungen wurde, aber in getStatsAndDateRange()
			// (die bewusst ohne Uhrzeit rechnet) trotzdem als wählbarer Tag im
			// Datepicker auftauchte - die Liste blieb an diesem Tag dann leer.
			$start = preg_match('/^\d{1,2}:\d{2}$/', (string) ($d['start'] ?? '')) ? $d['start'] : '00:00';
			$tstamp = strtotime($d['date'].date('Y').' '.$start.':00');
			// Ungültige bzw. leere Datumsangaben überspringen. Sonst liefert
			// strtotime() false, das als Array-Schlüssel "_i" landet und später
			// in date() einen leeren String erzeugt -> TypeError unter PHP 8.
			if($tstamp === false) {
				$i++;
				continue;
			}
			$d['text'] = $weekdays[date('w', $tstamp)].', '.date('d.m.Y', $tstamp);
			$dates[$tstamp.'_'.$i] = $d;
			$i++;
		}
		ksort($dates);
		$data->dates_formatted = $dates;

		$nextdate = false;
		foreach($dates as $tstamp => $x) {
			$tstamp = (int) explode('_', $tstamp)[0];
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

		if ($data === null) {
			return null;
		}

		static::formatData($data);

		return $data;
	}
	
	/**
	 * Ermittelt in einem einzigen Tabellendurchlauf sowohl die Orts-Statistik
	 * (für die Filter-Badges) als auch den frühesten/spätesten Termin (für die
	 * Begrenzung des Datepickers auf den tatsächlichen Festival-Zeitraum).
	 * Beide Werte basieren auf denselben aktiven Datensätzen. Zuvor gab es allein
	 * getStats(); diese Methode lief über findAllSekEvents() und rutschte dabei in
	 * dessen Default-Limit von 100 Datensätzen. Der Datumsbereich kommt neu hinzu
	 * und wird bewusst im selben Durchlauf ermittelt, statt dafür eine zweite
	 * Abfrage aufzusetzen. Arbeitet bewusst unabhängig von Suchfiltern (immer
	 * alle aktiven Termine) und ohne formatData(), da für Statistik und
	 * Zeitraum nur location_ort/-adresse/-plz sowie dates benötigt werden -
	 * die in formatData() zusätzlich aufgelösten Artists/Locations/Hosts pro
	 * Event wären hier unnötiger Aufwand.
	 *
	 * @return array{
	 *     stats: array{count:int, location_orte:array<string,int>, locations:string[]},
	 *     dateRange: array{min: ?string, max: ?string}
	 * }
	 */
	public static function getStatsAndDateRange()
	{
		$t = static::$strTable;

		$arrOptions = [
			'column' => array("($t.disable!=?)"),
			'value'  => array('1'),
			'order'  => 'id ASC',
		];

		$rawdata = static::findAll($arrOptions);

		$count = 0;
		$location_orte = [];
		$locations = [];
		$min = null;
		$max = null;

		// Grenze wie in findAllSekEvents(): Statistik, Orts-Badges und Datumsbereich
		// müssen auf derselben Datenbasis stehen wie die Trefferliste. Sonst bietet
		// die Oberfläche Orte und Datumsgrenzen an, die garantiert zu null Treffern
		// führen - etwa einen Ort, der nur noch vergangene Veranstaltungen hat.
		$heuteMitternacht = strtotime('today midnight');

		if ($rawdata !== null) {
			foreach ($rawdata as $r) {
				// NULL-sicher decodieren, siehe Kommentar bei $linked_artists in formatData().
				$json_dates = json_decode((string) $r->dates, true) ?: [];
				$hatKuenftigenTermin = false;

				foreach ($json_dates as $d) {
					if (empty($d['date'])) {
						continue;
					}

					// Termine werden ohne Jahr gespeichert, das aktuelle Jahr wird
					// wie in formatData() zur Laufzeit ergänzt. Die Uhrzeit ist für
					// eine reine Datumsrange irrelevant und wird bewusst nicht mehr
					// einbezogen: 'start' ist ein ungeprüftes Freitextfeld (JSON-
					// Textfeld im Backend), ein leerer String hätte zuvor zu
					// "15.09.2026 :00" geführt, wofür strtotime() false liefert -
					// der Termin wäre dann stillschweigend aus der Min/Max-
					// Ermittlung herausgefallen.
					$tstamp = strtotime($d['date'].date('Y'));

					if ($tstamp === false) {
						continue;
					}

					if ($tstamp < $heuteMitternacht) {
						continue;
					}

					$hatKuenftigenTermin = true;

					if ($min === null || $tstamp < $min) {
						$min = $tstamp;
					}

					if ($max === null || $tstamp > $max) {
						$max = $tstamp;
					}
				}

				// Veranstaltungen ohne heutigen oder künftigen Termin erscheinen nicht
				// in der Liste und dürfen deshalb auch nicht in Zähler und Orts-Badges
				// auftauchen.
				if (!$hatKuenftigenTermin) {
					continue;
				}

				$count++;

				$ort = trim($r->location_ort);
				$location_orte[$ort] = ($location_orte[$ort] ?? 0) + 1;
				$locations[] = $r->location_adresse.', '.$r->location_plz.' '.$r->location_ort;
			}
		}

		return [
			'stats' => [
				'count' => $count,
				'location_orte' => $location_orte,
				'locations' => $locations,
			],
			'dateRange' => [
				'min' => $min !== null ? date('d.m.Y', $min) : null,
				'max' => $max !== null ? date('d.m.Y', $max) : null,
			],
		];
	}

	/**
	 * Liefert ausschließlich die Orts-Statistik.
	 *
	 * @deprecated Ersetzt durch getStatsAndDateRange(), das zusätzlich den
	 *             Datumsbereich zurückgibt. Diese Methode bleibt erhalten, damit
	 *             fremde Installationen des öffentlichen Bundles, die weiterhin
	 *             getStats() aufrufen, nicht mit einem Fatal Error brechen.
	 */
	public static function getStats()
	{
		return static::getStatsAndDateRange()['stats'];
	}

	public static function findAllSekEvents($limit = 100, $searchArr = false)
    {
		$t = static::$strTable;

		$arrOptions = [
			'column' => array("($t.disable!=?)"),
			'value'  => array('1'),
			'order' => 'id DESC', //'(id = 16) DESC, id DESC',
			'limit' => $limit
			//'return' => 'Array'
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
		
		// Der Filterwert wird hier EINMAL strikt geparst und für die spätere
		// PHP-seitige Nachfilterung weiter unten wiederverwendet (statt ihn
		// dort ein zweites Mal separat per explode() zu zerlegen). Akzeptiert
		// werden Tag und Monat mit je 1-2 Ziffern, optional gefolgt von einem
		// Punkt und/oder einer vierstelligen Jahreszahl (Formulare aus dem
		// Datepicker liefern z. B. "15.09.2026", gespeichert ist "15.09.").
		// Ein nicht zum Muster passender Wert (z. B. LIKE-Wildcards wie "%"
		// oder "_", die sonst ungeprüft ins SQL-Muster liefen, oder Werte, die
		// weiter unten mktime() mit einem nicht-numerischen Tag/Monat gefüttert
		// hätten) wird als ungültig behandelt.
		$datumFilterActive = false;
		$datumFilterDay = null;
		$datumFilterMonth = null;

		if(isset($searchArr['datum']) && $searchArr['datum'] !== '') {
			if (preg_match('/^(\d{1,2})\.(\d{1,2})\.?(\d{4})?$/', $searchArr['datum'], $matches)) {
				$datumFilterDay = (int) $matches[1];
				$datumFilterMonth = (int) $matches[2];
				$datumFilterYear = (isset($matches[3]) && $matches[3] !== '') ? (int) $matches[3] : null;

				// Termine werden ohne Jahr gespeichert, das laufende Jahr wird
				// zur Laufzeit ergänzt (siehe formatData()/Nachfilter unten).
				// Enthält der Filterwert eine davon abweichende Jahreszahl,
				// stünde im Formularfeld ein anderes Jahr als das der
				// zurückgelieferten Treffer - der Filter liefert in diesem
				// Fall bewusst keine Treffer statt inkonsistenter.
				if ($datumFilterYear === null || $datumFilterYear === (int) date('Y')) {
					$datumFilterActive = true;
				}
			}

			if ($datumFilterActive) {
				$arrOptions['column'][] = "(dates LIKE ?)";
				$arrOptions['value'][] = sprintf('%%"date":"%02d.%02d."%%', $datumFilterDay, $datumFilterMonth);
			} else {
				// Ungültiger oder jahresfremder Filterwert: bewusst keine
				// Treffer statt eines Serverfehlers oder einer irreführenden
				// Teilmenge zurückgeben.
				$arrOptions['column'][] = '(1 = 0)';
			}
		}

		$rawdata = static::findAll($arrOptions);
		$data = [];

		// Model::find() liefert bei null Treffern null zurück, keine leere
		// Collection. Ohne diese Absicherung erzeugt der foreach darunter unter
		// PHP 8 eine Warning ("foreach() argument must be of type array|object,
		// null given"), z. B. wenn der Datumsfilter einen Tag ohne Termine trifft.
		if ($rawdata === null) {
			return $data;
		}

		// Vergangene Termine gehören nicht in Liste und Suche - ausgegeben werden nur
		// heutige und künftige Veranstaltungen. Die Grenze ist Mitternacht, damit ein
		// Termin am laufenden Tag sichtbar bleibt, auch wenn seine Uhrzeit bereits
		// vorbei ist. Vergangene Veranstaltungen bleiben über den Direktlink auf ihre
		// Detailseite erreichbar, nur eben nicht mehr über die Übersicht.
		$heuteMitternacht = strtotime('today midnight');

		foreach($rawdata as $d) {
			$format = static::formatData($d);
			
			//if(1 == 2) { 	// VARIANTE 1: Ein Event wird nur 1x mit dem nächstmöglichen Datum angezeigt
			//	$data[$format->nextdate.$format->id] = $format;
			//} else { 		// VARIANTE 2: Ein Event wird zu jedem angegebenen Datum angezeigt 
			foreach($format->dates_formatted as $tstamp => $x) {
				$tstamp = (int) explode('_', $tstamp)[0];
				if($tstamp <= 0) {
					continue;
				}

				if($tstamp < $heuteMitternacht) {
					continue;
				}

				if($datumFilterActive) {
					$searchstart = mktime(0,0,0,$datumFilterMonth,$datumFilterDay,(int) date('Y'));
					$searchend = mktime(23,59,59,$datumFilterMonth,$datumFilterDay,(int) date('Y'));

					// Grenzen bewusst inklusiv: Ein Termin exakt um 00:00 Uhr entspricht genau
					// $searchstart und fiele bei striktem Vergleich aus dem Datumsfilter
					// heraus - das betrifft sowohl den Fallback für fehlende Uhrzeiten als
					// auch legitim auf 00:00 gesetzte Termine.
					if($tstamp >= $searchstart && $tstamp <= $searchend) {
						$date = strtotime(date('Y-m-d-His',$tstamp));
						$data[$date.'_'.$format->id] = $format;
					}
				} else {
					$date = strtotime(date('Y-m-d-His',$tstamp));
					$data[$date.'_'.$format->id] = $format;
				}
			}
			//}
		}
		
		ksort($data);
		return $data;
    }

	/**
	 * Mischt die Veranstaltungen innerhalb eines Tages, ohne die Reihenfolge der
	 * Tage selbst zu verändern - das Programm bleibt also nach Tagen chronologisch,
	 * nur innerhalb eines Tages wechselt die Reihenfolge. Damit stehen nicht immer
	 * dieselben Veranstaltungen oben.
	 *
	 * Gemischt wird bei jedem Seitenaufruf neu, die Reihenfolge ändert sich also
	 * auch beim Neuladen oder Filtern. Das ist so gewollt: Die Rotation soll
	 * ständig stattfinden und nicht nur einmal pro Tag.
	 *
	 * @param array       $data Chronologisch sortierte Liste, Schlüssel "<tstamp>_<id>"
	 * @param string|null $seed Fester Seed für reproduzierbare Reihenfolgen in Tests.
	 *                          Im Normalbetrieb null, dann wird echt zufällig gemischt.
	 *
	 * @return array
	 */
	public static function mischeInnerhalbDerTage(array $data, $seed = null)
	{
		if (count($data) < 2) {
			return $data;
		}

		// Nach Kalendertag gruppieren. Die Eingabe ist bereits chronologisch
		// sortiert, dadurch bleibt die Reihenfolge der Tage automatisch erhalten.
		$nachTag = [];
		foreach ($data as $key => $event) {
			$tstamp = (int) explode('_', (string) $key)[0];
			$nachTag[date('Y-m-d', $tstamp)][$key] = $event;
		}

		$ergebnis = [];

		foreach ($nachTag as $eventsDesTages) {
			$schluessel = array_keys($eventsDesTages);

			if ($seed === null) {
				// Normalbetrieb: bei jedem Aufruf eine neue Reihenfolge.
				shuffle($schluessel);
			} else {
				// Fester Seed: reproduzierbare Reihenfolge für Tests.
				usort($schluessel, static function ($a, $b) use ($seed) {
					$hashA = crc32($seed.'|'.$a);
					$hashB = crc32($seed.'|'.$b);

					// Bei identischem Hash nach Schlüssel entscheiden, damit die
					// Reihenfolge auch in diesem Fall eindeutig bleibt.
					return $hashA === $hashB ? strcmp((string) $a, (string) $b) : ($hashA <=> $hashB);
				});
			}

			foreach ($schluessel as $key) {
				$ergebnis[$key] = $eventsDesTages[$key];
			}
		}

		return $ergebnis;
	}

}