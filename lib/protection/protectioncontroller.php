<?php


namespace Gpart\Local\Protection;


use Bitrix\Main\Application;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Text\Encoding;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Web\Json;


class ProtectionController extends Controller
{

	/**
	 * @return array
	 */
	public function configureActions()
	{
		return [
			"check" => [
				"prefilters" => [
					new ActionFilter\HttpMethod(
						[
							ActionFilter\HttpMethod::METHOD_POST,
							ActionFilter\HttpMethod::METHOD_POST,
						]
					),
					new ActionFilter\Csrf(),
				],
			],
		];
	}

	public static function checkAction($number)
	{
		$number = preg_replace("~\D~", "", $number);

		if (
			!isset($number)
			|| trim($number) < 50000000
			|| trim($number) > 65000000
			|| !is_numeric($number)
		) {

			$result = [
				"error" => true,
				"text"  => "Некорректный VIN-номер",
			];

		} else {
			$url = "http://region.gaz.ru/spareparts/OriginalityCheck.aspx?id=" . $number;

			$client = new HttpClient();
			$client->setCharset("windows-1251");
			$client->get($url);
			$response = Encoding::convertEncoding($client->getResult(), "CP-1251", "UTF-8");

			if (strpos($response, "Возможно запчасть поддельная") !== false) {

				$result = [
					"question" => true,
					"text"     => "Возможно запчасть поддельная",
				];

			} elseif (strpos($response, "Запчасть не является оригинальной") !== false) {

				$result = [
					"error" => true,
					"text"  => "Запчасть не является оригинальной",
				];

			} else {

				$result = [
					"success" => true,
					"text"    => "Запчасть является оригинальной",
				];

			}
		}

		$result["number"] = $number;

		return $result;
	}

}
