<?php
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @global CUserTypeManager $USER_FIELD_MANAGER */

/** @var  string $REQUEST_METHOD */

use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Gpart\Local\Common\Sync\CatalogSync;

$module_id = 'gpart.local';
define("ADMIN_MODULE_NAME", $module_id);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

$POST_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($POST_RIGHT === 'D') {
	$APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}

if (!Loader::includeModule($module_id)) {
	$message = new CAdminMessage(GetMessage("post_save_error"), 'Error include module');
}

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();


$tabControl = new CAdminTabControl("tabControl", [
	[
		"DIV"   => "edit1",
		"TAB"   => 'Импорт точек продаж', "ICON" => "main_user_edit",
		"TITLE" => ''
	]
]);


Loader::includeModule("uplab.core");

\Bitrix\Main\Page\Asset::getInstance()
	->addJs("/local/modules/gpart.local/assets/admin/import.js");
\Bitrix\Main\UI\Extension::load("ui.progressbar");
\Bitrix\Main\UI\Extension::load("ui.forms");
\Bitrix\Main\UI\Extension::load("ui.buttons");
\Bitrix\Main\UI\Extension::load("ui.alerts");

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

$tabControl->Begin();
$tabControl->BeginNextTab();

//********************
//Posting issue
//********************

?>
	<div class="adm-detail-content-table">

		<div>
			<h3>Импорт диллеров, Россия</h3>
			<div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
				<div class="ui-ctl-tag">Диллеры, Россия</div>
				<input type="text" name="dealers_file_ru" class="ui-ctl-element" placeholder="Путь к файлу (XLSX)">
			</div>
			<br>
			<button name="import" class="ui-btn ui-btn-success ui-btn-sm" onclick="importDealers(this, 'dealers_file_ru');">Импортировать</button>
		</div>
		<br><br><br><br>
		<div>
			<h3>Импорт диллеров, СНГ</h3>
			<div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
				<div class="ui-ctl-tag">Диллеры, СНГ</div>
				<input type="text" name="dealers_file_cis" class="ui-ctl-element" placeholder="Путь к файлу (XLSX)">
			</div>
			<br>
			<button name="import" class="ui-btn ui-btn-success ui-btn-sm" onclick="importDealers(this, 'dealers_file_cis');">Импортировать</button>
		</div>
		
	</div>
<?php/*	<div>
		<div class="adm-detail-content" id="import">
			<div class="adm-detail-title"></div>
			<div class="adm-detail-content-item-block">
				<table class="adm-detail-content-table edit-table" id="import_edit_table">
					<tbody>
					<tr class="heading">
						<td colspan="2">Диллеры, Россия</td>
					</tr>
					<tr>
						<td width="50%" class="adm-detail-content-cell-l">Путь к файлу (XLSX)<a name="opt_dealers_file_ru"></a>
						</td>
						<td width="50%" class="adm-detail-content-cell-r">
							<input type="text" size="80" maxlength="255" value="" name="dealers_file_ru"></td>
					</tr>
					<tr>
						<td class="adm-detail-content-cell-l"></td>
						<td class="adm-detail-content-cell-r">
							<input type="button" name="import" value="Импортировать" onclick="importDealers(this, 'dealers_file_ru');">
						</td>
					</tr>
					<tr class="heading">
						<td colspan="2">Диллеры, СНГ</td>
					</tr>
					<tr>
						<td width="50%" class="adm-detail-content-cell-l">Путь к файлу (XLSX)<a name="opt_dealers_file_cis"></a>
						</td>
						<td width="50%" class="adm-detail-content-cell-r">
							<input type="text" size="80" maxlength="255" value="" name="dealers_file_cis"></td>
					</tr>
					<tr>
						<td class="adm-detail-content-cell-l"></td>
						<td class="adm-detail-content-cell-r">
							<input type="button" name="import" value="Импортировать" onclick="importDealers(this, 'dealers_file_cis');">
						</td>
					</tr>

					</tbody>
				</table>
			</div>
		</div>
	</div>
*/

//$tabControl->EndTab();

?><? echo bitrix_sessid_post(); ?><?php
//$tabControl->ShowWarnings("post_form", $message);

$tabControl->End();

echo BeginNote();
?>
	<span class="required"><sup>1</sup></span>Отдельно импортируются данные из России и СНГ.<br>
<? echo EndNote(); ?>


<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php"); ?>