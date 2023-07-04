<?php
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @global CUserTypeManager $USER_FIELD_MANAGER */

/** @var  string $REQUEST_METHOD */

use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Gpart\Local\Common\Sync\CatalogSync;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

$module_id = 'gpart.local';

$POST_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($POST_RIGHT === 'D') {
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}

if (!Loader::includeModule($module_id)) {
    $message = new CAdminMessage(GetMessage("post_save_error"), 'Error include module');
}

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

if ($request->isPost()) {

    $stepRequest = (int)$request->get('step');

    $resultData = [
        'status' => 'error'
    ];

    if ($POST_RIGHT === "W" && check_bitrix_sessid()) {
        if (!$stepRequest) {
            $resultData['message'] = 'Empty step';
        } else {
            switch ($stepRequest) {
                case 1:
                    $tFileData = $request->getFile('data');
                    if (!$tFileData) {
                        $resultData['message'] = 'Empty file';
                    } else {
                        //сохраним файл на хостинге
                        $fileSrcSave = \Bitrix\Main\Application::getDocumentRoot() . '/upload/tmp/';
                        if (!is_dir($fileSrcSave) && !mkdir($fileSrcSave) && !is_dir($fileSrcSave)) {
                            throw new \RuntimeException(sprintf('Directory "%s" was not created', $fileSrcSave));
                        }
                        $result = '';
                        $name = basename($tFileData['name']);
                        $lastPos = strrpos($name, '.');
                        $fileExtension = substr($name, $lastPos);
                        $name = substr($name, 0, $lastPos);

                        $fileName = Cutil::translit($name, "ru", ['max_len' => 30, "replace_space" => "-", "replace_other" => "-"]) . $fileExtension;

                        $fileSrcSave .= $fileName;


                        if (is_file($fileSrcSave) && filesize($fileSrcSave) === $tFileData['size'] && md5_file($tFileData['tmp_name']) === md5_file($fileSrcSave)) {
                            $result = "Файл уже был загружен ранее\n";
                        } elseif (move_uploaded_file($tFileData['tmp_name'], $fileSrcSave)) {
                            $result = "Файл корректен и был успешно загружен.\n";
                        } else {
                            throw new \RuntimeException('Возможная атака с помощью файловой загрузки!');
                        }

                        if ($result && $fileSrcSave) {
                            $resultData['status'] = 'success';
                            $resultData['message'] = $result;
                            $resultData['data'] = [
                                'fileUrlFull' => $fileSrcSave,
                                'fileUrl' => str_replace(\Bitrix\Main\Application::getDocumentRoot(), '', $fileSrcSave)
                            ];
                        }
                    }

                    break;

                case 2:
                    $fileSrcSave = $request->get('fileUrlFull');
                    if (!$fileSrcSave) {
                        $resultData['message'] = 'Empty $fileSrcSave';
                    } elseif (Loader::includeModule('gpart.local')) {
                        $obj = new CatalogSync($fileSrcSave);
                        $sheetsInfo = $obj->stepGetSheetInfo();

//                        $firsList[] = current($sheetsInfo);
                        if ($sheetsInfo) {
                            $resultData['data'] = $sheetsInfo;
                            $resultData['status'] = 'success';
                        } else {
                            $resultData['message'] = 'Ошибка чтения файла';
                        }
                    } else {
                        $resultData['message'] = 'Error includeModule gpart.local';
                    }
                    break;

                case 3:
                    $fileUrlFull = $request->get('fileUrlFull');
                    $worksheetName = $request->get('worksheetName');
                    $totalRows = $request->get('totalRows');
                    if (!$fileUrlFull) {
                        $resultData['message'] = 'Empty $fileSrcSave';
                    } elseif (Loader::includeModule('gpart.local')) {

                        $obj = new CatalogSync($fileUrlFull);

                        $result = $obj->stepReadAndWrite($worksheetName, $totalRows);
                        if ($result) {
                            $resultData['data'] = $result;
                            $resultData['status'] = 'success';
                        } else {
                            $resultData['message'] = 'Ошибка чтения файла';
                        }
                    } else {
                        $resultData['message'] = 'Error includeModule gpart.local';
                    }

                    break;

                case 4:

                    $fileUrlFull = $request->get('fileUrlFull');

                    if (!$fileUrlFull) {
                        $resultData['message'] = 'Empty $fileSrcSave';
                    } else {
                        unlink($fileUrlFull);
                    }
                    break;
            }
        }
    } else {
        $resultData['message'] = 'Нет доступа';
    }

    if ($request->isAjaxRequest()) {
        $APPLICATION->RestartBuffer();
    }

    $resultData['$stepRequest'] = $stepRequest;
    $resultData['sessid'] = bitrix_sessid();

    if ($request->isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode($resultData);
        CMain::FinalActions();
        die();
    }
}

$tabControl = new CAdminTabControl("tabControl", [
    [
        "DIV" => "edit1",
        "TAB" => 'Ипорт продукции', "ICON" => "main_user_edit",
        "TITLE" => 'Импорт данных из файла'
    ]
]);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

$context = new CAdminContextMenu([]);
$context->Show();

if (is_array($_SESSION["SESS_ADMIN"]["POSTING_EDIT_MESSAGE"])) {
    CAdminMessage::ShowMessage($_SESSION["SESS_ADMIN"]["POSTING_EDIT_MESSAGE"]);
    $_SESSION["SESS_ADMIN"]["POSTING_EDIT_MESSAGE"] = false;
}
Asset::getInstance()->addCss('/bitrix/css/main/bootstrap.min.css');

if ($message)
    echo $message->Show();

?>
    <form id="form_sync_catalog" method="POST" action="<? echo $APPLICATION->GetCurPage() ?>"
          ENCTYPE="multipart/form-data" name="sync_catalog">
        <?php
        $tabControl->Begin();
        ?>
        <?
        //********************
        //Posting issue
        //********************
        $tabControl->BeginNextTab();
        ?>

        <div class="container">
            <div>
                <label for="fileForm">Файл</label>
                <input id="fileForm" name="data" type="file" required class="form-control-file">
            </div>
        </div>
        <div class="adm-detail-content-btns">
            <input type="submit" class="js-btn-send adm-btn-save" value="Старт"/>
        </div>
        <br>
        <div>
            <p>Лог:</p>
            <textarea style="width: 100%; min-height: 350px;" id="log" cols="30" rows="10"></textarea>
        </div>
        <?php

        $tabControl->End();
        ?>
        <? echo bitrix_sessid_post(); ?>
        <input type="hidden" name="lang" value="<?= LANG ?>">
    </form>

    <script>
		if (!$) {
			alert('Нет jQuery работа не возможна');
		} else {

			try {

				let step = 1;
				let urlAction = '';
				let fileUrl = '';
				let fileUrlFull = '';
				let delFile = false;
				let $btn;
				let sheetsInfo = '';
				disabledBtn = function ($btn) {
					$btn.prop('disabled', true)
				}
				enabledBnt = function ($btn) {
					$btn.prop('disabled', false)
				}
				const $form = $('#form_sync_catalog');
				const $logItem = $('#log');
				$form.on('submit', function (e) {
					e.preventDefault();
					e.stopImmediatePropagation();
					const form = this;
					$btn = $form.find('input[type="submit"]');
					disabledBtn($btn);
					urlAction = $form.attr('action');
					runStep(1, form);
				});


				runStep = function (step, option) {
					switch (step) {
						case 1:
							step1UploadFile(step, option);
							break;
						case 2:
							step2GetInfoFile(step, option);
							break;
						case 3:
							step3SyncCatalog(step, option);
							break;
						case 4:
							step4DelFile(step, option);
							break;
					}
				}
				addLogMessage = function (message) {
					$logItem.append(message + '\n');
				}
				addErrorAjaxResponse = function (response) {
					addLogMessage('Ошибка, не корректный ответ сервера.' + (response && response.message ? ' ' + response.message : ''));
				}
				step1UploadFile = function (step, form) {
					$logItem.text('');

					addLogMessage('Запуск\n\n');

					addLogMessage('Шаг 1. Загрузка файла данных');

					sheetsInfo = {};

					let fd = new FormData(form)
					fd.append('step', step);

					$.ajax({
						url: urlAction,
						type: "POST",
						data: fd,
						contentType: false,
						cache: false,
						processData: false
					}).done(function (response) {
						if (response && response.status === 'success') {
							let fileUrl = response.data.fileUrl;
							fileUrlFull = response.data.fileUrlFull;
							if (!fileUrl) {
								addLogMessage('Ошибочный ответ сервера, нет ссылки на файл\n');

							} else {
								addLogMessage('файл успешно загружен\n');
								runStep(2, response)
							}
						} else {
							addErrorAjaxResponse(response);
						}
					})
				}

				step2GetInfoFile = function (step, response) {
					addLogMessage('');
					addLogMessage('Шаг 2. Получение информации о файле');
					let data = response.data;
					data.sessid = response.sessid;
					data.step = step;
					$.ajax({
						url: urlAction,
						type: "POST",
						data: data,
					}).done(function (response) {
						if (response && response.status === 'success') {
							sheetsInfo = response.data;

							if (!sheetsInfo) {
								addLogMessage('Ошибочный ответ сервера, нет информации о листах\n');
							} else {

								addLogMessage('информация получена. Листов ' + sheetsInfo.length);
								runStep(3, response)
							}
						} else {
							addErrorAjaxResponse(response);
						}
					})
				}

				step3SyncCatalog = function (step, response) {
					addLogMessage('');
					addLogMessage('Шаг 3. считываем информацию из листов, по очереди.\n');
					if (response && response.data) {
						sheetsInfo = response.data;
						let n = sheetsInfo.length, i = 0, sessid = response.sessid;
						let result = false;

						function syncSheet(worksheetName, totalRows) {

							addLogMessage('Читаем ' + (i + 1) + ' лист "' + worksheetName + '" в котором ' + totalRows + ' элементов');

							let data = {
								fileUrlFull: fileUrlFull,
								worksheetName: worksheetName,
								totalRows: totalRows,
								sessid: sessid,
								step: 3
							}
							$.ajax({
								url: urlAction,
								timeout: 40000,
								type: "POST",
								data: data,
							}).done(function (response) {
								if (response && response.status === 'success') {
									if (response.sessid) {
										sessid = response.sessid;
									}
									result = true;
									addLogMessage('успешно.' + (response.data ? JSON.stringify(response.data) : ''))
								} else {
									addLogMessage('ошибка.' + (response && response.message) ? ' ' + response.message : '')
								}

								i++;
								if (i < n) {
									resutlSend = syncSheet(sheetsInfo[i]['worksheetName'], sheetsInfo[i]['totalRows']);
								} else {
									step(4, {sessid: sessid, fileUrlFull: fileUrlFull});
								}
							} )

							return result;
						}

						let resutlSend = syncSheet(sheetsInfo[i]['worksheetName'], sheetsInfo[i]['totalRows']);
						console.log(resutlSend)
					} else {
						addErrorAjaxResponse(response);
					}
				}

				step4DelFile = function (step, responce) {
					addLogMessage('');
					addLogMessage('Шаг 4. Удаляем временный файл.\n');

					let data = {
						fileUrlFull: responce.fileUrlFull,
						sessid: responce.sessid,
						step: step
					}

					$.ajax({
						url: urlAction,
						type: "POST",
						data: data,
					}).done(function (response) {
						if (response && response.status === 'success') {
							result = true;
							addLogMessage('успешно.' + (response && response.message) ? ' ' + response.message : '')
						} else {
							addLogMessage('ошибка.' + (response && response.message) ? ' ' + response.message : '')
						}

					})

					enabledBnt($btn);

				}
			} catch
				(e) {
				console.log(e);
				enabledBnt($btn);
			}
		}
    </script>
<?
$tabControl->ShowWarnings("post_form", $message);
?>

<? echo BeginNote(); ?>
    <span class="required"><sup>1</sup></span>Notes 1<br>
    <br>
    <span class="required"><sup>2</sup></span>Notes 2<br>
<? echo EndNote(); ?>


<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php"); ?>