<?
namespace Gpart\Local;

use CIBlockElement;
use CIBlockSection;
use CFile;
use \Bitrix\Main\Data\Cache;
use Uplab\Core\Data\StringUtils;


class WTB
{
	
	public static $filterType = 'FILTER_TYPE';
	
	public static function getFilterOptions(): array
	{
		
		$arResult = [];
		$i = 1;
		$obSections = CIBlockSection::GetList(
			["SORT" => "ASC"],
			["IBLOCK_ID" => CITIES_IBLOCK, "ACTIVE" => "Y"],
			false,
			["ID", "NAME", "CODE", "DESCRIPTION"]
		);
		while ($arSection = $obSections->GetNext()) {
			$checked = false;
			if ($i == 1) {
				$checked = true;
			}
			$arResult[] = [
				"radio"       => true,
				"name"        => "select-country",
				"required"    => true,
				"checked"     => $checked,
				"text"        => $arSection["NAME"],
				"value"       => $arSection["ID"],
				"code"        => $arSection["CODE"],
				"placeholder" => $arSection["DESCRIPTION"],
				"attr"        => "onchange='changeFilter(this)'"
			];
			$i++;
		}
		return $arResult;
	}
	
	public static function getInitMapOption($sectionID = 0)
	{
		$result = [
			"zoom"   => 5,
			"coords" => [55.1, 55.8]
		];
		if ($sectionID > 0) {
			$data = CIBlockSection::GetList([], ["ID" => $sectionID, "IBLOCK_ID" => CITIES_IBLOCK], false, ["ID", "NAME", "UF_*"])->Fetch();
			if (!empty($data)) {
				$result["zoom"] = (int)$data["UF_MAP_ZOOM"];
				$initCoords = explode(",", $data["UF_COORDS"]);
				$result["coords"] = [(float)$initCoords[0], (float)$initCoords[1]];
			} else {
				$data = CIBlockElement::GetList([], ["ID" => $sectionID, "IBLOCK_ID" => CITIES_IBLOCK], false, false, ["ID", "NAME", "PROPERTY_COORDS"])->Fetch();
				
				$initCoords = explode(",", $data["PROPERTY_COORDS_VALUE"]);
				$result["zoom"] = 10;
				$result["coords"] = [(float)$initCoords[0], (float)$initCoords[1]];
				
			}
		}
		return $result;
	}
	
	public static function getCities(int $iParentSection = 29, int $iSelectedValue = 0): array
	{
		
		$allCities = [];
		$arSelect = ["PROPERTY_CITY"];
		$arFilter = ["IBLOCK_ID" => WTB_IBLOCK, "ACTIVE" => "Y"];
		$arGroup = ["PROPERTY_CITY"];
		$dbRes = CIBlockElement::GetList([], $arFilter, $arGroup, false, $arSelect);
		while ($arCity = $dbRes->GetNext()) {
			if ($arCity["PROPERTY_CITY_VALUE"]) {
				$allCities[] = $arCity["PROPERTY_CITY_VALUE"];
			}
		}
		
		
		$arResult = [];
		
		$arSelect = ["ID", "NAME"];
		$arFilter = ["IBLOCK_ID" => CITIES_IBLOCK, "ACTIVE" => "Y", "IBLOCK_SECTION_ID" => $iParentSection, "ID" => $allCities];
		$arSort = ["NAME" => "ASC"];
		$dbRes = CIBlockElement::GetList($arSort, $arFilter, false, false, $arSelect);
		while ($arFields = $dbRes->GetNext()) {
			$bSelected = false;
			if ($iSelectedValue == $arFields["ID"]) {
				$bSelected = true;
			}
			$arResult[] = [
				"value"    => $arFields["ID"],
				"text"     => $arFields["NAME"],
				"selected" => $bSelected
			];
		}
		
		return $arResult;
	}
	
	public static function getCitiesByType(int $iType = 0): array
	{
		$arResult = [];
		
		$cache = Cache::createInstance();
		
		if ($cache->initCache(7200, 'type_cities_' . $iType, '/type_cities')) {
			$arResult = $cache->getVars();
		} else {
			
			$obRes = CIBlockElement::GetList([], ["IBLOCK_ID" => CITIES_IBLOCK, "ACTIVE" => "Y", "IBLOCK_SECTION_ID" => $iType], false, false, ["ID"]);
			while ($arCity = $obRes->GetNext()) {
				$arResult[] = $arCity["ID"];
			}
			
			$cache->endDataCache($arResult);
		}
		
		
		return $arResult;
	}
	
	public static function getMapRouteLink(float $lat = 0.00, float $lon = 0.00)
	{
		$strUrl = "https://yandex.ru/maps/?ll=" . $lat . "%2C" . $lon . "&mode=routes&rtext=~" . $lon . "%2C" . $lat . "&rtt=auto&z=12";
		return $strUrl;
	}
	
	public static function getItems(array $arExternalFilter = [], $bMap = false, &$component = null, $pageOption = [], $bBtnChoose = false): array
	{
		$arResult = [];
		
		$sPrefScript = '';
		if ($bBtnChoose) {
			$sPrefScript = '-choose';
		}
		
		$config = Config::getInstance();
		$btn = $config->getLink('wtb_btn_link');
		$btnRoute = $config->getLink('wtb_btn_route');
		
		$arFilter = [
			"IBLOCK_ID" => WTB_IBLOCK,
			"ACTIVE"    => "Y"
		];
		$arSort = [
			"SORT" => "ASC",
			"NAME" => "ASC",
			"ID"   => "ASC"
		];
		
		if (isset($arExternalFilter["IDS"])) {
			$arExternalFilter["ID"] = $arExternalFilter["IDS"];
			unset($arExternalFilter["IDS"]);
		} else {
			if (isset($arExternalFilter["TYPE_CITY"])) {
				$iTypeCity = $arExternalFilter["TYPE_CITY"];
				unset($arExternalFilter["TYPE_CITY"]);
				$arCities = self::getCitiesByType($iTypeCity);
				$arExternalFilter["PROPERTY_CITY"] = $arCities;
			}
			
		}
		if ($arExternalFilter) {
			$arFilter = array_merge($arFilter, $arExternalFilter);
		}
		$dbPage = false;
		
		if (!empty($pageOption)) {
			$dbPage["nTopCount"] = false;
			$dbPage["nPageSize"] = $pageOption["count"];
			$dbPage["iNumPage"] = $pageOption["page"];
			$dbPage["checkOutOfRange"] = true;
		}
		
		$dbRes = CIBlockElement::GetList($arSort, $arFilter, false, $dbPage, ["ID", "IBLOCK_ID", "PROPERTY_POINT", "PROPERTY_PHONE", "PROPERTY_ADDRESS", "PROPERTY_WORK_TIME"]);
		
		while ($arFields = $dbRes->GetNext()) {
			//$arFields = $obField->GetFields();
			//$arFields["PROPERTIES"] = $obField->GetProperties();
			//print_r($obField);
			//die;
			
			$arCoords = explode(",", $arFields["PROPERTY_POINT_VALUE"]);
			if ($arFields["PROPERTY_POINT_VALUE"] && $arCoords && count($arCoords) == 2) {
				if ($bMap) {
					
					$arResult[] = [
						"id"                  => $arFields["ID"],
						"location"            => [
							$arCoords[0],
							$arCoords[1]
						],
						"balloonAjaxFetchUrl" => "/ajax/getbaloon" . $sPrefScript . ".php?id=" . $arFields["ID"]
					];
				} else {
					
					$arPhones = [];
					foreach ($arFields["PROPERTY_PHONE_VALUE"] as $strPhone) {
						$arPhones[] = [
							"href" => StringUtils::clearPhone($strPhone),
							"text" => $strPhone,
                            "attr" => ' data-ga-event data-event-category="click" data-event-action="phone" data-event-label="wtb" '
						];
					}
					
					$attr = '';
					
					if ($component) {
						$component->AddEditAction($arFields["ID"], '/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=' . $arFields["IBLOCK_ID"] . '&type=' . $arFields["IBLOCK_TYPE_ID"] . '&lang=ru&ID=' . $iID . '&bxpublic=Y&from_module=iblock&return_url=%2F', 'Редактировать');
						$attr = " id=\"" . $component->GetEditAreaId($arFields["ID"]) . "\" ";
					}
					
					if (!$bBtnChoose) {
						$button = [
							"type"  => "button",
							"color" => "white",
							"text"  => $btn["text"],
							"popup" => $btn["popup"],
							"href"  => $btn["href"],
							"attr"  => 'data-street="' . $arFields["PROPERTY_ADDRESS_VALUE"]["TEXT"] . '" data-ga-event data-event-category="form" data-event-action="visit" data-event-label="wtb_request"',
						];
					} else {
						$button = [
							"type"  => "button",
							"color" => "white",
							"text"  => "Выбрать",
							"attr"  => "data-request-choose-id='" . $arFields["ID"] . "' data-request-choose-url='/ajax/place-shop.php' data-street='" . $arFields["PROPERTY_ADDRESS_VALUE"]["TEXT"] . "'"
						];
					}
					
					$arResult[] = [
						"attr"        => $attr,
						"id"          => $arFields["ID"],
						"address"     => $arFields["PROPERTY_ADDRESS_VALUE"]["TEXT"],
						"worktime"    => implode("<br/>", $arFields["PROPERTY_WORK_TIME_VALUE"]),
						"phones"      => $arPhones,
						"link_action" => [
							"icon" => [
								"name"  => "geo",
								"right" => false
							],
							"text" => $btnRoute["text"],
							"href" => self::getMapRouteLink($arCoords[1], $arCoords[0])
						],
						"button"      => $button
					];
				}
			}
		}
		
		return $arResult;
	}
}