<?php


namespace Gpart\Local\Common\Cities;


use Bitrix\Main\Service\GeoIp\Manager;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Cookie;

class UserCity
{
	private $city = '';
	private $cityDefault = 'Москва';
	
	private $cookieName = 'userCityG';
	
	//region Singelton
	private static $instance = null;
	
	private function __construct()
	{
		$city = '';
		
		//Берём из кук
		if (($cookieCity = Application::getInstance()->getContext()->getRequest()->getCookie($this->cookieName)) && in_array($cookieCity, Helper::getInstance()->getCities())) {
			$city = $cookieCity;
		}
		if (!$city && $city = $this->getCityIP()) {
			$cookie = new Cookie($this->cookieName, $city);
			Application::getInstance()->getContext()->getResponse()->addCookie($cookie);
		}
		
		$this->city = $city;
	}
	
	/**
	 * @return string
	 */
	public function getDefaultCity(): string
	{
		return $this->cityDefault;
	}
	
	private function __clone()
	{
	}
	
	private function __wakeup()
	{
	}
	
	public static function getInstance(): UserCity
	{
		if (self::$instance === null) {
			self::$instance = new static();
		}
		
		return self::$instance;
	}
	
	//endregion
	
	/**
	 * Получить город пользователя
	 *
	 * @return string|null
	 */
	public function getUserCity(): string
	{
		return $this->city;
	}
	
	/**
	 * Получить город по IP пользователя
	 *
	 * @return string|null
	 */
	protected function getCityIP(): string
	{
		$cityName = '';
		try {
			if (!$this->isBot()) {
				
				$ip = Manager::getRealIp();
				
				//TODO доработать пользователей будет много
				$cache = \Bitrix\Main\Data\Cache::createInstance();
				$cacheDir = '/getCityIp';
				$cacheId = md5($ip);
				if ($cache->initCache(84600, $cacheId, $cacheDir)) {
					$cityName = $cache->getVars();
				} elseif ($cache->startDataCache()) {
					
					if ($result = Manager::getDataResult($ip, 'ru', ['cityName'])) {
						if ($geoData = $result->getGeoData()) {
							$cityName = $geoData->cityName ?: '';
						}
					} else {
						$cache->abortDataCache();
					}
					if (!$cityName) {
						$cache->abortDataCache();
					}
					$cache->endDataCache($cityName);
				}
				
			}
		} catch (\Exception $ex) {
			AddMessage2Log($ex->getMessage());
		}
		return (string)$cityName;
	}
	
	/**
	 * @return false|int
	 */
	protected function isBot()
	{
		return preg_match(
			"~(Google|Yahoo|Rambler|Bot|Yandex|Spider|Snoopy|Crawler|Finder|Mail|curl)~i",
			$_SERVER['HTTP_USER_AGENT']
		);
	}
}