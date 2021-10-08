<?php

define('NOT_CHECK_PERMISSIONS', true);
define('NEED_AUTH', false);

if (!require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php')) {
    die('prolog_before.php not found!');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/paysystem/manager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/paysystemaction.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/entity.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/collectableentity.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/entitymarkerinterface.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/entitymarker.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/businessvalueproviderinterface.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/businessvalue.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/registry.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/orderbase.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/shipmentinterface.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/paymentinterface.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/orderdiscount.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/tradingplatform/order.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/order.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/shipment.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/order.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/servicerestriction.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/services/base/restriction.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/services/base/restrictionmanager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/services/paysystem/restrictions/manager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/payment.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/payment.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/paysystem/baseservicehandler.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/paysystem/servicehandler.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/businessvalue_persondomain.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/persontypesite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/company.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/orderprops.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/persontype.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/helpers/admin/businessvalue.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/handlers/paysystem/frisbee/lib/FrisbeeService.php';

use Bitrix\Sale\Internals\BusinessValueTable;
use Bitrix\Sale\PaySystem\Manager as SalePaySystemManager;
use Bitrix\Sale\PaySystem\Service as SalePaySystemService;
use Bitrix\Sale\Order as SaleOrder;

class FrisbeeResult
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var array
     */
    protected $paySystem;

    public function __construct()
    {
        $this->paySystem = $this->getPaySystem();
        $this->parameters = $this->getPaySystemParameters($this->paySystem['ID']);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function process()
    {
        if (empty($this->parameters['FRISBEE_MERCHANT_ID'])) {
            throw new \Exception('Frisbee merchant id not set.');
        }

        if (!isset($this->parameters['FRISBEE_SECRET_KEY'])) {
            throw new \Exception('Frisbee secret key not set.');
        }

        $frisbeeService = new FrisbeeService();
        $orderStatusProcessing = $this->getOrderStatusProcessing();

        try {
            $data = $frisbeeService->getCallbackData();
            $orderId = $frisbeeService->parseFrisbeeOrderId($data);
            $frisbeeService->setMerchantId($this->parameters['FRISBEE_MERCHANT_ID']);
            $frisbeeService->setSecretKey($this->parameters['FRISBEE_SECRET_KEY']);

            $frisbeeService->handleCallbackData($data);

            /**
             * @var \Bitrix\Sale\Order $order
             */
            $order = $this->findOrder($orderId);
            $currentOrderStatus = $order->getField('STATUS_ID');

            if ($frisbeeService->isOrderDeclined()) {
                $orderStatus = $this->getOrderStatusCanceled();
            } elseif ($frisbeeService->isOrderApproved()) {
                $orderStatus = $this->getOrderStatusApproved();
                $order->getPaymentCollection()->offsetGet(0)->setPaid('Y');
            } elseif ($frisbeeService->isOrderFullyReversed()) {
                $orderStatus = 'R';
            } elseif ($frisbeeService->isOrderPartiallyReversed()) {
                $orderStatus = 'RP';
            } else {
                die;
            }

            $message = $frisbeeService->getStatusMessage();
        } catch (\Exception $exception) {
            $orderStatus = $orderStatusProcessing;
            echo $message = $exception->getMessage();
            http_response_code(500);
        }

        $datetimeFormat = $GLOBALS['DB']->DateFormatToPHP(CSite::GetDateFormat('FULL'));
        $description = sprintf('Frisbee ID: %s Payment ID: %s Message: %s', $data['order_id'], $data['payment_id'], $message);
        $arFields = array(
            'STATUS_ID' => $orderStatus,
            'PAYED' => $orderStatus === 'P' ? 'Y' : 'N',
            'PS_STATUS' => $orderStatus === 'P' ? 'Y' : 'N',
            'PS_STATUS_CODE' => $data['order_status'],
            'PS_STATUS_DESCRIPTION' => $description,
            'PS_STATUS_MESSAGE' => $data['order_status'],
            'PS_SUM' => $data['amount'],
            'PS_CURRENCY' => $data['currency'],
            'PS_RESPONSE_DATE' => date($datetimeFormat),
        );

        if ($frisbeeService->isOrderDeclined()) {
            $arFields['CANCELED'] = 'Y';
            $arFields['DATE_CANCELED'] = date($datetimeFormat);
            $arFields['REASON_CANCELED'] = $message;
        }

        CSaleOrder::Update($orderId, $arFields);
    }

    /**
     * @return mixed|string
     */
    protected function getOrderStatusProcessing()
    {
        if (!empty($this->parameters['FRISBEE_STATUS_PROCESSING'])) {
            return $this->parameters['FRISBEE_STATUS_PROCESSING'];
        }

        return 'N';
    }

    /**
     * @return mixed|string
     */
    protected function getOrderStatusApproved()
    {
        if (!empty($this->parameters['FRISBEE_STATUS_APPROVED'])) {
            return $this->parameters['FRISBEE_STATUS_APPROVED'];
        }

        return 'P';
    }

    /**
     * @return mixed|string
     */
    protected function getOrderStatusCanceled()
    {
        if (!empty($this->parameters['FRISBEE_STATUS_CANCELED'])) {
            return $this->parameters['FRISBEE_STATUS_CANCELED'];
        }

        return 'C';
    }

    /**
     * @return array|false
     */
    protected function getPaySystem()
    {
        return SalePaySystemManager::getByCode('frisbee');
    }

    /**
     * @param $paySystemId
     * @return array
     * @throws \Exception
     */
    protected function getPaySystemParameters($paySystemId): array
    {
        $parameters = [];
        $result = BusinessValueTable::getList(array(
            'select' => array('CODE_KEY', 'CONSUMER_KEY', 'PERSON_TYPE_ID', 'PROVIDER_KEY', 'PROVIDER_VALUE'),
            'filter' => array('CONSUMER_KEY' => SalePaySystemService::PAY_SYSTEM_PREFIX . $paySystemId),
        ));

        foreach ($result->fetchAll() as $parameter) {
            $name = $parameter['CODE_KEY'];
            $parameters[$name] = $parameter['PROVIDER_VALUE'];
        }

        if (count($parameters) === 0) {
            $result = BusinessValueTable::getList(array(
                'select' => array('CODE_KEY', 'CONSUMER_KEY', 'PERSON_TYPE_ID', 'PROVIDER_KEY', 'PROVIDER_VALUE'),
            ));

            foreach ($result->fetchAll() as $parameter) {
                $name = $parameter['CODE_KEY'];
                $parameters[$name] = $parameter['PROVIDER_VALUE'];
            }
        }

        return $parameters;
    }

    /**
     * @param $orderId
     * @return mixed
     */
    protected function findOrder($orderId)
    {
        return SaleOrder::load($orderId);
    }

    /**
     * @param \Bitrix\Sale\Order $order
     * @return \Bitrix\Sale\Payment
     * @throws \Exception
     */
    protected function getPayment(SaleOrder $order)
    {
        /**
         * @var \Bitrix\Sale\Payment $payment
         */
        foreach ($order->getPaymentCollection()->getIterator() as $payment) {
            if (!$payment->isPaid()) {
                return $payment;
            }
        }

        if (isset($payment) && $payment->isPaid()) {
            throw new \Exception(sprintf('Order %d already paid.', $order->getId()));
        }

        throw new \Exception(sprintf('Unable to find payment for order %d.', $order->getId()));
    }
}

$frisbeeResult = new FrisbeeResult();
$frisbeeResult->process();

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
