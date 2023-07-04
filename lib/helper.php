<?

namespace Gpart\Local;

use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;
use CAllDBResult;
use CBitrixComponent;
use CMain;
use CUser;
use Uplab\Core\Components\TemplateBlock;
use Uplab\Core\Constant;
use Uplab\Core\Helper as UplabHelper;
use Bitrix\Main\Loader;
use Uplab\Core\Uri;

defined("B_PROLOG_INCLUDED") && B_PROLOG_INCLUDED === true || die();

/**
 * @global CMain $APPLICATION
 * @global CUser $USER
 */
class Helper extends UplabHelper
{
    const MODULE_ID = "gpart.local";
    const PHONE_PATTERN = "/^\\+7\\(\\d{3}\\)\\d{3}-\\d{2}-\\d{2}$/";

    public static function test()
    {
        die("It works! Module gpart.local installed correctly.");
    }

    public static function universalImageResize(
        callable $callback,
                 $picture,
                 $params,
                 $additionalParams = [],
        callable $prepareNewSrcImage = null
    )
    {
        if (!is_callable($callback)) return false;

        $firstImageParams = $params;

        // Увеличиваем размер картинок на 10%, чтобы они были почетче
        $firstImageParams["width"] = round($firstImageParams["width"] * 1.1);
        $firstImageParams["height"] = round($firstImageParams["height"] * 1.1);

        $resultImage = $callback($picture, $firstImageParams);

        if ($resultImage) {
            $scalesList = [2, 3];
            $srcSet = [];

            // чтобы уменьшить кол-во вызовов CFile::GetFileArray в parent::resizeImage,
            // есть возможность отправить сразу массив с инфорацией об изображениии
            if (isset($prepareNewSrcImage) && is_callable($prepareNewSrcImage)) {
                $preparedSrcImage = $prepareNewSrcImage($picture, $resultImage);
            } else {
                $preparedSrcImage = $picture;
            }

            foreach ($scalesList as $scale) {

                // если размер картинки меньше или равен $params['width'], $params['height']
                // то браузер исходя из srcset будет уменьшать в 2-3 раза картинку
                // поэтому нельзя заполнять srcset, если размеры картинки меньше или равены основной.

                $width = $params["width"] * $scale;
                $height = $params["height"] * $scale;

                if ($resultImage["SRC_WIDTH"] >= $width && $resultImage["SRC_HEIGHT"] >= $height) {
                    // отправляем сразу $file вместо $picture,
                    // уменьшим кол-во вызовов CFile::GetFileArray в parent::resizeImage
                    $srcSetImage = $callback(
                        $preparedSrcImage,
                        array_merge(
                            (array)$params,
                            [
                                "width" => $width,
                                "height" => $height,
                            ]
                        )
                    );

                    if ($srcSetImage["SRC"]) {
                        $srcSet[] = [
                            "scale" => $scale,
                            "src" => $srcSetImage["SRC"],
                        ];
                    }
                }

            }

            $resultImage = [
                "src" => $resultImage["SRC"],
                "srcset" => $srcSet,
                "alt" => $resultImage["DESCRIPTION"],
                "width" => $resultImage["WIDTH"],
                "height" => $resultImage["HEIGHT"],
                // "_"      => $resultImage,
            ];

            if ($additionalParams) {
                $resultImage = array_merge(
                    $resultImage,
                    array_filter((array)$additionalParams)
                );
            }
        } else {

            $resultImage = false;

        }

        return $resultImage;
    }

    public static function resizeImageFile($picture, $params, $additionalParams = [])
    {
        return self::universalImageResize(
            [parent::class, "resizeImageFile"],
            $picture,
            $params,
            $additionalParams
        );
    }

    /**
     * @param array|int $picture массив CFile::GetFileArray или ID файла
     * @param array $params
     * @param array $additionalParams
     *
     * @return array|bool
     */
    public static function resizeImage($picture, $params = [], $additionalParams = [])
    {
        return self::universalImageResize(
            [parent::class, "resizeImage"],
            $picture,
            $params,
            $additionalParams,
            function ($picture, $resultImage) {
                return $resultImage;
            }
        );
    }

    /**
     * Формирует строку утилитарных классов для компонента
     * Может как вернуть строку, так и передать ее по ссылке
     *
     * @param        $arParams
     * @param array $options
     * @param string $class
     *
     * @return string
     */
    public static function getUtilityClassesFromParams(&$arParams, $options = [], &$class = "")
    {
        $v = trim($arParams["BG_COLOR"] ?? "");
        if ($v) {
            $class .= " bg-{$v}";
        }

        if (empty($options["offsetProperty"]) && in_array($v, ["white", "blue"])) {
            $options["offsetProperty"] = "padding";
        }

        switch ($options["offsetProperty"]) {
            case "padding":
                $offsetProp = "p";
                break;
            case "margin":
            default:
                $offsetProp = "m";
                break;
        }

        $v = $arParams["OFFSET_TOP"] ?? "";
        if (strlen($v) > 0) {
            $class .= " {$offsetProp}t-{$v}";
        }

        $v = $arParams["OFFSET_MD_TOP"] ?? "";
        if (strlen($v) > 0) {
            $class .= " {$offsetProp}t-md-{$v}";
        }

        $v = $arParams["OFFSET_LG_TOP"] ?? "";
        if (strlen($v) > 0) {
            $class .= " {$offsetProp}t-lg-{$v}";
        }

        $v = $arParams["OFFSET_BOTTOM"] ?? "";
        if (strlen($v) > 0) {
            $class .= " {$offsetProp}b-{$v}";
        }

        $v = $arParams["OFFSET_MD_BOTTOM"] ?? "";
        if (strlen($v) > 0) {
            $class .= " {$offsetProp}b-md-{$v}";
        }

        $v = $arParams["OFFSET_LG_BOTTOM"] ?? "";
        if (strlen($v) > 0) {
            $class .= " {$offsetProp}b-lg-{$v}";
        }

        $v = $arParams["OFFSET_LEFT"] ?? "";
        if (strlen($v) > 0) {
            $class .= " wrap-pl-{$v}";
        }

        $v = $arParams["OFFSET_RIGHT"] ?? "";
        if (strlen($v) > 0) {
            $class .= " wrap-pr-{$v}";
        }

        return $class;
    }

    /**
     * @param $arParams
     */
    public static function addUtilityClassesToParams(&$arParams)
    {
        // region Оступы сверху и снизу
        $values = [
            "" => "По умолчанию",
            "0" => "Нет отступа",
            "16" => "16",
            "20" => "20",
            "24" => "24",
            "28" => "28",
            "32" => "32",
            "36" => "36",
            "40" => "40",
            "48" => "48",
            "64" => "64",
            "80" => "80",
            "96" => "96",
            "112" => "112",
            "128" => "128",
        ];
        $arParams["OFFSET_TOP"] = [
            "NAME" => "Отступ сверху",
            "TYPE" => "LIST",
            "VALUES" => $values,
            "CUSTOM_PARENT" => ["UTILITIES", "Доп. параметры"],
            "DEFAULT" => "",
        ];
        $arParams["OFFSET_BOTTOM"] = [
            "NAME" => "Отступ снизу",
            "TYPE" => "LIST",
            "VALUES" => $values,
            "CUSTOM_PARENT" => ["UTILITIES", "~"],
            "DEFAULT" => "",
        ];
        $arParams["OFFSET_MD_TOP"] = [
            "NAME" => "Отступ сверху (планшет, mt-md)",
            "TYPE" => "LIST",
            "VALUES" => $values,
            "CUSTOM_PARENT" => ["UTILITIES", "~"],
            "DEFAULT" => "",
        ];
        $arParams["OFFSET_MD_BOTTOM"] = [
            "NAME" => "Отступ снизу (планшет, mb-md)",
            "TYPE" => "LIST",
            "VALUES" => $values,
            "CUSTOM_PARENT" => ["UTILITIES", "~"],
            "DEFAULT" => "",
        ];
        $arParams["OFFSET_LG_TOP"] = [
            "NAME" => "Отступ сверху (большие экраны, mt-lg)",
            "TYPE" => "LIST",
            "VALUES" => $values,
            "CUSTOM_PARENT" => ["UTILITIES", "~"],
            "DEFAULT" => "",
        ];
        $arParams["OFFSET_LG_BOTTOM"] = [
            "NAME" => "Отступ снизу (большие экраны, mb-lg)",
            "TYPE" => "LIST",
            "VALUES" => $values,
            "CUSTOM_PARENT" => ["UTILITIES", "~"],
            "DEFAULT" => "",
        ];
        // endregion

        // region Оступы справа и слева
        $values = [
            "" => "По умолчанию",
            "0" => "Нет отступа",
            "1" => "x1",
            "2" => "x2",
        ];
        $arParams["OFFSET_LEFT"] = [
            "NAME" => "Отступ слева",
            "TYPE" => "LIST",
            "VALUES" => $values,
            "CUSTOM_PARENT" => ["UTILITIES", "Доп. параметры"],
            "DEFAULT" => "",
        ];
        $arParams["OFFSET_RIGHT"] = [
            "NAME" => "Отступ справа",
            "TYPE" => "LIST",
            "VALUES" => $values,
            "CUSTOM_PARENT" => ["UTILITIES", "Доп. параметры"],
            "DEFAULT" => "",
        ];
        // endregion

        $arParams["BG_COLOR"] = [
            "NAME" => "Цвет фона",
            "TYPE" => "LIST",
            "VALUES" => [
                "" => "По умолчанию",
                "transparent" => "Прозрачный",
                "gray" => "Серый",
                "white" => "Белый",
                "blue" => "Синий",
            ],
            "CUSTOM_PARENT" => ["UTILITIES", "Доп. параметры"],
            "DEFAULT" => "",
        ];
    }

    /**
     * @param        $arParams
     * @param        $arCurrentValues
     * @param string $groupKey
     * @param string $name
     *
     * @noinspection PhpUnused
     */
    public static function addActionsToParams(&$arParams, &$arCurrentValues, $groupKey = "ACTION", $name = "Кнопка")
    {
        CBitrixComponent::includeComponentClass("uplab.core:template.block");

        // Множественная группа свойств.
        // При заполнении одного из элементов, появляется следующий
        TemplateBlock::addDynamicParameters(
            $arParams,
            $arCurrentValues,
            [
                "CODE" => $groupKey,
                "NAME" => $name,
            ],
            [
                "TEXT" => ["NAME" => "Текст ссылки"],
                "HREF" => ["NAME" => "Адрес ссылки"],
                "TYPE" => [
                    "TYPE" => "LIST",
                    "NAME" => "Тип элемента",
                    "VALUES" => [
                        "" => "Ссылка",
                        "btn-white" => "Кнопка с белым фоном",
                        "btn-dark" => "Кнопка с синим фоном",
                        "btn-outline-light" => "Кнопка с белой рамкой",
                    ],
                ],
                "FILE" => [
                    "NAME" => 'Файл',
                    "TYPE" => "FILE",
                    "COLS" => 30,
                    "FD_TARGET" => "F",
                    "FD_EXT" => '',
                    "FD_UPLOAD" => true,
                    "FD_USE_MEDIALIB" => true,
                    "FD_MEDIALIB_TYPES" => ['image'],
                    'REFRESH' => 'Y',
                ],
                "ATTRS" => ["NAME" => "Атрибуты ссылки"],
            ]
        );
    }

    /**
     * @param        $arParams
     * @param        $arResult
     * @param string $groupKey
     * @param array $additionalParams
     *
     * @return array
     * @noinspection PhpUnused
     */
    public static function getActionsFromParams(&$arParams, &$arResult, $groupKey = "ACTION", $additionalParams = [])
    {
        CBitrixComponent::includeComponentClass("uplab.core:template.block");

        if (!isset($arResult["PARSED_PARAMS"][$groupKey])) {
            TemplateBlock::parseDynamicGroupFromParams($groupKey, $arParams, $arResult);
        }

        $actionsList = [];

        foreach ($arResult["PARSED_PARAMS"][$groupKey] as $arAction) {
            if (empty($arAction["TEXT"])) continue;

            $action["text"] = $arAction["TEXT"];
            $action["title"] = $arAction["TEXT"];

            if (!empty($arAction["FILE"])) {

                $action["image"] = ["svg" => "32/download"];
                $action["attrs"] = $arAction["ATTRS"] ?: " download";
                $action["icon_first"] = true;
                $action["href"] = $arAction["FILE"];

            } elseif (!empty($arAction["HREF"])) {

                $action["href"] = Constant::extract($arAction["HREF"]);
                $action["attrs"] = $arAction["ATTRS"];

            }

            if (!empty($additionalParams)) {
                $action = array_merge($action, $additionalParams);
            }

            switch ($arAction["TYPE"]) {
                case "btn-white":
                    $action["type"] = "button";
                    $action["color"] = "white";
                    break;

                case "btn-dark":
                    $action["type"] = "button";
                    $action["color"] = "dark";
                    break;

                case "btn-outline-light":
                default:
                    $action["type"] = "button";
                    $action["color"] = "outline-light";
                    break;
            }

            if (!empty($action["href"])) {
                $actionsList[] = $action;
            }
        }

        return $actionsList;
    }


    /**
     * Возвращает название сайта
     * @param string|false $siteId
     *
     * @return mixed
     */
    public static function getSiteName($siteId = false)
    {
        if (!$siteId) {
            $siteId = SITE_ID;
        }
        return self::getSiteInfo($siteId)['SITE_NAME'] ?: Option::get('main', 'site_name');
    }

    /**
     * Возвращает информация о сайте
     *
     * @param      $siteId
     * @param bool $useCache
     *
     * @return array|false
     */
    public static function getSiteInfo($siteId, $useCache = true)
    {
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        $cacheDir = 'getSiteInfo';
        $cacheId = $siteId;
        $result = [];
        if ($cache->initCache($useCache ? 84600 : 0, $cacheId, $cacheDir)) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $rsSites = \CSite::GetByID($siteId);
            $result = $rsSites->Fetch();

            if (!$result) {
                $cache->abortDataCache();
            }

            $cache->endDataCache($result);
        }

        return $result;
    }

    public static function GetBtnArray($data = [], $params = [])
    {
        $result = [
            "type" => "button"
        ];
        if ($params) {
            $result = array_merge($result, $params);
        }

        $result["text"] = $data["DESCRIPTION"];

        if (strpos($data["VALUE"], "[popup]") === 0) {
            $popup = str_replace("[popup]", "", $data["VALUE"]);
            $result["popup"] = $popup;
        } else {
            $result["href"] = $data["VALUE"] . ((strpos($data["VALUE"], "#") === 0) ? '" data-anchors-link="' : '');
        }

        return $result;
    }

    /**
     * @param CAllDBResult $arNav
     *
     * @return array
     * @noinspection PhpUnused
     */
    public static function preparePaginator($arNav, $onPageParam = "", $count = 0, $q_search = '', $mark = 0, $model = 0)
    {

        $arResult = [];
        if ($arNav->nEndPage > $arNav->nStartPage) {

            if ($arNav->nStartPage == $arNav->PAGEN) {
                $disabled_prev = true;
            } else {
                $disabled_prev = false;
            }

            $uri = new \Bitrix\Main\Web\Uri($arNav->strListUrl);
            $uri->deleteParams(\Bitrix\Main\HttpRequest::getSystemParameters());

            $sPagen = 'PAGEN_' . $arNav->NavNum;

            $pageNumber = (int)$arNav->PAGEN - 1;
            if ($pageNumber > 1) {
                $uri->addParams([$sPagen => $pageNumber]);
            }

            $addData = [];
            if ($arNav->strQueryText) {
                $addData['q'] = $arNav->strQueryText;
                $addData['how'] = 'r';
            }
            if ($onPageParam) {
                $addData[$onPageParam] = $count;
            }
            if ($q_search) {
                $addData['q'] = $q_search;
            }
            if ($mark) {
                $addData['mark'] = $mark;
            }

            if ($model) {
                $addData['model'] = $model;
            }


            if ($addData) {
                $uri->addParams($addData);
            }
            unset($addData);

            $arResult[] = [
                "text" => "Назад",
                "arrow" => "prev",
                "href" => $uri->getUri(),
                "attr" => "",
                "disabled" => $disabled_prev
            ];

            for ($i = $arNav->nStartPage; $i <= $arNav->nEndPage; $i++) {
                /*$text = $i;
                if ($i<10) {
                    $text = '0'.$i;
                }*/

                $active = $i == $arNav->PAGEN;

                $href = '';
                if (!$active) {
                    if ($i > 1) {
                        $uri->addParams([$sPagen => $i]);
                    } else {
                        $uri->deleteParams([$sPagen]);
                    }
                    $href = $uri->getUri();
                }

                $arResult[] = [
                    "text" => $i,
                    "href" => $href,
                    "attr" => "",
                    "active" => $active
                ];
            }

            if ($arNav->nEndPage == $arNav->PAGEN) {
                $disabled_next = true;
            } else {
                $disabled_next = false;
            }

            if (!$disabled_next) {
                $uri->addParams([$sPagen => $arNav->PAGEN + 1]);
            } else {
                $uri->deleteParams([$sPagen]);
            }

            $arResult[] = [
                "text" => "Вперед",
                "arrow" => "next",
                "href" => $uri->getUri(),
                "attr" => "",
                "disabled" => $disabled_next
            ];

        }
        return $arResult;
    }


    /**
     * @param array $arNav
     *
     * @return array
     * @noinspection PhpUnused
     */
    public static function preparePaginatorNextPage($arNav)
    {

        $arResult = [];
        if ($arNav->nEndPage > $arNav->nStartPage) {

            if ($arNav->nEndPage == $arNav->PAGEN) {
                $disabled_next = true;
            } else {
                $disabled_next = false;
            }

            if (!$disabled_next) {
                $arResult = [
                    "href" => $arNav->strListUrl . '?PAGEN_' . $arNav->NavNum . '=' . ($arNav->PAGEN + 1) . ($arNav->strQueryText ? '&q=' . $arNav->strQueryText . '&how=r' : ''),
                ];
            }

        }
        return $arResult;
    }

    public static function prepareSearchText($sText = '')
    {
        $result = str_replace("<b>", "<em>", $sText);
        $result = str_replace("</b>", "</em>", $result);
        return $result;
    }

    public static function getEndWord($value = 1, $status = array('', 'а', 'ов'))
    {
        $array = array(2, 0, 1, 1, 1, 2);
        return $status[($value % 100 > 4 && $value % 100 < 20) ? 2 : $array[($value % 10 < 5) ? $value % 10 : 5]];
    }

    public static function getTitle()
    {
        global $APPLICATION;
        return $APPLICATION->GetPageProperty('title') ?: $APPLICATION->GetTitle();
    }

    public static function ajaxBufferCatalog($start = true, $buffer = true, $params = [], $ajaxKey = "")
    {
        global $APPLICATION;

        $request = Context::getCurrent()->getRequest();

        if (
            !(empty($ajaxKey) && $request->isAjaxRequest()) &&
            !(!empty($ajaxKey) && $request->get($ajaxKey) == "y")
        ) {
            return false;
        }

        if ($start) {
            $APPLICATION->RestartBuffer();
            if ($buffer) ob_start();
        } else {
            // require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php";
            if (!$buffer) exit();

            $params["removeParams"] = (array)$params["removeParams"];
            if ($ajaxKey) $params["removeParams"][] = $ajaxKey;

            if (!array_key_exists("diffParams", $params)) {
                $params["diffParams"] = true;
            }

            $html = trim(ob_get_clean());

            if (Loader::includeModule("uplab.editor")) {
                if (class_exists(Surrogates::class)) {
                    Surrogates::replaceOnBuffer($html);
                }
            }

            $url = '/catalog/' . $params['PATH'] . '/';

            if ($_GET['mark']) {
                $url .= 'marka-' . self::getCodeById($_GET['mark']) . '/';
            }
            if ($_GET['model']) {
                $url .= 'model-' . self::getCodeById($_GET['model']) . '/';
            }
            if ($_GET['motor']) {
                $url .= 'motor-' . self::getCodeById($_GET['motor']) . '/';
            }
            if ($_GET['ON_PAGE']) {
                $url .= "?ON_PAGE=".$_GET['ON_PAGE'];
            }
            if ($_GET['PAGEN_1']) {
                $url .= "&PAGEN_1=".$_GET['PAGEN_1'];
            }

            $result = [
                "html" => $html,

                "title" => $APPLICATION->GetPageProperty("title"),
                "h1" => htmlspecialchars_decode($APPLICATION->GetTitle(false)),

                "url" => $url,
            ];

            if (!empty($params["data"])) {
                $result = array_merge($result, $params["data"]);
            }

            header('Content-Type: application/json');

            exit(json_encode($result));
        }

        return true;
    }

    public static function getCodeById($id)
    {
        if (!Loader::IncludeModule("iblock")) {
            return;
        }
        return \CIBlockElement::GetByID($id)->fetch()['CODE'] ?: false;
    }

    public static function getIdByCode($code)
    {
        if (!Loader::IncludeModule("iblock")) {
            return;
        }
        return \CIBlockElement::GetList([], ['CODE' => $code], false, ['nTopCount' => 1], ['ID'])->fetch()['ID'] ?: false;
    }

    public static function getNameByCode($code)
    {
        if (!Loader::IncludeModule("iblock")) {
            return;
        }
        return \CIBlockElement::GetList([], ['CODE' => $code], false, ['nTopCount' => 1], ['NAME'])->fetch()['NAME'] ?: false;
    }

    public static function setGlobalProperty($name, $value)
    {
        global $globalProps;
        $globalProps[$name] = $value;
    }

    public static function getGlobalProperty($name)
    {
        global $globalProps;
        return $globalProps[$name];
    }

    public static function executeGlobalActions()
    {
        global $APPLICATION, $globalProps;
        foreach ($globalProps as $name => $value) {
            $APPLICATION->SetPageProperty($name, $value);
        }
    }
}
