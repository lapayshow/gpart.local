<?php

namespace Gpart\Local\Common\Sync;

use Bitrix\Main\IO\FileNotFoundException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Type\Date;
use CIBlockSection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class CatalogSync
{
    /**
     * @var string
     */
    private $fileSrcData;
    /**
     * @var string
     */
    private $readerType;
    /**
     * @var int
     */
    private $chunkSize = 100;
    /**
     * @var string
     */
    private $uniqueCodePropertyItem = 'ARTICLE';

    /**
     * CatalogSync constructor.
     *
     * @param string $fileSrcData
     *
     * @throws FileNotFoundException
     */
    public function __construct(string $fileSrcData)
    {
        $this->fileSrcData = $this->checkFileExist($fileSrcData);
        $this->readerType = $this->getReaderType($this->getFileExtension($this->fileSrcData));
    }


    /**
     * Возвращает расширение файла из имени файла
     *
     * @param $fileSrcSave
     *
     * @return false|string
     */
    protected function getFileExtension($fileSrcSave)
    {
        $lastPos = strrpos($fileSrcSave, '.');
        return substr($fileSrcSave, $lastPos);
    }

    /**
     * Возвращает класс для работы с файлом
     *
     * @param $fileExtension
     *
     * @return string
     */
    protected function getReaderType($fileExtension)
    {
        $fileExtension = substr($fileExtension, 1);
        return strtoupper($fileExtension[0]) . substr($fileExtension, 1);
    }

    protected function checkFileExist($fileSrcSave)
    {
        if (stripos($fileSrcSave, $_SERVER['DOCUMENT_ROOT']) === false) {
            $fileSrcSave = $_SERVER['DOCUMENT_ROOT'] . $fileSrcSave;
        }
        if (!file_exists($fileSrcSave)) {
            throw new FileNotFoundException($fileSrcSave);
        }

        return $fileSrcSave;
    }

    /**
     * Возвращает информацию о листах в документе
     *
     * @param $fileSrcSave
     *
     * @return array
     * @throws Exception|FileNotFoundException
     */
    protected function getSheetInfo($fileSrcSave)
    {
        $sheetsInfo = [];

        $worksheetData = IOFactory::createReader($this->getReaderType($this->getFileExtension($fileSrcSave)))->listWorksheetInfo($fileSrcSave);
        if ($worksheetData) {
            foreach ($worksheetData as $key => $worksheet) {
                $sheetsInfo[] = $worksheet;
            }
        }

        return $sheetsInfo;
    }

    public function stepGetSheetInfo()
    {
        return $this->getSheetInfo($this->fileSrcData);
    }

    /*Старт*/
    public function stepReadAndWrite($sheetIndex, $countRow)
    {
        //Читаем по листам
        //Todo перенести в нормальное место
        $items = $this->readData($sheetIndex, $countRow);
        $items = $this->prepareData($items);
        return $this->saveData($items);
    }

    /**
     * Заменяем id в колонке марки, на id которые в DB
     *
     * @param $items строки марок из выгрузки
     * @return string
     * @throws LoaderException
     */
    public function getListBrand($items)
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Not loader iblock');
        }
        $iblockId = $this->getBrandIblockId();

        $trimName = array_map('trim', explode(',', $items));
        foreach ($trimName as $name) {
            $model = \CIBlockElement::GetList(false,
                ['IBLOCK_ID' => $iblockId, 'PROPERTY_ID_FILE' => $name], false, false, ['ID'])
                ->Fetch();
            if ($model) {
                $arr[] = $model['ID'];
            }
        }

        if ($arr) {
            return implode(',', $arr);
        }

        return '';
    }

    /**
     * Получаем все модели из DB
     *
     * @param $items string строка названий моделей
     * @return string
     *
     * @throws LoaderException
     */
    public function getModelAll($items)
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Not loader iblock');
        }
        $iblockId = $this->getModelIblockId();
        $arr = [];
        $trimName = array_map('trim', explode(',', $items));
        foreach ($trimName as $name) {
            $model = \CIBlockElement::GetList(false,
                ['IBLOCK_ID' => $iblockId, 'NAME' => $name, 'CODE' => $name], false, false, ['ID', 'NAME', 'CODE'])
                ->Fetch();
            if ($model) {
                $arr[] = $model['ID'];
            } else {
                $arr[] = $this->getModelAdd($name);
            }
        }

        if ($arr) {
            return implode(',', $arr);
        }

        return '';
    }

    /**
     * Создаем модель
     *
     * @param $items string строка названий моделей
     * @return string
     *
     * @throws LoaderException
     */
    public function getModelAdd($items)
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Not loader iblock');
        }
        if ($items) {
            global $USER;
            $userId = $USER->GetID();
            $iblockId = $this->getModelIblockId();
            $date = new Date();
                $el = new \CIBlockElement;
                if ($res = $el->Add([
                    "ACTIVE" => 'Y',
                    "ACTIVE_FROM" => $date,
                    "IBLOCK_ID" => $iblockId,
                    "CODE" => trim($items),
                    "MODIFIED_BY" => $userId,
                    "NAME" => trim($items),
                ])) {
                    return $res;
                }
                if (!$res) {
                    AddMessage2Log($el->LAST_ERROR, 'Ошибка при добавленние моделей');

                    return $el->LAST_ERROR;
                }
        }

        return false;
    }

    /**
     * Получаем все моторы из DB
     *
     * @param $items string строка названий моделей
     * @return string
     *
     * @throws LoaderException
     */
    public function getMotorAll($items)
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Not loader iblock');
        }
        $iblockId = $this->getMotorIblockId();
        $arr = [];
        $trimName = array_map('trim', explode(',', $items));
        foreach ($trimName as $name) {
            $motor = \CIBlockElement::GetList(false,
                ['IBLOCK_ID' => $iblockId, 'NAME' => $name, 'CODE' => $name], false, false, ['ID', 'NAME', 'CODE'])
                ->Fetch();
            if ($motor) {
                $arr[] = $motor['ID'];
            } else {
                $arr[] = $this->getMotorAdd($name);
            }
        }

        if ($arr) {
            return implode(',', $arr);
        }

    }

    /**
     * Создаем список моторов
     *
     * @param $items string строка названий моторов
     * @return string
     *
     * @throws LoaderException
     */
    public function getMotorAdd($items)
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Not loader iblock');
        }
        if ($items) {
            global $USER;
            $userId = $USER->GetID();
            $iblockId = $this->getMotorIblockId();
            $date = new Date();
            $el = new \CIBlockElement;
            if ($res = $el->Add([
                "ACTIVE" => 'Y',
                "ACTIVE_FROM" => $date,
                "IBLOCK_ID" => $iblockId,
                "CODE" => trim($items),
                "MODIFIED_BY" => $userId,
                "NAME" => trim($items),
            ])) {
                return $res;
            }
            if (!$res) {
                AddMessage2Log($el->LAST_ERROR, 'Ошибка при добавленние моторов');

                return $el->LAST_ERROR;
            }
        }

        return false;
    }

    /**
     * Получаем информацию по колонкам из файла
     *
     * @param $sheetIndex
     * @param $countRow
     * @return array
     * @throws Exception
     */
    public function readData($sheetIndex, $countRow)
    {
        $reader = IOFactory::createReader($this->readerType);
        $reader->setLoadSheetsOnly($sheetIndex);

        $chunkSize =& $this->chunkSize;

        $allItems = [];

        //Читаем по $chunkSize данных из файла и проверяем их DB Битрикс

        for ($startRow = 3; $startRow <= $countRow; $startRow += $chunkSize) {
            // Create a new Instance of our Read Filter, passing in the limits on which rows we want to read
            $chunkFilter = new ChunkReadFilter($startRow, $chunkSize);
            // Tell the Reader that we want to use the new Read Filter that we've just Instantiated
            $reader->setReadFilter($chunkFilter);

            // Load only the rows that match our filter from $inputFileName to a PhpSpreadsheet Object
            $worksheet = $reader->load($this->fileSrcData)->getActiveSheet();

            $end = $startRow - 1 + $chunkSize;
            if ($end > $countRow) {
                $end = $countRow;
            }

            $rows = $worksheet->getRowIterator($startRow, $end);

            foreach ($rows as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);
                $rowItems = [];
                foreach ($cellIterator as $cell) {
                    $rowItems[$cell->getColumn()] = $cell->getValue();
                }

                $allItems[] = $rowItems;
            }
            unset($rows);
        }

        return $allItems;
    }


    public function prepareData($items)
    {
        $resItems = [];
        $model = '';
        $motor = '';
        $idBrand = '';
        if ($items) {

            foreach ($items as $arData) {
                $xmlID = trim((string)$arData['E']);
                if ($xmlID) {

                    if (!empty($arData['I'])) {
                        $idBrand = $this->getListBrand($arData['I']);
                    }

                    if (!empty($arData['J'])) {
                        $model = $this->getModelAll($arData['J']);
                    }

                    if (!empty($arData['K'])) {
                        $motor = $this->getMotorAll($arData['K']);
                    }

                    $resItems[$xmlID] = [
                        //Чертежный номер
                        $this->uniqueCodePropertyItem => $xmlID,
                        //Артикул
                        'PARENT' => trim((string)$arData['B']),
                        //2 уровень каталога
                        'SUBSECTION' => trim((string)$arData['C']),
                        //Название товара
                        'NAME' => trim((string)$arData['D']),
                        //Название товара
                        'DRAWING_NUMBER' => trim((string)$arData['E']),
                        //Цифровой номер
                        'DIGITAL_NUMBER' => trim((string)$arData['F']),
                        //Оригинальный номер
                        'ORIGINAL_NUMBER' => trim((string)$arData['H']),
                        //Марка
                        'MARK' => trim($idBrand),
                        //Модель
                        'MODEL' => trim($model),
                        //Тип двигателя
                        'MOTOR' => trim($motor),
                        //Д*Ш*В, мм
                        'PARAMETRS' => trim((string)$arData['L']),
                        //Вес
                        'WHEIGHT' => trim((string)$arData['M']),
                        //Описание
                        'DESCRIPTION' => trim((string)$arData['N']),
                        //Комплектность
                        'COMPLECT' => trim((string)$arData['O']),
                        //Фото
                        'PHOTO' => trim((string)$arData['P']),
                        //Цена
                        'PRICE' => trim((string)$arData['R'])
                    ];
                    $resItems[$xmlID]['HASH'] = $this->getHashItem($resItems[$xmlID]);
                }
                unset($model, $motor);
            }
        }
        return $resItems;
    }

    /**
     * Создаем символьный код для ЧПУ
     *
     * @param $value string символьынй код
     * @return string
     */
    public function getTranslit($value)
    {
        $converter = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        );

        $value = mb_strtolower($value);
        $value = strtr($value, $converter);
        $value = mb_ereg_replace('[^-0-9a-z]', '-', $value);
        $value = mb_ereg_replace('[-]+', '-', $value);
        $value = trim($value, '-');

        return $value;
    }

    /**
     * Обновляем/добавляем  подразделы
     * @param $subSectionName string название категории 2 уровня
     * @param $parentId integer id родительского раздела
     * @return string
     * @throws LoaderException
     */
    public function getSubSection($subSectionName, $parentId)
    {
        $iblockId = $this->getCatalogIblockId();
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Not loader iblock');
        }
        $section = new CIBlockSection;

        //получаем  подраздел
        $subSection = $section::GetList(false,
            [
                'IBLOCK_ID' => $iblockId,
                'NAME' => $subSectionName,
                'IBLOCK_SECTION_ID' => $parentId,
                'DEPTH_LEVEL' => 2,

            ],
            false,
            ['ID', 'IBLOCK_SECTION_ID', 'CODE']
        )->Fetch();

        $id = $subSection['ID'];

        $arFields = [
            'IBLOCK_ID' => $iblockId,
            'NAME' => $subSectionName,
            'IBLOCK_SECTION_ID' => $parentId,
            'DEPTH_LEVEL' => 2,
            'CODE' => $this->getTranslit($subSectionName),
        ];
        if ($id) {
            return $id;
        }

        $id = $section->Add($arFields);
        $res = ($id > 0);

        if (!$res) {
            AddMessage2Log($section->LAST_ERROR, 'Ошибка при создание подраздела');
            return "ERROR: " . $section->LAST_ERROR;
        }

        return $id;
    }

    /**
     * Обновляем/добавляем родительские разделы
     * @param $nameParent string  навзвание раздела 1 уровня
     * @return array
     * @throws LoaderException
     */
    private function getSectionsInfo($nameParent)
    {
        $iblockId = $this->getCatalogIblockId();
        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Not loader iblock');
        }
        $section = new CIBlockSection;

        //получаем родительский раздел
        $sectionParent = $section::GetList(false,
            [
                'IBLOCK_ID' => $iblockId,
                'NAME' => $nameParent,
                'DEPTH_LEVEL' => 1,
//                'CODE' => $this->getTranslit($nameParent),
            ],
            false,
            ['ID', 'IBLOCK_SECTION_ID', 'CODE']
        )->Fetch();

        $id = $sectionParent['ID'];

        $arFields = [
            'IBLOCK_ID' => $iblockId,
            'NAME' => $nameParent,
            'DEPTH_LEVEL' => 1,
            'CODE' => $this->getTranslit($nameParent),
        ];
        if ($id) {
            return $id;
        }
        $id = $section->Add($arFields);
        $res = ($id > 0);
        if (!$res) {
            AddMessage2Log($section->LAST_ERROR, 'Ошибка при создание родительского раздела');

            return $section->LAST_ERROR;
        }

        return $id;
    }

    public function saveData($items)
    {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Not loader iblock');
        }
        $errors = [];

        $countUpdateItems = 0;
        $countAddItems = 0;
        $allItems = count($items);

        if ($items) {
            //получим список элемнтов, что сейчас есть в БД
            $xmlIds = array_keys($items);

            $iblockId = $this->getCatalogIblockId();

            $exsitItems = [];
            $res = \CIBlockElement::GetList(false, ['=PROPERTY_' . $this->uniqueCodePropertyItem => $xmlIds, 'IBLOCK_ID' => $iblockId], false, false, ['ID', 'PROPERTY_' . $this->uniqueCodePropertyItem, 'PROPERTY_HASH']);
            while ($item = $res->Fetch()) {
                $xmlID = $item['PROPERTY_' . $this->uniqueCodePropertyItem . '_VALUE'];
                if ($xmlID) {
                    $item = [
                        'ID' => $item['ID'],
                        $this->uniqueCodePropertyItem => $xmlID,
                        'HASH' => $item['PROPERTY_HASH_VALUE']
                    ];
                    $exsitItems[$xmlID] = $item;
                }
            }
            if ($exsitItems) {
                $needUpdate = [];
                //проверим HASH
                foreach ($exsitItems as $xmlId => $arItem) {
                    if ($items[$xmlId]['HASH'] !== $arItem['HASH']) {
                        $needUpdate[$xmlId] = $items[$xmlId];
                        $needUpdate[$xmlId]['ID'] = $arItem['ID'];
                    }
                    unset($items[$xmlId]);
                }

                if ($needUpdate) {
                    global $USER;
                    $userId = $USER->GetID();
                    foreach ($needUpdate as $xmlId => $arItem) {
                        $elementId = $arItem['ID'];
                        \CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, [
                            'HASH' => $arItem['HASH'],
                            'DIGITAL_NUMBER' => $arItem['DIGITAL_NUMBER'],
                            'DRAWING_NUMBER' => $arItem['DRAWING_NUMBER'],
                            'ORIGINAL_NUMBER' => $arItem['ORIGINAL_NUMBER'],
                            'MARK' => explode(',', $arItem['MARK']),
                            'MODEL' => explode(',', $arItem['MODEL']),
                            'MOTOR' => explode(',', $arItem['MOTOR']),
                            'PARAMETRS' => $arItem['PARAMETRS'],
                            'WHEIGHT' => $arItem['WHEIGHT'],
                            'DESCRIPTION' => $arItem['DESCRIPTION'],
                            'COMPLECT' => $arItem['COMPLECT'],
                            'PHOTO' => $arItem['PHOTO'],
                            'PRICE' => $arItem['PRICE'],
                            $this->uniqueCodePropertyItem => $arItem[$this->uniqueCodePropertyItem]
                        ]);

                        $el = new \CIBlockElement;
                        $res = $el->Update($elementId, [
                            "MODIFIED_BY" => $userId,
                            "NAME" => $arItem['NAME']
                        ]);
                        if (!$res) {
                            $errors[] = $el->LAST_ERROR;
                        } else {
                            $countUpdateItems++;
                        }
                    }
                    unset($needUpdate);
                }
            }

            if ($items) {
                $groupError = [];
                $date = new Date();
                foreach ($items as $xmlId => $arItem) {
                    if (!empty($arItem['PARENT'])) {
                        $parent = $this->getSectionsInfo($arItem['PARENT']);
                        $subSection = $this->getSubSection($arItem['SUBSECTION'], (int)$parent);
                        $el = new \CIBlockElement;
                        $res = $el->Add([
                            "ACTIVE" => 'Y',
                            "ACTIVE_FROM" => $date,
                            "IBLOCK_ID" => $iblockId,
                            "IBLOCK_SECTION_ID" => $subSection,
                            "CODE" => $xmlId,
                            "MODIFIED_BY" => $userId,
                            "NAME" => $arItem['NAME'],
                            'PROPERTY_VALUES' => [
                                'HASH' => $arItem['HASH'],
                                //Артикул
                                'PARENT' => $arItem['PARENT'],
                                //2 уровень каталога
                                'SUBSECTION' => $arItem['SUBSECTION'],
                                //Чертежный номер
                                'DRAWING_NUMBER' => $arItem['DRAWING_NUMBER'],
                                //Цифровой номер
                                'DIGITAL_NUMBER' => $arItem['DIGITAL_NUMBER'],
                                //Оригинальный номер
                                'ORIGINAL_NUMBER' => $arItem['ORIGINAL_NUMBER'],
                                //Марка
                                'MARK' => explode(',', $arItem['MARK']),
                                //Модель
                                'MODEL' => explode(',', $arItem['MODEL']),
                                //Тип двигателя
                                'MOTOR' => explode(',', $arItem['MOTOR']),
                                //Д*Ш*В, мм
                                'PARAMETRS' => $arItem['PARAMETRS'],
                                //Вес
                                'WHEIGHT' => $arItem['WHEIGHT'],
                                //Описание
                                'DESCRIPTION' => $arItem['DESCRIPTION'],
                                //Комплектность
                                'COMPLECT' => $arItem['COMPLECT'],
                                //Фото
                                'PHOTO' => $arItem['PHOTO'],
                                //Цена
                                'PRICE' => $arItem['PRICE'],
                                $this->uniqueCodePropertyItem => $arItem[$this->uniqueCodePropertyItem]
                            ]
                        ]);
                        if (!$res) {
                            AddMessage2Log($el->LAST_ERROR, 'Ошибка при создание товара');
                            $errors[] = $el->LAST_ERROR;
                        } else {
                            $countAddItems++;
                        }


                        if ($groupError) {
                            $s = '';
                            foreach ($groupError as $groupName => $count) {
                                $s .= $groupName . "($count), ";
                            }
                            $s = substr($s, 0, -2);
                            $errors[] = "Не удалось добавить элементы с группами: " . $s;
                        }
                        unset($groupError);
                    }
                }
            }
        }

        return [
            '$allItems' => $allItems,
            '$countUpdateItems' => $countUpdateItems,
            '$countAddItems' => $countAddItems,
            '$errors' => $errors,
        ];
    }

    /**
     * @param $arItem
     *
     * @return string
     */
    private function getHashItem($arItem)
    {
        return md5(serialize($arItem));
    }


    public function readItemsInFile()
    {

    }

    private function getCatalogIblockId()
    {
        return (int)CATALOG_IBLOCK;
    }

    private function getModelIblockId()
    {
        return (int)MODEL_IBLOCK;
    }

    private function getBrandIblockId()
    {
        return (int)BRAND_IBLOCK;
    }

    private function getMotorIblockId()
    {
        return (int)MOTOR_IBLOCK;
    }

}