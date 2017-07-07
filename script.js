var $lendTable = $('#lend-table'),
	$historyTable = $('#history-table'),
	$dialogs = $('.mdl-dialog'),
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
							price = 9 - data.price,
							$price = $d.find('.price'),
							$cable = $('[name="kabel-options"]'),
							$adapterIos = $('[name="ios-options"]'),
							$adapterUsbc = $('[name="usbc-options"]'),
							$adapter = $adapterIos.add($adapterUsbc),
							device = ''+data.device,
							check = [],
							checkElements = {
								cable: $cable,
								adapterIos: $adapterIos,
								adapterUsbc: $adapterUsbc
							},
							$submit = $d.find('.submit'),
							$accessories = $cable.add($adapterIos).add($adapterUsbc);
						$d.find('.accessories').hide();
						
						if (device > 0) {
							$('#cable-wrapper').show();
							check.push('cable');
						}
						if (device == 2) {
							$('#adapter-ios-wrapper').show();
							check.push('adapterIos');
						}
						if (device == 3) {
							$('#adapter-usbc-wrapper').show();
							check.push('adapterUsbc');
						}
						$accessories.prop('checked', false).off('.recalculate').on('change.recalculate', function() {
							var disabled = false;
							$.each(check, function(k, v) {
								if (!checkElements[v].filter(':checked').length) disabled = true;
							});
							$submit.prop('disabled', disabled);
							var cable = $cable.filter(':checked').val() == '1' ? 0 : 2,
								adapter = $adapter.filter(':checked').val() == '1' ? 0 : 1.5;
							if (device < 2) adapter = 0;
							else if (!device) {
								adapter = 0;
								cable = 0;
							}
							var p = Math.ceil((price - cable - adapter) * 100) / 100;
							if (p < 0) p = 0;
							$price.text(disabled ? '-' : p.toFixed(2).replace('.', ','));
						}).trigger('change').parent().each(function() {
							this.MaterialRadio.checkToggleState();
						});
						
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
			kabel = "Kabel";
			break;
		case 2:
			kabel = "Kabel +\niOS Ad.";
			break;
		case 3:
			kabel = "Kabel +\nUSB-C Ad.";
			break;
		case 0:
			kabel = "Nein";
	}
	$tr.append($('<td />').text(opt.id));
	$tr.append($('<td />').html(ausgabe));
	$tr.append($('<td />').html(kabel.replace(/\n/g, '<br>')));
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
		$devices = $d.find('[name="device"]'),
		$submit = $d.find('.submit');
	$d.find('#akkunr').val('').focus();
	$devices.prop('checked', false).off('change').on('change', function() {
		if ($(this).is(':checked')) $submit.prop('disabled', false);
	}).parent().each(function() {
		this.MaterialRadio.checkToggleState();
	});
	$d.find('.info-text').hide();
	$d.find('.current-time').text(date.getDate()+'.'+(date.getMonth() < 9 ? '0' : '')+(date.getMonth() + 1)+'.'+(''+date.getFullYear()).substr(2)+', '+date.getHours()+':'+(date.getMinutes() < 10 ? '0' : '')+date.getMinutes());
	$submit.prop('disabled', true).off('click').on('click', function() {
		var akkuNr = +$d.find('#akkunr').val(),
			$deviceChecked = $d.find('[name="device"]:checked'),
			device = +$deviceChecked.val();
		if (akkuNr && $deviceChecked.length) {
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
