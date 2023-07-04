<?

namespace Gpart\Local;


use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Service\GeoIp\Manager;
use CMain;
use CUser;
use Uplab\Core\Renderer;
use Uplab\Core\Traits\EventsTrait;


defined("B_PROLOG_INCLUDED") && B_PROLOG_INCLUDED === true || die();

/**
 * @global CMain $APPLICATION
 * @global CUser $USER
 */
class Events
{
	use EventsTrait;
	
	public static function bindEvents()
	{
		$event = EventManager::getInstance();
		
		$event->addEventHandler("main", "OnProlog", [self::class, "setGlobalData"]);
		
		$event->addEventHandler("main", "OnEpilog", [self::class, "redirect404"]);
		
		 $event->addEventHandler("form", "onAfterResultAdd", [self::class, "formHandler"]);
		// $event->addEventHandler("form", "onAfterResultUpdate", [self::class, "formHandler"]);

        $event->addEventHandler("main", "OnEpilog", [self::class, "onEpilog"]);
        $event->AddEventHandler('main', 'OnEndBufferContent', [__CLASS__, 'OnEndBufferContentHandler']);

    }
	
	public static function setGlobalData()
	{
		Loc::loadMessages(Application::getDocumentRoot() . Helper::DEFAULT_TEMPLATE_PATH . "/lang.php");
		
		if (Context::getCurrent()->getRequest()->isAdminSection()) {
			self::setAdminGlobalData();
		} else {
			self::setPublicGlobalData();
		}
	}
	
	private static function setAdminGlobalData()
	{
	}
	
	private static function setPublicGlobalData()
	{
		$rendererIncludePath = Helper::isDevMode()
			? Helper::DEFAULT_TEMPLATE_PATH . '/frontend/src'
			: '/dist';
		$rendererIncludePathFull = Application::getDocumentRoot() . $rendererIncludePath;
		
		// Дополнительные параметры, передаваемые в шаблон
		Renderer::getInstance()->setRenderParams([
			'defaultTemplatePath' => $rendererIncludePath . '/',
			'placeholder'         => Helper::TRANSPARENT_PIXEL,
			'messages'            => [
				'error' => [
					'required'  => 'Обязательное поле',
					'email'     => 'Введите корректный e-mail адрес',
					'number'    => 'Введите корректное число',
					'url'       => 'Введите корректный URL',
					'tel'       => 'Введите корректный номер телефона',
					'maxlength' => 'This fields length must be < \${1}',
					'minlength' => 'This fields length must be > \${1}',
					'min'       => 'Minimum value for this field is \${1}',
					'max'       => 'Maximum value for this field is \${1}',
					'pattern'   => 'Input must match the pattern \${1}',
				],
			],
			"colors" => [
				"white",
			    "grey",
			    "black",
			    "blue",
			    "blueberry",
			    "pink",
			    "teal",
			    "purple",
			    "green",
			    "orange"
			],
			'breakpoints'         => [
				'md' => 640,
				'lg' => 990
			]
		]);
		
		// Здесь можно изменить список путей, с которыми будет инициализирован Twig
		Renderer::getInstance()->setLoaderPaths([
			$rendererIncludePathFull,
			Application::getDocumentRoot(),
			"template"  => Application::getDocumentRoot() . Helper::DEFAULT_TEMPLATE_PATH,
			"frontend"  => "{$rendererIncludePathFull}",
			"layout"    => "{$rendererIncludePathFull}/include/layout",
			"atoms"     => "{$rendererIncludePathFull}/include/@atoms",
			"molecules" => "{$rendererIncludePathFull}/include/^molecules",
			"organisms" => "{$rendererIncludePathFull}/include/&organisms",
		]);
		
		// Настройки для кастомного тега {% view '' %} в шаблонах Twig
		Renderer\View\ViewTokenParser::getInstance()->setPathParams([
			"srcExt"  => "twig",
			"dataExt" => "json",
			
			"viewsSrc" => "{$rendererIncludePath}/include/%s/%s.%s",
			"replace"  => [
				"~^@~"  => "@atoms/",
				"~^\^~" => "^molecules/",
				"~^&~"  => "&organisms/",
			],
		]);
		
		// Настройки для кастомного тега {% svg '' %} в шаблонах Twig
		Renderer\Svg\SvgTokenParser::getInstance()->setPathParams([
			"src" => [
				"{$rendererIncludePath}/img/%s.svg",
				"%s",
			],
		]);
	}
	
	public static function formHandler($WEB_FORM_ID, $RESULT_ID)
	{
		// запишем в дополнительное поле 'IP' IP-адрес пользователя
		\CFormResult::SetField($RESULT_ID, 'IP', Manager::getRealIp());
	}
	
	/*
	private static function prepareFormAnswers($arAnswer)
	{
		$data = [];

		foreach ($arAnswer as $code => $answer) {
			$answerItem = current($answer);

			if ($answerItem["FIELD_TYPE"] == "file") {
				$value = CFile::GetPath($answerItem["USER_FILE_ID"]);
				$value = $value ? Helper::makeDomainUrl($value) : "";
			} else {
				$value = $answerItem["USER_TEXT"];
			}

			if (!empty($value)) {
				$data[$code] = $value;
			}
		}

		return $data;
	}
	*/

    public static function onEpilog()
    {
        global $APPLICATION;
        $arPageProp = $APPLICATION->GetPagePropertyList();
        if (is_string($arPageProp['PAGE_NUMBER'])){
            $APPLICATION->SetPageProperty("title", $arPageProp['TITLE'] . ' страница ' . $arPageProp['PAGE_NUMBER']);
        }
    }

    /**
     * Создаем хэш страницы и формируем Last-Modified,
     * если отправляется заголовок If-Modified-Since с датой большей, чем в последнем кэшировании,
     * то возвращаем 304 Not Modified
     * @param $content
     * @throws \Exception
     */
    public static function OnEndBufferContentHandler($content)
    {
        global $APPLICATION, $USER;
        $url = $APPLICATION->GetCurPage();
        if (!file_exists($_SERVER['DOCUMENT_ROOT'].'/upload/page_hash/')) {
            mkdir($_SERVER['DOCUMENT_ROOT'].'/upload/page_hash/', 0775, true);
        }
        if (
            !substr_count($url, "/bitrix/")
            && substr_count($content, "</body>")
            && !$USER->IsAdmin()
            && !(defined('ERROR_404') && ERROR_404 == 'Y')
        ) {
            $hashUrl = hash('md5', $url);
            $hashPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/page_hash/' . $hashUrl . '.txt';
            $sliCont = substr($content, 0, strpos($content, "</main>") + 7);
            $sliCont = substr($sliCont, strpos($sliCont, "<main"));
            $hashContent = hash('md5', $sliCont);
            $now = new \DateTime('now', new \DateTimeZone('Etc/GMT+0'));
            $hashArr = ["time" => $now->getTimestamp(), "hash" => $hashContent];
            if (file_exists($hashPath)) {
                $lastArr = json_decode(file_get_contents($hashPath), true);
                if (is_array($lastArr) && $lastArr["hash"] && $lastArr["hash"] == $hashArr["hash"]) {
                    $lastModified = gmdate('D, d M Y H:i:s', $lastArr['time']);
                    header('Last-Modified: ' . $lastModified . ' GMT');
                    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastArr["time"]) {
                        header('HTTP/1.1 304 Not Modified');
                        exit();
                    }
                }
            }
            if (empty($lastModified)) {
                file_put_contents(
                    $hashPath,
                    json_encode($hashArr)
                );
            }
        }
    }
}
