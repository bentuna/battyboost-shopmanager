<?php
$user = isset($_GET['user']) ? $_GET['user'] : false;
if (!$user || !preg_match('~^[\w-]+$~', $user) || !is_file('data/'.$user.'.json')) {
	http_response_code(403);
	die('<!doctype html>
<html>
	<head>
		<title>Kein Zugriff</title>
		<meta name="robots" content="noindex">
		<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
		<link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-pink.min.css">
	</head>
	<body>
		Sie haben keinen Zugriff
	</body>
</html>');
}

$myData = @json_decode(file_get_contents('data/'.$user.'.json'), true);
if (!$myData) $myData = [
	'lend' => [],
	'history' => []
];
$myDataChanged = false;

$deposit = 12;

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

	// KAUTION prüfen
	if ($price > $deposit) $price = $deposit;

	return round($price, 2);
}

if (isset($_POST['ajax'])) {
	header('Content-Type: application/json');
	switch ($_POST['ajax']) {
		case 'add':
			$id = +$_POST['akkuNr'];
			$device = +$_POST['device'];
			if ($id && $device) {
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
			} else {
				$json = ['error' => 'Fehlerhafte/Fehlende Formularinformationen erhalten'];
			}
			echo json_encode($json);
			break;
		case 'price':
			$id = +$_POST['akkuNr'];
			
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
			echo json_encode($json);
			break;
		case 'return':
			$id = +$_POST['akkuNr'];
			$cable = +$_POST['cable'];
			$adapter = +$_POST['adapter'];
			$toReceived = +$_POST['time'];
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
						if (($lend['device'] == '1' || $lend['device'] == '2') && !$cable) $price += 2;
						if ($lend['device'] == '2' && !$adapter) $price += 1.5;
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
						$myDataChanged = true;
						$found = true;
						break;
					}
				}
				if ($found) {
					$json['success'] = true;
				} else {
					$json = ['error' => $error];
				}
			} else {
				$json = ['error' => 'AkkuNr konnte nicht ermittelt werden'];
			}
			echo json_encode($json);
			break;
	}
	if ($myDataChanged) file_put_contents('data/'.$user.'.json', json_encode($myData, JSON_PRETTY_PRINT));
	exit;
}
if ($myDataChanged) file_put_contents('data/'.$user.'.json', json_encode($myData, JSON_PRETTY_PRINT));
?>
<!doctype html>
<html class="no-js" lang="de">
<head>
	<meta charset="utf-8">
	<meta http-equiv="x-ua-compatible" content="ie=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title>battyboost ShopManager</title>
	<meta name="description" content="Ausgaben/Rücknahmen von Powerbanks verwalten">

	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-pink.min.css">
	<script defer src="https://code.getmdl.io/1.3.0/material.min.js"></script>
	<link rel="stylesheet" type="text/css" href="dialog-polyfill.css" />
	<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>

	<style type="text/css">
		.fab-fixed {
			position: fixed;
			bottom: 20px;
			right: 20px;
		}

		.full-width {
			width: 100%;
		}
		.scrollH {
			width: 100%;
			max-width: 100%;
			overflow-x: scroll;
		}
		.mdl-dialog__content h4 {
			margin: 0;
		}
		.mdl-data-table td, .mdl-data-table th {
			padding-left: 14px;
			padding-right: 14px;
			text-align: left;
		}
		.mdl-data-table td:first-of-type, .mdl-data-table th:first-of-type {
			padding-left: 20px;
		}
		.mdl-data-table td:last-of-type, .mdl-data-table th:last-of-type {
			padding-right: 20px;
		}
		table {
			border-right: none;
			border-left: none;
		}
		.center {
			text-align: center !important;
		}
		.available {
			color: green;
		}
		.available.mdl-radio.is-checked .mdl-radio__outer-circle {
			border-color: green;
		}
		.available .mdl-radio__inner-circle, .available .mdl-radio__ripple-container .mdl-ripple {
			background-color: green;
		}
		.missing {
			color: red;
		}
		.missing.mdl-radio.is-checked .mdl-radio__outer-circle {
			border-color: red;
		}
		.missing .mdl-radio__inner-circle, .missing .mdl-radio__ripple-container .mdl-ripple {
			background-color: red;
		}
		.gap {
			display: inline-block;
			width: 30px;
		}
	</style>
</head>
<body>
	<!-- Simple header with scrollable tabs. -->
	<div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
		<header class="mdl-layout__header">
			<div class="mdl-layout__header-row">
				<!-- Title -->
				<span class="mdl-layout-title">battyboost ShopManager</span>
			</div>
			<!-- Tabs -->
			<div class="mdl-layout__tab-bar mdl-js-ripple-effect">
				<a href="#scroll-tab-1" class="mdl-layout__tab is-active">aktuell</a>
				<a href="#scroll-tab-2" class="mdl-layout__tab">Historie</a>
			</div>
		</header>
		<div class="mdl-layout__drawer">
		<span class="mdl-layout-title">Hallo, <?php echo $_GET["user"]; ?></span>
			<nav class="mdl-navigation">
				<a class="mdl-navigation__link"><b>battyboost kontaktieren:</b></a>
				<a class="mdl-navigation__link" href="tel:+491733774726">Anrufen</a>
				<a class="mdl-navigation__link" href="mailto:orgis@battyboost.com">E-Mail</a>
				<a class="mdl-navigation__link" href="https://battyboost.com">Website</a>
			</nav>
		</div>
		<main class="mdl-layout__content">
			<section class="mdl-layout__tab-panel is-active" id="scroll-tab-1">
				<div class="page-content">
					<!-- Colored FAB button with ripple -->
					<button class="mdl-button mdl-js-button mdl-button--fab mdl-js-ripple-effect mdl-button--colored fab-fixed" id="show-add">
						<i class="material-icons">add</i>
					</button>
					<div class="scrollH">
						<table class="mdl-data-table mdl-js-data-table mdl-shadow--2dp full-width" id="lend-table">
							<thead>
								<tr>
									<th>Akku Nr.</th>
									<th>Ausgabe</th>
									<th>Kabel</th>
									<th class="center">Rückgabe</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>

					<script src="dialog-polyfill.js"></script>

				</div>
			</section>
			<section class="mdl-layout__tab-panel" id="scroll-tab-2">
				<div class="page-content">
					<div class="scrollH">
						<table class="mdl-data-table mdl-js-data-table mdl-shadow--2dp full-width" id="history-table">
							<thead>
								<tr>
									<th>Akku Nr.</th>
									<th>Ausgabe</th>
									<th>Rückgabe</th>
									<th>Mietpreis</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</section>
		</main>
	</div>

	<dialog class="mdl-dialog dialog-add" id="dialog-add">
		<h4 class="mdl-dialog__title">
			Neue Ausgabe
			<small class="current-time"></small>
		</h4>

		<div class="mdl-dialog__content">
			<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
				<input class="mdl-textfield__input" type="text" pattern="\d*" id="akkunr">
				<label class="mdl-textfield__label" for="akkunr">Akku Nr.</label>
				<span class="mdl-textfield__error">Eingabe ist keine Zahl!</span>
			</div>
			<span>Welches Zubehör wird ausgegeben?</span>
			<p>
				<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect">
					<input type="radio" id="option-1" class="mdl-radio__button" name="device" value="1" checked>
					<span class="mdl-radio__label">nur Kabel</span>
				</label>
			</p>
			<p>
				<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect">
					<input type="radio" id="option-2" class="mdl-radio__button" name="device" value="2">
					<span class="mdl-radio__label">Kabel + iOS-Adapter</span>
				</label>
			</p>
			<p>
				<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect">
					<input type="radio" id="option-2" class="mdl-radio__button" name="device" value="4">
					<span class="mdl-radio__label">Kabel + USB-C-Adapter</span>
				</label>
			</p>
			<p>
				<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect">
					<input type="radio" id="option-3" class="mdl-radio__button" name="device" value="3">
					<span class="mdl-radio__label">kein Kabel, kein Adapter</span>
				</label>
			</p>

			<div class="info-text">Bitte iOS-Adapter an Kunden ausgeben.</div>
		</div>
		<div class="mdl-dialog__actions">
			<input type="submit" class="mdl-button submit mdl-button--colored mdl-js-button mdl-button--raised" value="Buchen">
			<input type="button" class="mdl-button close" value="Abbrechen">
		</div>
	</dialog>

	<dialog class="mdl-dialog dialog-giveback" id="dialog-giveback">
		<h4 class="mdl-dialog__title">Rückgabe</h4>
		<div class="mdl-dialog__content">

			<div id="cable-wrapper">
				<div>Kabel</div>
				<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect available">
					<input type="radio" class="mdl-radio__button" name="kabel-options" value="1">
					<span class="mdl-radio__label">vorhanden</span>
				</label><div class="gap"></div>
				<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect missing">
					<input type="radio" class="mdl-radio__button" name="kabel-options" value="0">
					<span class="mdl-radio__label">fehlt</span>
				</label>
				<br> 
			</div>

			<div id="adapter-wrapper">
				<div>iOS-Adapter</div>
				<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect available">
					<input type="radio" class="mdl-radio__button" name="iOS-options" value="1">
					<span class="mdl-radio__label">vorhanden</span>
				</label><div class="gap"></div>
				<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect missing">
					<input type="radio" class="mdl-radio__button" name="iOS-options" value="0">
					<span class="mdl-radio__label">fehlt</span>
				</label>
				<br> 
			</div>

			<div id="adapter-wrapper">
				<div>USB-C-Adapter</div>
				<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect available">
					<input type="radio" class="mdl-radio__button" name="USBC-options" value="1">
					<span class="mdl-radio__label">vorhanden</span>
				</label><div class="gap"></div>
				<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect missing">
					<input type="radio" class="mdl-radio__button" name="USBC-options" value="0">
					<span class="mdl-radio__label">fehlt</span>
				</label>
				<br> 
			</div>

			<span>Nutzer erhält:<br></span>
			<h4><span class="price">-</span> €<br></h4>
		</div>
		<div class="mdl-dialog__actions">
			<input type="submit" class="mdl-button submit mdl-button--colored mdl-js-button mdl-button--raised" value="Rückgabe">
			<input type="button" class="mdl-button close" value="Abbrechen">
		</div>
	</dialog>
	
	<script>
		var $lendTable = $('#lend-table'),
			$historyTable = $('#history-table'),
			$dialogs = $('.mdl-dialog'),
			lend = <?= json_encode($myData['lend']) ?>,
			hist = <?= json_encode($myData['history']) ?>,
			returnTo = false;
		$dialogs.each(function() {
			dialogPolyfill.registerDialog(this);
			var self = this;
			$(this).find('.close').on('click', function() {
				if ($(self).is('.dialog-giveback')) {
					if (returnTo) clearTimeout(returnTo);
					returnTo = false;
				}
				self.close();
			});
		});
		
		function initReturn() {
			$('.return').each(function() {
				if ($(this).data('init')) return;
				$(this).on('click', function() {
					var id = +$(this).attr('data-id');
					
					$.post(location.pathname+location.search, {
						ajax: 'price',
						akkuNr: id
					}, function(data) {
						if (data) {
							if (data.success) {
								var $d = $dialogs.filter('.dialog-giveback'),
									time = data.time,
									price = 12 - data.price,
									$price = $d.find('.price'),
									$cable = $('[name="kabel-options"]').prop('checked', false).off('.recalculate'),
									$adapter = $('[name="adapter-options"]').prop('checked', false).off('.recalculate'),
									device = ''+data.device,
									check = [],
									checkElements = {
										cable: $cable,
										adapter: $adapter
									},
									$submit = $d.find('.submit');
								switch (device) {
									case '1':
										$('#cable-wrapper').show();
										$('#adapter-wrapper').hide();
										check.push('cable');
										break;
									case '2':
										$('#cable-wrapper').show();
										$('#adapter-wrapper').show();
										check.push('cable');
										check.push('adapter');
										break;
									default:
										$('#cable-wrapper').hide();
										$('#adapter-wrapper').hide();
								}
								$cable.add($adapter).parent().each(function() {
									this.MaterialRadio.checkToggleState();
								});
								$cable.add($adapter).on('change.recalculate', function() {
									var disabled = false;
									$.each(check, function(k, v) {
										if (!checkElements[v].filter(':checked').length) disabled = true;
									});
									$submit.prop('disabled', disabled);
									var cable = $cable.filter(':checked').val() == '1' ? 0 : 2,
										adapter = $adapter.filter(':checked').val() == '1' ? 0 : 1.5;
									if (device == '1') adapter = 0;
									else if (device != '2') {
										adapter = 0;
										cable = 0;
									}
									var p = Math.ceil((price - cable - adapter) * 100) / 100;
									if (p < 0) p = 0;
									$price.text(disabled ? '-' : p.toFixed(2).replace('.', ','));
								}).trigger('change');
								$submit.prop('disabled', !!check.length);
								
								$submit.off('click').on('click', function() {
									$(this).prop('disabled', true);
									$.post(location.pathname+location.search, {
										ajax: 'return',
										akkuNr: id,
										time: time,
										cable: $cable.filter(':checked').val(),
										adapter: $adapter.filter(':checked').val()
									}, function(data) {
										if (data) {
											if (data.success) {
												$('tr[data-id="'+id+'"]').remove();
												historyAdd(data);
											} else if (data.error) {
												alert('Es ist ein Fehler aufgetreten: '+data.error);
											}
										}
										if (returnTo) clearTimeout(returnTo);
										returnTo = false;
										$d[0].close();
									});
									return false;
								});
								$d[0].showModal();
								
								returnTo = setTimeout(function() {
									$d[0].close();
								}, 1000 * 600);
							}
						}
					});
				});
				$(this).data('init', true);
			});
		}
		
		function add(opt) {
			var $tr = $('<tr data-id="'+opt.id+'" />');
			var ausgabe = opt.date+"<br>"+opt.time;
			var kabel = "";
			switch(opt.device) {
				case 1:
					kabel = "Android";
					break;
				case 2:
					kabel = "iPhone";
					break;
				case 3:
					kabel = "keins";
			}
			$tr.append($('<td />').text(opt.id));
			$tr.append($('<td />').html(ausgabe));
			$tr.append($('<td />').text(kabel));
			$tr.append($('<td class="center" />').append($('<a data-id="'+opt.id+'" class="mdl-button mdl-js-button mdl-button--fab mdl-button--mini-fab mdl-button--colored return" />').html('<i class="material-icons">undo</i>')));
			$lendTable.find('tbody').append($tr);
			initReturn();
		}
		
		function historyAdd(opt) {
			var $tr = $('<tr data-history-id="'+opt.id+'" />');
			var ausgabe = opt.date+"<br>"+opt.time;
			var rueckgabe = opt.rdate+"<br>"+opt.rtime;
			var extra = '';
			switch (opt.device) {
				case 1:
					if (opt.cable == 'no') extra = 'fehlt: K';
					break;
				case 2:
					if (opt.cable == 'no' && opt.adapter == 'no') extra = 'fehlt: K+A';
					else if (opt.cable == 'no') extra = 'fehlt: K';
					else if (opt.adapter == 'no') extra = 'fehlt: A';
					break;
			}
			$tr.append($('<td />').text(opt.id));
			$tr.append($('<td />').html(ausgabe));
			$tr.append($('<td />').html(rueckgabe));
			$tr.append($('<td />').html(opt.price+' €'+(extra ? "<br><small>"+extra+'</small>' : '')));
			$historyTable.find('tbody').prepend($tr);
		}
		
		$('#show-add').on('click', function() {
			var $d = $dialogs.filter('.dialog-add'),
				date = new Date(),
				$devices = $d.find('[name="device"]');
			$d.find('#akkunr').val('').focus();
			$('#option-1').prop('checked', true);
			$('[name="device"]').parent().each(function() {
				this.MaterialRadio.checkToggleState();
			});
			$d.find('.info-text').hide();
			$devices.on('change', function() {
				if ($devices.filter(':checked').val() == '2') {
					$d.find('.info-text').stop().slideDown();
				} else {
					$d.find('.info-text').stop().slideUp();
				}
			});
			$d.find('.current-time').text(date.getDate()+'.'+(date.getMonth() < 9 ? '0' : '')+(date.getMonth() + 1)+'.'+(''+date.getFullYear()).substr(2)+', '+date.getHours()+':'+(date.getMinutes() < 10 ? '0' : '')+date.getMinutes());
			$d.find('.submit').prop('disabled', false).off('click').on('click', function() {
				var akkuNr = +$d.find('#akkunr').val(),
					device = +$d.find('[name="device"]:checked').val();
				if (akkuNr && device) {
					$(this).prop('disabled', true);
					$.post(location.pathname+location.search, {
						ajax: 'add',
						akkuNr: akkuNr,
						device: device
					}, function(data) {
						if (data) {
							if (data.success) {
								add(data);
							} else if (data.error) {
								alert('Es ist ein Fehler aufgetreten: '+data.error);
							}
						}
						$d[0].close();
					});
				}
				return false;
			});
			$d[0].showModal();
		});
		
		if (lend) {
			for (var x in lend) add(lend[x]);
		}
		
		if (hist) {
			for (var x in hist) historyAdd(hist[x]);
		}
		
		initReturn();
	</script>
	</body>
</html>
