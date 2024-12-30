<?php

namespace Only\Site\Agents;

use CIBlock;
use CIBlockElement;

class Iblock {

    public static $iBlockLogName = 'LOG';
    public static $iBlockLogId;
    public static $maxCount = 3;

    public static function clearOldLogs() {

        if (!\Bitrix\Main\Loader::includeModule("iblock")) {
            return; // Завершаем выполнение, если модуль недоступен
        }

        self::$iBlockLogId = self::getiBlockLogId();

        // нет инфоблока Log
        if (!isset(self::$iBlockLogId)) {
            return '\\' . __CLASS__ . '::' . __FUNCTION__ . '();';
        }

        // получает все элементы инфоблока Log
        $res = CIBlockElement::GetList(
                ['ID' => 'desc'],
                ['IBLOCK_ID' => self::$iBlockLogId],
                false,
                false,
                ['ID']
        );

        $arr = [];

        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $arr[] = $arFields['ID'];
        }

        // кол-во меньше/равно допустимого
        if (count($arr) <= self::$maxCount) {
            return;
        }

        // удаление элементов
        foreach ($arr as $key => $item) {
            if ($key >= self::$maxCount) {
                CIBlockElement::Delete($item);
            }
        }

//        return "Only\Site\Agents\Iblock::clearOldLogs();";
        return '\\' . __CLASS__ . '::' . __FUNCTION__ . '();';
    }

    /**
     *
     * @return int|bool
     */
    public static function getiBlockLogId(): int|bool {

        // !!! не работает в агенте !!!
//        $iBlock = CIBlock::GetList(
//                [],
//                [
//                    'CODE' => self::$iBlockLogName
//                ]
//        );
//        $ar_res = $iBlock->GetNext();
//        return $ar_res['ID'] ?? false;

        $connection = \Bitrix\Main\Application::getConnection();

        $sql = "SELECT ID FROM b_iblock WHERE CODE = '" . self::$iBlockLogName . "'";
        $recordset = $connection->query($sql);

        while ($record = $recordset->fetch()) {
            return $record['ID'];
        }
    }

    public static function example() {
        global $DB;
        if (\Bitrix\Main\Loader::includeModule('iblock')) {
            $iblockId = \Only\Site\Helpers\IBlock::getIblockID('QUARRIES_SEARCH', 'SYSTEM');
            $format = $DB->DateFormatToPHP(\CLang::GetDateFormat('SHORT'));
            $rsLogs = \CIBlockElement::GetList(['TIMESTAMP_X' => 'ASC'], [
                    'IBLOCK_ID' => $iblockId,
                    '<TIMESTAMP_X' => date($format, strtotime('-1 months')),
                    ], false, false, ['ID', 'IBLOCK_ID']);
            while ($arLog = $rsLogs->Fetch()) {
                \CIBlockElement::Delete($arLog['ID']);
            }
        }
        return '\\' . __CLASS__ . '::' . __FUNCTION__ . '();';
    }
}
