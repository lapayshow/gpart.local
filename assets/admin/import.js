let countProgressItems = 0;
let progressInit = false;
var myProgress = new BX.UI.ProgressBar({
	statusType: BX.UI.ProgressBar.Status.COUNTER,
	fill: true,
	column: true
});
myProgress.setColor(BX.UI.ProgressBar.Color.PRIMARY);
myProgress.setMaxValue(countProgressItems);
myProgress.setValue(0);
myProgress.setTextBefore('');

var myAlert = new BX.UI.Alert({
	text: "",
	inline: true
});

window.importDealers = (target, optionName, startIndex) => {
	if (!target) return;
	const $target = $(target);
	const $table = $target.closest('.adm-detail-content-table');

	const disableForm = () => {
		$table.css({
			opacity: .2,
			pointerEvents: 'none'
		});
		BX.closeWait();
		BX.showWait();
	};
	const enableForm = () => {
		$table.css({
			opacity: 1,
			pointerEvents: 'auto'
		});
		BX.closeWait();
	};

	disableForm();

	const prepareData = () => {
		const $option = $table.find(`input[name="${optionName}"]`);
		if (!$option || !$option.length) return;

		const filePath = $option.val();
		if (!filePath) return;

		return {
			filePath: filePath,
			startIndex: startIndex || ''
		};
	};

	const data = prepareData();


	if (progressInit !== true) {
		$table.before(myProgress.getContainer());
		$table.before(myAlert.getContainer());
		progressInit = true;
	}
	if (typeof startIndex === 'undefined') {
		myProgress.setMaxValue(0);
		countProgressItems = 0;
		myProgress.update(0);
		myProgress.setColor(BX.UI.ProgressBar.Color.WARNING);
		myAlert.setColor(BX.UI.Alert.Color.WARNING);
		//myProgress.setTextAfter('');

		myAlert.setText('Обработка файла');
	}

	if (!data) {
		myProgress.setColor(BX.UI.ProgressBar.Color.DANGER);
		myAlert.setColor(BX.UI.Alert.Color.DANGER);
		myAlert.setText('Некорректный путь к файлу');

		enableForm();
		return false;
	}

	const request = BX.ajax.runAction('gpart:local.import.importController.importDealers', {
		data: data,
	});

	request
		.then((response) => {
			response = response || {};
			let data = response.data || {};
			data.nextIndex = data.nextIndex || false;

			if (data.success) {
				console.log(
					data.stats || 'Импортировано успешно'
				);

				if (data.progress) {
					myProgress.setColor(BX.UI.ProgressBar.Color.PRIMARY);
					myAlert.setColor(BX.UI.Alert.Color.PRIMARY);
					if (data.progress.all && data.progress.all != countProgressItems) {
						countProgressItems = data.progress.all;
						myProgress.setMaxValue(countProgressItems);
					}
					if (data.progress.end) {
						myProgress.update(data.progress.end);
					}
				}
				myAlert.setText(data.stats ? data.stats.replaceAll('\n', '<br>') : '');

			} else {
				console.error(data.error || 'Импортировано с ошибками');
				myProgress.setColor(BX.UI.ProgressBar.Color.DANGER);
				myAlert.setColor(BX.UI.Alert.Color.DANGER);
				myAlert.setText(data.error || 'Импортировано с ошибками');
			}

			//myProgress.setTextAfter();

			if (data.nextIndex) {
				importDealers(target, optionName, data.nextIndex);
			} else {
				enableForm();
				if (data.success) {
					myProgress.setColor(BX.UI.ProgressBar.Color.SUCCESS);
					myAlert.setColor(BX.UI.Alert.Color.SUCCESS);
				}
			}
		})
		.catch((response) => {
			const errorText = response && response.message || 'Ошибка импорта. Попробуйте позже.'
			alert(errorText);
			if (progressInit) {
				myProgress.setColor(BX.UI.ProgressBar.Color.ERROR);
				myAlert.setColor(BX.UI.ProgressBar.Color.ERROR);
				myAlert.setText(errorText);
			}
			enableForm();
		});
};