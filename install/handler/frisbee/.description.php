<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\PaySystem;
use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
	die();

Loc::loadMessages(__FILE__);

$isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_TRUE;

$licensePrefix = Loader::includeModule('bitrix24') ? \CBitrix24::getLicensePrefix() : '';
$portalZone = Loader::includeModule('intranet') ? CIntranetUtils::getPortalZone() : '';

$data = [
    'NAME' => 'Frisbee',
	'SORT' => 500,
	'CODES' => [
        'FRISBEE_MERCHANT_ID' => array(
            'NAME' => Loc::getMessage('FRISBEE_MERCHANT'),
            'DESCR' => Loc::getMessage('FRISBEE_MERCHANT_DESC'),
            'VALUE' => '',
            'TYPE' => '',
            'SORT' => 100,
        ),
        'FRISBEE_SECRET_KEY' => array(
            'NAME' => Loc::getMessage('FRISBEE_KEY'),
            'DESCR' => Loc::getMessage('FRISBEE_KEY_DESC'),
            'VALUE' => '',
            'TYPE' => '',
            'SORT' => 200,
        ),
        'FRISBEE_STATUS_PROCESSING' => array(
            'NAME' => Loc::getMessage('FRISBEE_STATUS_PROCESSING'),
            'DESCR' => Loc::getMessage('FRISBEE_STATUS_PROCESSING_DESC'),
            'VALUE' => '',
            'TYPE' => '',
            'SORT' => 200,
        ),
        'FRISBEE_STATUS_APPROVED' => array(
            'NAME' => Loc::getMessage('FRISBEE_STATUS_APPROVED'),
            'DESCR' => Loc::getMessage('FRISBEE_STATUS_APPROVED_DESC'),
            'VALUE' => '',
            'TYPE' => '',
            'SORT' => 200,
        ),
        'FRISBEE_STATUS_CANCELED' => array(
            'NAME' => Loc::getMessage('FRISBEE_STATUS_CANCELED'),
            'DESCR' => Loc::getMessage('FRISBEE_STATUS_CANCELED_DESC'),
            'VALUE' => '',
            'TYPE' => '',
            'SORT' => 200,
        ),
	]
];
