<?
namespace Gpart\Local;

use CIBlockElement;
use CFile;
use \Bitrix\Main\Data\Cache;


class Config {
	
	use \Uplab\Core\Traits\SingletonTrait;
	
	private $iBlockID = [CONFIGS_IBLOCK, FULL_PHOTOS_IBLOCK];

	private $arData = [];
	
	private $iTimeCache = 7200;
	
	private $strCache = 'configs';
	private $cacheDir = '/gl-config';
	
	public static function getInstance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new static();
		}

		self::$instance->arData = self::$instance->getData();
		return self::$instance;
	}
	
	private function getData():array {
		$arResult = [];
		
		$cache = Cache::createInstance();
		
		if ($cache->initCache($this->iTimeCache, $this->strCache, $this->cacheDir)) {
			$arResult = $cache->getVars();
		} elseif ($cache->startDataCache()) {
			foreach($this->iBlockID as $iBlockID) {
				$arFilter = Array("IBLOCK_ID"=>$iBlockID);
				$rsResult = CIBlockElement::GetList(Array(), $arFilter, false, false);
				while($obItem = $rsResult->GetNextElement()) {
					$arItem = $obItem->GetFields();
					$arItem["PROPERTIES"] = $obItem->GetProperties();
					$arResult[$arItem["CODE"]][] = $arItem;
				}
			}
			
			$cache->endDataCache($arResult);
		}
		return $arResult;
	}
	
	public function getFileWithAlt(string $strCode = ''):array {
		$arResult = [];
		
		if (!empty($strCode)) {
			
			$arData = current($this->arData[$strCode]);
			$arResult["src"] = CFile::GetPath($arData["PROPERTIES"]["FILE"]["VALUE"]);
			$arResult["alt"] = $arData["PREVIEW_TEXT"];
		}

		return $arResult;
	}
	
	public function getTextValue(string $strCode = ''):string {
		$strResult = '';
		
		if (!empty($strCode)) {
			$arData = current($this->arData[$strCode]);
			$strResult = $arData["PREVIEW_TEXT"];
		}
		
		return $strResult;
	}
	
	public function getLink(string $strCode = '', $popUp = true):array {
		$arResult = [];
		
		if (!empty($strCode)) {
			$arData = current($this->arData[$strCode]);
			$arResult["text"] = $arData["PROPERTIES"]["LINK"]["DESCRIPTION"];
			if ($popUp && strpos($arData["PROPERTIES"]["LINK"]["VALUE"], "#") === 0) {
				$arResult["popup"] = str_replace("#", "", $arData["PROPERTIES"]["LINK"]["VALUE"]);
				$arResult["href"] = '';
			} else {
				$arResult["href"] = $arData["PROPERTIES"]["LINK"]["VALUE"];
				$arResult["popup"] = '';
			}
		}
		
		return $arResult;
	}
	
	public function getTextListValue(string $strCode = ''):array {
		$arResult = [];
		
		if (!empty($strCode)) {
			$arData = current($this->arData[$strCode]);
			$arList = explode("\n", $arData["PREVIEW_TEXT"]);
			foreach($arList as $strItem) {
				$strText = trim($strItem);
				if (!empty($strText)) {
					$arResult[] = [
						"text" => $strText
					];
				}
			}
		}
		
		return $arResult;
	}
	
	
	
	public function getFile(string $strCode = ''):array {
		$arResult = [];
		
		if (!empty($strCode)) {
			$arData = current($this->arData[$strCode]);
			
			$arFileArray = CFile::GetFileArray($arData["PROPERTIES"]["FILE"]["VALUE"]);
			
			$arExt = explode(".", $arFileArray["ORIGINAL_NAME"]);
			$strExt = $arExt[count($arExt) - 1];
		
			$arResult = [
		        "href" => CFile::GetPath($arData["PROPERTIES"]["FILE"]["VALUE"]),
		        "ext" => $strExt,
		        "description" => $arFileArray["DESCRIPTION"]?$arFileArray["DESCRIPTION"]:$arFileArray["ORIGINAL_NAME"],
		        "size" => CFile::formatSize($arFileArray["FILE_SIZE"]),
		        "title" => $arItem["NAME"],
		        "external" => true,
		        "disabled" => false,
		        "attr" => " data-some-additional-attributes='' "
			];
			
		}

		return $arResult;
	}
	
	public function getImageAdaptive(string $strCode = ''):array {
		$arResult = [];
		if (!empty($strCode)) {
			$arData = current($this->arData[$strCode]);
			
			if ($arData["PROPERTIES"]["IMG"]["VALUE"]) {
				$arResult["DESKTOP"] = [
					"SRC" => CFile::GetPath($arData["PROPERTIES"]["IMG"]["VALUE"])
				];
			}
			if ($arData["PROPERTIES"]["IMG_TAB"]["VALUE"]) {
				$arResult["TAB"] = [
					"SRC" => CFile::GetPath($arData["PROPERTIES"]["IMG_TAB"]["VALUE"])
				];
			}
			if ($arData["PROPERTIES"]["IMG_MOB"]["VALUE"]) {
				$arResult["MOB"] = [
					"SRC" => CFile::GetPath($arData["PROPERTIES"]["IMG_MOB"]["VALUE"])
				];
			}
		}
		return $arResult;
		
	}
	
	public function getSimpleImagePath(string $strCode = ''):string {
		$srtResult = '';
		if (!empty($strCode)) {
			$arData = current($this->arData[$strCode]);
			$strResult = CFile::GetPath($arData["PROPERTIES"]["FILE"]["VALUE"]);
		}
		return $strResult;
	}
	
	
	public function setEditLink(&$component, $arrCodes = []) {
		$entryId = md5(date("HisdmY").rand(1,999999999));
		
		foreach($arrCodes as $strCode) {
			$arData = current($this->arData[$strCode]);
			$iID = $arData["ID"];
			$btnTitle = $arData["NAME"];
			$component->AddEditAction($entryId, '/bitrix/admin/iblock_element_edit.php?IBLOCK_ID='.$arData["IBLOCK_ID"].'&type='.$arData["IBLOCK_TYPE_ID"].'&lang=ru&ID='.$iID.'&bxpublic=Y&from_module=iblock&return_url=%2F', htmlspecialchars_decode($btnTitle));
			
		}
		
		return $entryId;
	}
}