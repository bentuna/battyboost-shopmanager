<?php
function price($diff) {
	global $deposit;
	
	$fullDays = floor($diff / 86400);
	$leftMinutes = floor($diff / 60) - ($fullDays * 1440);

	// TAGESPREIS: 3,00 € pro vollem Tag
	$daysPrice = $fullDays*3;

	// STUNDENPREIS: 0,39 € pro Stunde + ggf. 0,90 € Grundgebühr
	$minutesPrice = $leftMinutes * (.39 / 60);
	if($fullDays == 0) $minutesPrice += .9;
	if($minutesPrice > 3) $minutesPrice = 3;

	// GESAMTPREIS
	$price = $daysPrice + $minutesPrice;

	// 2h kostenlos
	if ($fullDays == 0 && $leftMinutes < 120) {
		$price = 0;
	}

	// KAUTION prüfen
	if ($price > $deposit) $price = $deposit;

	return round($price, 2);
}

function loadData() {
	$arr = @json_decode(file_get_contents('data/'.USER.'.json'), true);
	if (!$arr) $arr = [
		'lend' => [],
		'history' => []
	];
	return $arr;
}

function cronMove2History(&$myData) {
	$myDataChanged = false;
	$maxAge = time() - 3600 * 24 * 7;
	foreach ($myData['lend'] as $i => $v) {
		if ($v['ts'] < $maxAge) {
			// move to history
			$myData['history'][] = $v + [
					'rdate' => 'Nie',
					'rtime' => '',
					'price' => $deposit,
					'cable' => 'no',
					'adapter' => 'no'
				];
			unset($myData['lend'][$i]);
			$myDataChanged = true;
		}
	}
	if ($myDataChanged) saveData($myData);
}

function saveData($myData) {
	file_put_contents('data/'.USER.'.json', json_encode($myData, JSON_PRETTY_PRINT));
}

### AJAX
function addLend($myData, $id, $device) {
	global $deposit;
	
	if ($id) {
		$json = [
			'id' => $id,
			'device' => $device,
			'date' => date('d.m.y'),
			'time' => date('H:i'),
			'ts' => time()
		];
		$myData['lend'][] = $json;
		$myDataChanged = true;
		$json['success'] = true;
		saveData($myData);
	} else {
		$json = ['error' => 'Fehlerhafte/Fehlende Formularinformationen erhalten'];
	}
	return $json;
}

function calculatePrice($myData, $id) {
	foreach ($myData['lend'] as $k => $lend) {
		if ($lend['id'] == $id) {
			$to = time();
			$price = price($to - $lend['ts']);
			$json = [
				'price' => floor($price * 100) / 100,
				'time' => $to,
				'device' => $lend['device']
			];
			$found = true;
			break;
		}
	}
	if ($found) $json['success'] = true;
	else $json['error'] = 'Akku-Nr. konnte nicht ermittelt werden.';
	return $json;
}

function returned($myData, $id, $cable, $adapter, $toReceived) {
	global $deposit;
	
	$error = 'AkkuNr konnte nicht gefunden werden. Bitte aktualisieren Sie die Seite und versuchen Sie es erneut.';
	if ($id) {
		$found = false;
		foreach ($myData['lend'] as $k => $lend) {
			if ($lend['id'] == $id) {
				if (time() - $toReceived > 60 * 12) {
					$error = 'Zeit abgelaufen, versuchen Sie es erneut.';
					break;
				}
				$price = price($toReceived - $lend['ts']);
				if ($lend['device']) {
					if (!$cable) $price += 2;
					if ($lend['device'] > 1 && !$adapter) $price += 1.5;
				}
				if ($price > $deposit) $price = $deposit;
				$d = $lend + [
						'rdate' => date('d.m.y', $toReceived), 'rtime' => date('H:i', $toReceived),
						'rts' => $toReceived,
						'price' => number_format(floor($price * 100) / 100, 2, ',', ' '),
						'cable' => $cable ? 'yes' : 'no',
						'adapter' => $adapter ? 'yes' : 'no'
					];
				$myData['history'][] = $d;
				$json = $d;
				unset($myData['lend'][$k]);
				saveData($myData);
				$found = true;
				break;
			}
		}
		if ($found) $json['success'] = true;
		else $json = ['error' => $error];
	} else {
		$json = ['error' => 'AkkuNr konnte nicht ermittelt werden'];
	}
	return $json;
}
