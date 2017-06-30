<?php
$user = isset($_GET['user']) ? $_GET['user'] : false;
define('USER', $user);

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

$deposit = 12;

require_once 'functions.inc.php';

$myData = loadData();

cronMove2History($myData);

### AJAX
if (isset($_POST['ajax'])) {
	header('Content-Type: application/json');
	switch ($_POST['ajax']) {
		case 'add':
			$id = +$_POST['akkuNr'];
			$device = +$_POST['device'];
			$json = addLend($myData, $id, $device);
			echo json_encode($json);
			break;
		case 'price':
			$id = +$_POST['akkuNr'];
			$json = calculatePrice($myData, $id);
			echo json_encode($json);
			break;
		case 'return':
			$id = +$_POST['akkuNr'];
			$cable = +$_POST['cable'];
			$adapter = +$_POST['adapter'];
			$toReceived = +$_POST['time'];
			$json = returned($myData, $id, $cable, $adapter, $toReceived);
			echo json_encode($json);
			break;
	}
	exit;
}
?>
<!doctype html>
<html class="no-js" lang="de">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="x-ua-compatible" content="ie=edge">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<link rel="apple-touch-icon" href="icon.png">
		<link rel="icon" type="image/png" href="icon.png">
		<meta name="msapplication-TileColor" content="#33CC66">
		<meta name="theme-color" content="#33CC66">
		<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#33CC66">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">

		<title>battyboost ShopManager</title>
		<meta name="description" content="Ausgaben/Rücknahmen von Powerbanks verwalten">

		<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
		<link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-pink.min.css">
		<script defer src="https://code.getmdl.io/1.3.0/material.min.js"></script>
		<link rel="stylesheet" type="text/css" href="dialog-polyfill.css" />
		<link rel="stylesheet" type="text/css" href="style.css" />
		<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
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
										<th>Zubehör</th>
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
					<input class="mdl-textfield__input" type="number" pattern="\d*" id="akkunr">
					<label class="mdl-textfield__label" for="akkunr">Akku Nr.</label>
					<span class="mdl-textfield__error">Eingabe ist keine Zahl!</span>
				</div>
				<p>
					<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect">
						<input type="radio" class="mdl-radio__button" name="device" value="1">
						<span class="mdl-radio__label">nur <b>Kabel</b></span>
					</label>
				</p>
				<p>
					<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect">
						<input type="radio" class="mdl-radio__button" name="device" value="2">
						<span class="mdl-radio__label">Kabel + <b>iOS</b> Adapter</span>
					</label>
				</p>
				<p>
					<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect">
						<input type="radio" class="mdl-radio__button" name="device" value="3">
						<span class="mdl-radio__label">Kabel + <b>USB-C</b> Adapter</span>
					</label>
				</p>
				<p>
					<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect">
						<input type="radio" class="mdl-radio__button" name="device" value="0">
						<span class="mdl-radio__label"><b>kein</b> Zubehör</span>
					</label>
				</p>
			</div>
			<div class="mdl-dialog__actions">
				<input type="submit" class="mdl-button submit mdl-button--colored mdl-js-button mdl-button--raised" value="Buchen">
				<input type="button" class="mdl-button close" value="Abbrechen">
			</div>
		</dialog>

		<dialog class="mdl-dialog dialog-giveback" id="dialog-giveback">
			<h4 class="mdl-dialog__title">Rückgabe</h4>
			<div class="mdl-dialog__content">

				<div id="cable-wrapper" class="accessories">
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

				<div id="adapter-ios-wrapper" class="accessories">
					<div>iOS-Adapter</div>
					<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect available">
						<input type="radio" class="mdl-radio__button" name="ios-options" value="1">
						<span class="mdl-radio__label">vorhanden</span>
					</label><div class="gap"></div>
					<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect missing">
						<input type="radio" class="mdl-radio__button" name="ios-options" value="0">
						<span class="mdl-radio__label">fehlt</span>
					</label>
					<br> 
				</div>

				<div id="adapter-usbc-wrapper" class="accessories">
					<div>USB-C-Adapter</div>
					<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect available">
						<input type="radio" class="mdl-radio__button" name="usbc-options" value="1">
						<span class="mdl-radio__label">vorhanden</span>
					</label><div class="gap"></div>
					<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect missing">
						<input type="radio" class="mdl-radio__button" name="usbc-options" value="0">
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
		var lend = <?= json_encode($myData['lend']) ?>,
			hist = <?= json_encode($myData['history']) ?>;
		</script>
		<script src="script.js"></script>
	</body>
</html>
