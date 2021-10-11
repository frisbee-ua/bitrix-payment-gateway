<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\PaySystem;
use Bitrix\Main\Loader;
use Bitrix\Sale\OrderStatus;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

$isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_TRUE;

$licensePrefix = Loader::includeModule('bitrix24') ? \CBitrix24::getLicensePrefix() : '';
$portalZone = Loader::includeModule('intranet') ? CIntranetUtils::getPortalZone() : '';
$orderStatuses = OrderStatus::getAllStatusesNames();

$data = [
    'NAME' => 'Frisbee',
    'SORT' => 500,
    'CODES' => [
        'FRISBEE_MERCHANT_ID' => array(
            'NAME' => Loc::getMessage('FRISBEE_MERCHANT'),
            'DESCR' => Loc::getMessage('FRISBEE_MERCHANT_DESC'),
            'TYPE' => 'INPUT',
            'SORT' => 100,
            'GROUP' => 'GENERAL_SETTINGS',
        ),
        'FRISBEE_SECRET_KEY' => array(
            'NAME' => Loc::getMessage('FRISBEE_KEY'),
            'DESCR' => Loc::getMessage('FRISBEE_KEY_DESC'),
            'TYPE' => 'INPUT',
            'SORT' => 200,
            'GROUP' => 'GENERAL_SETTINGS',
        ),
        'FRISBEE_STATUS_PROCESSING' => array(
            'NAME' => Loc::getMessage('FRISBEE_STATUS_PROCESSING'),
            'DESCR' => Loc::getMessage('FRISBEE_STATUS_PROCESSING_DESC'),
            'TYPE' => 'SELECT',
            'SORT' => 300,
            'GROUP' => 'PAYMENT',
            'INPUT' => [
                'TYPE' => 'ENUM',
                'OPTIONS' => $orderStatuses,
            ],
        ),
        'FRISBEE_STATUS_APPROVED' => array(
            'NAME' => Loc::getMessage('FRISBEE_STATUS_APPROVED'),
            'DESCR' => Loc::getMessage('FRISBEE_STATUS_APPROVED_DESC'),
            'TYPE' => 'SELECT',
            'SORT' => 400,
            'GROUP' => 'PAYMENT',
            'INPUT' => [
                'TYPE' => 'ENUM',
                'OPTIONS' => $orderStatuses,
            ],
        ),
        'FRISBEE_STATUS_CANCELED' => array(
            'NAME' => Loc::getMessage('FRISBEE_STATUS_CANCELED'),
            'DESCR' => Loc::getMessage('FRISBEE_STATUS_CANCELED_DESC'),
            'TYPE' => 'SELECT',
            'SORT' => 500,
            'GROUP' => 'PAYMENT',
            'INPUT' => [
                'TYPE' => 'ENUM',
                'OPTIONS' => $orderStatuses,
            ],
        ),
    ]
];
