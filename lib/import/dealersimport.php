<?php


namespace Gpart\Local\Import;


use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use CIBlockElement;
use Exception;
use Gpart\Local\Helper;
use \PhpOffice\PhpSpreadsheet;
use Uplab\Core\Data\Cache;
use Uplab\Core\Data\StringUtils;
use Uplab\Core\IblockHelper;

class DealersImport
{
	const ITEMS_PER_STEP = 50;
	const DEFAULT_START_INDEX = 1;
	
	const YANDEX_MAPS_FALLBACK_KEY = "f0a4901c-c5e5-48ea-bba6-ef27b13e3460";
	
	/** @var string */
	private $filePath;
	/** @var array */
	private $parsedItems;
	/**
	 * @var PhpSpreadsheet\Worksheet\Worksheet
	 */
	private $worksheet;
	/**
	 * @var array
	 */
	private $cities;
	/**
	 * @var array
	 */
	private $citiesSections;
	
	private $createdCitiesCount = 0;
	private $createdItemsCount = 0;
	private $updatedItemsCount = 0;
	private $notNeedUpdatedItemsCount = 0;
	private $errorItemsCount = 0;
	/**
	 * @var int
	 */
	private $startIndex;
	/**
	 * @var int
	 */
	private $endIndex;
	/**
	 * @var int
	 */
	private $rowsCount;
	
	public function __construct($filePath, $startIndex)
	{
		// По умолчанию - начинать с первой строки
		$this->startIndex = $startIndex ?: (self::DEFAULT_START_INDEX + 1);
		$this->endIndex = $this->startIndex + (self::ITEMS_PER_STEP - 1);
		
		$fileInfo = Helper::getFileInfo($filePath);
		
		Loader::includeModule("iblock");
		
		if (file_exists($fileInfo["PATH"])) {
			$this->filePath = $fileInfo["PATH"];
		} else {
			throw new Exception("Некорректный файл");
		}
	}
	
	public function import()
	{
		$this->initParser();
		$this->parseItems();
		$this->writeItems();
		
		return true;
	}
	
	/**
	 * Проверяет, необходима ли следующая итерация.
	 * Если необходима, то возвращает стартовый индекст для следующего шага
	 *
	 * @return bool
	 */
	public function getNextIndex()
	{
		if (empty($this->rowsCount)) return false;
		
		return $this->endIndex >= $this->rowsCount
			? false
			: $this->endIndex + 1;
	}
	
	private function initParser()
	{
		$parser = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->filePath);
		$this->worksheet = $parser->getActiveSheet();
		$this->rowsCount = $this->worksheet->getHighestDataRow();
	}
	
	/**
	 * @return string
	 * @throws \Bitrix\Main\ArgumentNullException
	 */
	public static function getMapApiKey()
	{
		$api = Option::get('gpart.local', 'YANDEX_MAPS_FALLBACK_KEY', '') ?:
			(
			defined("YANDEX_MAPS_KEY") ? YANDEX_MAPS_KEY : self::YANDEX_MAPS_FALLBACK_KEY
			);
		return $api;
	}
	
	/**
	 * Осуществляет запрос к Яндекс.Геокодеру и возвращает координаты
	 *
	 * @param string $address
	 * @param bool   $cache
	 *
	 * @return string
	 */
	public static function getCoordinateByAddress($address, $cache = true)
	{
		$result = '';
		if ($cache !== true) {
			$result = Cache::CACHE_ABORT_FLAG;
		}
		if ($cache === true) {
			return Cache::cacheMethod(
				__METHOD__,
				[
					"arguments" => [$address, false],
					"time"      => 3600000000,
				]
			);
		}
		
		if ($address) {
			$apiKey = self::getMapApiKey();
			$response = json_decode(
				file_get_contents(
					sprintf(
						"https://geocode-maps.yandex.ru/1.x/?format=json&apikey=%s&geocode=%s",
						$apiKey,
						urlencode($address)
					)
				),
				true
			);
			
			if ($response) {
				$coordinate =
					$response
					["response"]
					["GeoObjectCollection"]
					["featureMember"]
					[0]
					["GeoObject"]
					["Point"]
					["pos"] ?? "";
				if ($coordinate) {
					$result = implode(
						",",
						array_reverse(
							explode(
								" ",
								$coordinate
							)
						)
					);
				}
			}
		}
		return $result;
	}
	
	private function createCityByName($name, $sectionCode)
	{
		if (empty($this->citiesSections)) {
			$this->citiesSections = IblockHelper::getSectionsList(
				[
					"filter" => [
						"IBLOCK_ID" => CITIES_IBLOCK,
					],
					"byKey"  => "CODE",
				]
			);
		}
		
		$arLoadItem = [
			"IBLOCK_ID"         => CITIES_IBLOCK,
			"IBLOCK_SECTION_ID" => $this->citiesSections[$sectionCode]["ID"] ?? null,
			"NAME"              => $name,
			"PROPERTY_VALUES"   => [
				"COORDS" => self::getCoordinateByAddress($name),
			],
		];
		
		$el = new \CIBlockElement;
		$id = $el->Add($arLoadItem);
		
		$this->createdCitiesCount++;
		
		if ($id) {
			$arLoadItem["ID"] = $id;
			$this->cities[$name] = $arLoadItem;
		} else {
			throw new Exception($el->LAST_ERROR . " " . $name);
		}
	}
	
	private function getCityIdByParams($item)
	{
		$country = $item["Дилер:Страна"] ?? "";
		$isRussia = $country == "РОССИЯ";
		
		if ($isRussia) {
			$name = $item["Дилер:Почтовый адрес:НаселенныйПункт"]
				?:
				$item["Дилер:Почтовый адрес:Город"]
					?:
					$item["Территория продаж"];
		} else {
			$name = StringUtils::ucFirst(mb_strtolower($country));
		}
		
		if (empty($name)) {
			throw new Exception(var_export($item, true));
		}
		
		if (empty($this->cities)) {
			$this->cities = IblockHelper::getList(
				[
					"filter" => ["IBLOCK_ID" => CITIES_IBLOCK],
					"byKey"  => "NAME",
				],
				false
			);
		}
		
		if (empty($this->cities[$name]["ID"])) {
			$this->createCityByName($name, $isRussia ? "city" : "country");
		}
		
		return $this->cities[$name]["ID"] ?? false;
	}
	
	/**
	 * Данные "грязные" удалим все лишние пробелы в ключах
	 * @param $array
	 * @return array
	 */
	protected function getNormalizeArrayData($array)
	{
		$tmp = [];
		if ($array && is_array($array)) {
			foreach ($array as $key => $value) {
				$newKey = trim($key);
				$separator = strpos($newKey, ':');
				if ($separator !== false) {
					$ar = explode(':', $newKey);
					foreach ($ar as &$v) {
						$v = trim($v);
					}
					unset($v);
					$newKey = implode(':', $ar);
				}
				$tmp[$newKey] = $value;
			}
		}
		return $tmp;
	}
	
	/**
	 * @param $item
	 * @return array|false
	 * @throws Exception
	 */
	public function getPreparesItemData($item)
	{
		if (empty($item)) {
			return false;
		}
		
		//Данные "грязные" удалим все лишние пробелы в ключах
		$item = $this->getNormalizeArrayData($item);
		
		if (empty($item["Дилер:Ид"])) {
			return false;
		}
		
		/**
		 * Готовим адрес.
		 *
		 * Входные данные, пример:
		 * РОССИЯ,  462430,  Оренбургская обл,  г Орск,  ул. Комарова, дом 32
		 *
		 * Убираем из этого адреса страну и индекс
		 */
		$address = ($item["Дилер:Почтовый адрес"] ?? "") ?: "";
		$country = ($item["Дилер:Страна"] ?? "") ?: "";
		if ($address && $country) {
			$address = preg_replace("~^{$country},\s*(\d+,\s*)?~", "", $address);
		}
		
		// dd($address);
		
		/**
		 * Готовим график рабооты
		 *
		 * Примеры:
		 * 08.00-20.00 ежедневно
		 * пн-пт 08.30-19.00, сб 08.30-18.00, вс 08.30-16.00
		 * круглосуточно
		 *
		 * Если есть слово ежедневно, то нужно разбить на две строки
		 * Если есть запятые, то разбиваем по запятым
		 * Если круглосуточно, то массив из одной строки
		 */
		$workTime = ($item["Дилер:График работы"] ?? "") ?: "";
		$everyDay = "ежедневно";
		if (strpos($workTime, $everyDay) !== false) {
			$workTime = [
				preg_replace("~\s*" . $everyDay . "~", "", $workTime),
				$everyDay,
			];
		} else {
			$workTime = preg_split("~\s*,\s*~", $workTime);
		}
		
		$phones = preg_split("~\s*,\s*~", ($item["Дилер:Телефоны"] ?? "") ?: "");
		$phones = array_map(
			function ($phone) {
				return preg_replace("~^7~", "+7", $phone);
			},
			$phones
		);
		
		$data = [
			"EXTERNAL_ID"     => $item["Дилер:Ид"],
			"NAME"            => $item["Дилер:Наименование"],
			"PROPERTY_VALUES" => [
				"ADDRESS"   => $address,
				"CITY"      => $this->getCityIdByParams($item),
				"POINT"     => self::getCoordinateByAddress($address)
					?:
					/**
					 * Фоллбэк для
					 * КАЗАХСТАН,  Шымкент,  Шымкент,  ш. Тамерлановское, Торговый Дом  «Тулпар-2030» 15 ряд, 15 место
					 *
					 * Без ряда и места яндекс находит координаты
					 */
					
					self::getCoordinateByAddress(
						preg_replace(
							"~\s*\d+\s+(ряд|место),?\s*~",
							"",
							$item["Дилер:Почтовый адрес"]
						)
					),
				"WORK_TIME" => $workTime,
				"PHONE"     => $phones,
				
				// "REGION"    => $item["Территория продаж"],
				// "DISTRICT"  => $item["Контрагент : Округ"],
				// "PERSON"    => [
				// 	"VALUE"       => ($item["Персонал (Основной) : ФИО"] ?? "") ?: "",
				// 	"DESCRIPTION" => ($item["Персонал (Основной) : Должность"] ?? "") ?: "",
				// ],
			],
		];
		
		if (!$data['PROPERTY_VALUES']['POINT']) {
			$this->errorItemsCount++;
			return false;
		}
		
		$data['PROPERTY_VALUES']['HASH'] = $this->getHashForArray($data);
		
		return $data;
	}
	
	protected function getHashForArray($array)
	{
		return md5(serialize($array) . 'SALT');
	}
	
	public function getParsedItems()
	{
		return $this->parsedItems;
	}
	
	private function getHeaderRow()
	{
		$headerRow = [];
		
		foreach ($this->worksheet->getRowIterator(1, 1) as $row) {
			$cellIterator = $row->getCellIterator();
			foreach ($cellIterator as $i => $cell) {
				$headerRow[] = $cell->getValue() ?: $i;
			}
			continue;
		}
		
		return $headerRow;
	}
	
	
	private function parseItems()
	{
		$this->parsedItems = [];
		$headerRow = $this->getHeaderRow();
		
		// $rowsCount = 0;
		foreach ($this->worksheet->getRowIterator($this->startIndex, $this->endIndex) as $row) {
			// if (self::MAX_COUNT > 0 && $rowsCount >= self::MAX_COUNT) break;
			
			$rowItems = [];
			
			$cellIterator = $row->getCellIterator();
			
			// This loops through all cells,
			//    even if a cell value is not set.
			// By default, only cells that have a value
			//    set will be iterated.
			$cellIterator->setIterateOnlyExistingCells(false);
			
			foreach ($cellIterator as $cell) {
				$rowItems[] = $cell->getValue();
			}
			
			$parsedItem = $this->getPreparesItemData(array_combine($headerRow, $rowItems));
			if (!empty($parsedItem)) {
				$this->parsedItems[] = $parsedItem;
			}
		}
	}
	
	private function writeItems()
	{
		if ($this->parsedItems) {
			$externalIds = [];
			foreach ($this->parsedItems as $item) {
				if ($item["EXTERNAL_ID"]) {
					$externalIds[] = $item["EXTERNAL_ID"];
				}
			}
			$currentItems = [];
			if ($externalIds) {
				$currentItems = IblockHelper::getList(
					[
						"filter" => [
							"IBLOCK_ID"   => WTB_IBLOCK,
							'EXTERNAL_ID' => $externalIds
						],
						"select" => ["EXTERNAL_ID", 'PROPERTY_HASH'],
						"byKey"  => "EXTERNAL_ID"
					],
					false
				);
			}
			
			$el = new CIBlockElement();
			
			foreach ($this->parsedItems as $item) {
				$item["IBLOCK_ID"] = WTB_IBLOCK;
				
				if (isset($currentItems[$item["EXTERNAL_ID"]])) {
					$currentItem =& $currentItems[$item["EXTERNAL_ID"]];
					
					if ($currentItem['PROPERTY_HASH_VALUE'] !== $item['PROPERTY_VALUES']['HASH']) {
						$id = $currentItems[$item["EXTERNAL_ID"]]["ID"];
						CIBlockElement::SetPropertyValuesEx(
							$id,
							$item["IBLOCK_ID"],
							array_filter($item["PROPERTY_VALUES"])
						);
						unset($item["PROPERTY_VALUES"]);
						
						$el->Update($id, $item);
						$this->updatedItemsCount++;
					} else {
						$this->notNeedUpdatedItemsCount++;
					}
					unset($currentItem);
				} else {
					$el->Add($item);
					$this->createdItemsCount++;
				}
			}
		}
	}
	
	public function getStats()
	{
		$itemsLeft = $this->rowsCount - $this->endIndex;
		$start = $this->startIndex - 1;
		$end = $this->endIndex - 1;
		$count = $this->rowsCount - 1;
		
		return implode(PHP_EOL, [
			"#{$start}..{$end} / {$count}",
			$itemsLeft > 0 ? "Осталось: {$itemsLeft}" : "Последний шаг",
			"",
			"Создано новых городов: {$this->createdCitiesCount}",
			"Создано новых элементов: {$this->createdItemsCount}",
			"Обновлено элементов: {$this->updatedItemsCount}",
			"Не нуждалось в обнолении элементов: {$this->notNeedUpdatedItemsCount}",
			"Ошибочных данных: {$this->errorItemsCount}",
		]);
	}
	
	/**
	 * @return int
	 */
	public function getRowsCount(): int
	{
		return $this->rowsCount;
	}
	
	/**
	 * @return int
	 */
	public function getEndIndex(): int
	{
		return $this->endIndex;
	}
}