<?php

namespace Only\Site\Handlers;

//use Bitrix\Main\Loader;
use CIBlock;
use CIBlockElement;
use CIBlockSection;

class Iblock {

    private static $iBlockLogName = 'LOG';
    private static $iBlockLogId;

    public function __construct() {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception("Модуль инфоблоков не подключен.");
        }
    }

    public static function addLog(&$arFields) {

        // Здесь напиши свой обработчик
        // получить ID инфоблока LOG
        self::$iBlockLogId = self::getiBlockLogId();

        // нет инфоблока LOG
        // или редактируемый элемент является элементом инфоблока LOG
        if (!self::$iBlockLogId | $arFields['IBLOCK_ID'] == self::$iBlockLogId) {
            return;
        }

        // элемент который добавляем/редактируем
        $element = CIBlockElement::GetByID($arFields['ID']);
        $el = $element->GetNext();

        // массив разделов элемента
        $elementSectionArray = [];

        // эдемент имеет раздел
        if ($el['IBLOCK_SECTION_ID']) {
            self::getElementSectionList($elementSectionArray, $el['IBLOCK_SECTION_ID']);

            $elementSectionArray = array_reverse($elementSectionArray);
        }

        // инфоблок элемента
        $block = CIBlock::GetByID($el['IBLOCK_ID']);
        $bl = $block->GetNext();

        // разделы в Log
        $logSectionsList = self::getLogSectionsList();

        // имя раздела в LOG для элемента
        $sectionName = implode(' ', [
            $bl['NAME'],
            $bl['CODE']
        ]);

        // поиск раздела по имени в массиве разделов
        $sectionId = array_search($sectionName, $logSectionsList);

        // если нет раздела, создаем его
        if (!$sectionId) {
            $sectionId = self::addLogSection($sectionName);
        }

        // добавляем имя бока и элемента для описание в анонсе
        array_unshift($elementSectionArray, $bl['NAME']);
        array_push($elementSectionArray, $el['NAME']);

//        dd($arFields, $el, $bl, $sectionName, $logSectionsList, $elementSectionArray, $sectionId);
        // добавить лог
        $newLogElementId = self::addLogElement($el['ID'], $sectionId,
                $el['DATE_CREATE'], $elementSectionArray);

        if (!$newLogElementId) {
            dd($newLogElement->LAST_ERROR);
        }
    }

    /**
     *
     * @param array $elementSectionArray
     * @param int $sectionId
     */
    public static function getElementSectionList(array &$elementSectionArray, int $sectionId) {
        $res = CIBlockSection::GetByID($sectionId);
        if ($ar_res = $res->GetNext()) {
            $elementSectionArray[] = $ar_res['NAME'];
        }

        if ($ar_res['IBLOCK_SECTION_ID']) {
            self::getElementSectionList($elementSectionArray, $ar_res['IBLOCK_SECTION_ID']);
        }
    }

    /**
     *
     * @return int|bool
     */
    public static function getiBlockLogId(): int|bool {
        $iBlock = CIBlock::GetList(
                [],
                [
                    'CODE' => self::$iBlockLogName
                ]
        );
        $ar_res = $iBlock->GetNext();

        return $ar_res['ID'] ?? false;
    }

    /**
     *
     * @return array
     */
    public static function getLogSectionsList(): array {
        $logSectionsList = CIBlockSection::GetList(
                [],
                [
                    'IBLOCK_ID' => self::$iBlockLogId
                ]
        );

        $sectionsList = [];

        while ($section = $logSectionsList->Fetch()) {
            $sectionsList[$section['ID']] = $section['NAME'];
        }

        return $sectionsList;
    }

    /**
     *
     * @param string $name
     * @return int|false
     */
    public static function addLogSection(string $name): int|false {
        $newSection = new CIBlockSection();

        return $newSection->Add([
                "NAME" => $name,
                "IBLOCK_ID" => self::$iBlockLogId,
        ]);
    }

    /**
     *
     * @param string $name
     * @param int $sectionId
     * @param string $from
     * @param array $elementSectionArray
     * @return int|false
     */
    public static function addLogElement(string $name, int $sectionId,
        string $from, array $elementSectionArray): int|false {

        $newLogElement = new CIBlockElement();

        return $newLogElement->Add([
                'NAME' => $name,
                'IBLOCK_ID' => self::$iBlockLogId,
                'IBLOCK_SECTION_ID' => $sectionId,
                'ACTIVE_FROM' => $from,
                'PREVIEW_TEXT' => implode('->', $elementSectionArray)
        ]);
    }

    public static function OnBeforeIBlockElementAddHandler(&$arFields) {
        $iQuality = 95;
        $iWidth = 1000;
        $iHeight = 1000;
        /*
         * Получаем пользовательские свойства
         */
        $dbIblockProps = \Bitrix\Iblock\PropertyTable::getList(array(
                'select' => array('*'),
                'filter' => array('IBLOCK_ID' => $arFields['IBLOCK_ID'])
        ));
        /*
         * Выбираем только свойства типа ФАЙЛ (F)
         */
        $arUserFields = [];
        while ($arIblockProps = $dbIblockProps->Fetch()) {
            if ($arIblockProps['PROPERTY_TYPE'] == 'F') {
                $arUserFields[] = $arIblockProps['ID'];
            }
        }
        /*
         * Перебираем и масштабируем изображения
         */
        foreach ($arUserFields as $iFieldId) {
            foreach ($arFields['PROPERTY_VALUES'][$iFieldId] as &$file) {
                if (!empty($file['VALUE']['tmp_name'])) {
                    $sTempName = $file['VALUE']['tmp_name'] . '_temp';
                    $res = \CAllFile::ResizeImageFile(
                            $file['VALUE']['tmp_name'],
                            $sTempName,
                            array("width" => $iWidth, "height" => $iHeight),
                            BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
                            false,
                            $iQuality);
                    if ($res) {
                        rename($sTempName, $file['VALUE']['tmp_name']);
                    }
                }
            }
        }

        if ($arFields['CODE'] == 'brochures') {
            $RU_IBLOCK_ID = \Only\Site\Helpers\IBlock::getIblockID('DOCUMENTS', 'CONTENT_RU');
            $EN_IBLOCK_ID = \Only\Site\Helpers\IBlock::getIblockID('DOCUMENTS', 'CONTENT_EN');
            if ($arFields['IBLOCK_ID'] == $RU_IBLOCK_ID || $arFields['IBLOCK_ID'] == $EN_IBLOCK_ID) {
                \CModule::IncludeModule('iblock');
                $arFiles = [];
                foreach ($arFields['PROPERTY_VALUES'] as $id => &$arValues) {
                    $arProp = \CIBlockProperty::GetByID($id, $arFields['IBLOCK_ID'])->Fetch();
                    if ($arProp['PROPERTY_TYPE'] == 'F' && $arProp['CODE'] == 'FILE') {
                        $key_index = 0;
                        while (isset($arValues['n' . $key_index])) {
                            $arFiles[] = $arValues['n' . $key_index++];
                        }
                    } elseif ($arProp['PROPERTY_TYPE'] == 'L' && $arProp['CODE'] == 'OTHER_LANG' && $arValues[0]['VALUE']) {
                        $arValues[0]['VALUE'] = null;
                        if (!empty($arFiles)) {
                            $OTHER_IBLOCK_ID = $RU_IBLOCK_ID == $arFields['IBLOCK_ID'] ? $EN_IBLOCK_ID : $RU_IBLOCK_ID;
                            $arOtherElement = \CIBlockElement::GetList([],
                                    [
                                        'IBLOCK_ID' => $OTHER_IBLOCK_ID,
                                        'CODE' => $arFields['CODE']
                                    ], false, false, ['ID'])
                                ->Fetch();
                            if ($arOtherElement) {
                                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                                \CIBlockElement::SetPropertyValues($arOtherElement['ID'], $OTHER_IBLOCK_ID, $arFiles, 'FILE');
                            }
                        }
                    } elseif ($arProp['PROPERTY_TYPE'] == 'E') {
                        $elementIds = [];
                        foreach ($arValues as &$arValue) {
                            if ($arValue['VALUE']) {
                                $elementIds[] = $arValue['VALUE'];
                                $arValue['VALUE'] = null;
                            }
                        }
                        if (!empty($arFiles && !empty($elementIds))) {
                            $rsElement = \CIBlockElement::GetList([],
                                    [
                                        'IBLOCK_ID' => \Only\Site\Helpers\IBlock::getIblockID('PRODUCTS', 'CATALOG_' . $RU_IBLOCK_ID == $arFields['IBLOCK_ID'] ? '_RU' : '_EN'),
                                        'ID' => $elementIds
                                    ], false, false, ['ID', 'IBLOCK_ID', 'NAME']);
                            while ($arElement = $rsElement->Fetch()) {
                                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                                \CIBlockElement::SetPropertyValues($arElement['ID'], $arElement['IBLOCK_ID'], $arFiles, 'FILE');
                            }
                        }
                    }
                }
            }
        }
    }
}
