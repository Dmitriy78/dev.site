<?php

require_once __DIR__ . '/vendor/autoload.php';

\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    'Only\\Site\\Handlers\\Iblock' => '/local/modules/dev.site/lib/Handlers/Iblock.php',
    'Only\\Site\\Agents\\Iblock' => '/local/modules/dev.site/lib/Agents/Iblock.php',
]);

use Only\Site\Handlers\Iblock;
use Only\Site\Agents\Iblock as AgentClearLog;
use CAgent;

AddEventHandler("iblock", "OnAfterIBlockElementAdd", [Iblock::class, 'addLog']);
AddEventHandler("iblock", "OnAfterIBlockElementUpdate", [Iblock::class, 'addLog']);
