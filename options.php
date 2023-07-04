<?

use Bitrix\Main\Loader;


defined("B_PROLOG_INCLUDED") && B_PROLOG_INCLUDED === true || die();
/**
 * @global CMain $APPLICATION
 */


$module_id = "gpart.local";

$options = new Gpart\Local\Module\Options(
	__FILE__,
	[
		[
			"DIV"     => "common",
			"TAB"     => "Настройки",
			"OPTIONS" => [
				"Яндекс.Карты",
				[
					"YMAP_API_KEY",
					"API ключ Яндекс.Карты",
					"",
					["text", 40],
				],
				"Ключ Яндекс.Карты для поиска адресов",
				[
					"YANDEX_MAPS_FALLBACK_KEY",
					"API",
					"",
					["text", 40]
				]
			]
		]
	]
);


$options->drawOptionsForm();