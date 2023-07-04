<?
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

defined("B_PROLOG_INCLUDED") && B_PROLOG_INCLUDED === true || die();

if (!Loader::includeModule("gpart.local")) return;
Loc::loadMessages(__FILE__);

return [
	[
		"sort"        => 1,
		"section"     => "gpart.local",
		"parent_menu" => "global_menu_settings",
		"icon"        => "learning_icon_certification",
		"page_icon"   => "fileman_sticker_icon",
		"text"        => "Gpart Local Utils",
		"url"         => "",
		"items_id"    => "gpart.local",
		"more_url"    => [],
		"items"       => [
			[
				"text"     => "Импорт продуктов",
				"icon"     => "rating_menu_icon",
				"url"      => "/bitrix/admin/gpart_local_sync_catalog.php",
				"more_url" => [],
			],
			[
				"text"     => "Импорт точек продаж",
				"icon"     => "rating_menu_icon",
				"url"      => "/bitrix/admin/gpart_local_sync_wtb.php",
				"more_url" => [],
			],
		],
	],
];