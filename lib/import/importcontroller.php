<?php


namespace Gpart\Local\Import;


use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Gpart\Local\Helper;


class ImportController extends Controller
{
	
	/**
	 * @return array
	 */
	public function configureActions()
	{
		return [
			'importDealers' => [
				'prefilters' => [
					new ActionFilter\Authentication(),
					new ActionFilter\HttpMethod(
						[
							ActionFilter\HttpMethod::METHOD_GET,
							ActionFilter\HttpMethod::METHOD_POST,
						]
					),
					// new ActionFilter\Csrf(),
				],
			],
		];
	}
	
	/**
	 * @param string $filePath
	 * @param int    $startIndex
	 *
	 * @return array
	 */
	public static function importDealersAction($filePath, $startIndex)
	{
		$fileInfo = Helper::getFileInfo($filePath);
		
		try {
			$import = new DealersImport($fileInfo["PATH"], $startIndex);
			$success = $import->import();
			
			$result = [
				"success"   => $success,
				"progress"  => [
					'all' => $import->getRowsCount() - 1,
					'end' => $import->getEndIndex() - 1
				],
				"nextIndex" => $import->getNextIndex(),
				"stats"     => $import->getStats(),
				"items"     => $import->getParsedItems(),
			];
		} catch (\Exception $e) {
			$result = [
				"success" => false,
				"error"   => $e->getMessage(),
			];
		}
		
		return $result;
	}
	
}