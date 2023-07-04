<?php

namespace Gpart\Local\Common\Cities;

class Helper
{
	
	//region Singelton
	private static $instance = null;
	
	private function __construct()
	{
	}
	
	private function __clone()
	{
	}
	
	private function __wakeup()
	{
	}
	
	public static function getInstance(): Helper
	{
		if (self::$instance === null) {
			self::$instance = new static();
		}
		
		return self::$instance;
	}
	
	//endregion
	
	/**
	 * Вернуть ID инфоблока
	 *
	 * @return int
	 */
	public function getIdDB(): int
	{
		return (int)WTB_IBLOCK;
	}
	
	public function getCities(): array
	{
		$items = [];
		
		$iblockId = $this->getIdDB();
		
		$cache = \Bitrix\Main\Data\Cache::createInstance();
		$cacheDir = '/cities-' . $iblockId;
		if ($cache->initCache(36000, 'cities', $cacheDir)) {
			$items = $cache->getVars();
		} elseif ($cache->startDataCache()) {
			
			
			$cache_manager = \Bitrix\Main\Application::getInstance()->getTaggedCache();
			$cache_manager->startTagCache($cacheDir);
			$cache_manager->registerTag('iblock_id_' . $iblockId);
			
			$res = \CIBlockElement::GetList(
				[
					'PROPERTY_CITY' => 'asc'
				],
				[
					'IBLOCK_ID'      => $iblockId,
					'ACTIVE'         => 'Y',
					'!PROPERTY_CITY' => false
				],
				['PROPERTY_CITY']
			);
			
			while ($item = $res->Fetch()) {
				if ($val = $item['PROPERTY_CITY_VALUE']) {
					$items[] = $val;
				}
			}
			
			$cache_manager->endTagCache();
			
			if (!$items) {
				$cache->abortDataCache();
			}
			
			$cache->endDataCache($items);
		}
		
		
		return $items;
	}
	
	public function getCitiesForTwigData($userCity): array
	{
		$twigData = [];
		if ($items = $this->getCities()) {
			foreach ($items as $val) {
				$twigData[] = [
					'selected' => $userCity === $val,
					'value'    => urlencode($val),
					'text'     => $val
				];
			}
		}
		return $twigData;
	}
}