<?php

namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Request;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Type\Date;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Sale\PriceMaths;
use Bitrix\Sale\Registry;
use Bitrix\Crm\Order\Order;
use CUser;

Loc::loadMessages(__FILE__);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/paysystem/context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/paysystem/baseservicehandler.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/paysystem/servicehandler.php';

/**
 * Class FrisbeeHandler
 *
 * @package Sale\Handlers\PaySystem
 */
class FrisbeeHandler extends PaySystem\ServiceHandler
{
    const PRECISION = 2;

    private $prePaymentSetting = [];

    /**
     * @param Payment $payment
     * @param Request|null $request
     * @return PaySystem\ServiceResult
     * @throws Main\ArgumentException
     * @throws Main\ArgumentOutOfRangeException
     * @throws Main\NotImplementedException
     */
    public function initiatePay(Payment $payment, Request $request = null)
    {
        require_once 'lib/FrisbeeService.php';

        $busValues = $this->getParamsBusValue($payment);

        $order = $payment->getOrder();
        $orderId = $order->getId();
        $currency = $order->getCurrency();

        if ($payment->isPaid()) {
            return $this->showTemplate($payment, 'template');
        }

        if (strtoupper($currency) == 'RUR') {
            $currency = 'RUB';
        }

        $frisbeeService = new \FrisbeeService();
        $frisbeeService->setMerchantId($busValues['FRISBEE_MERCHANT_ID']);
        $frisbeeService->setSecretKey($busValues['FRISBEE_SECRET_KEY']);
        $frisbeeService->setRequestParameterOrderId($orderId);
        $frisbeeService->setRequestParameterOrderDescription($this->generateOrderDescriptionParameter($order));
        $frisbeeService->setRequestParameterAmount($payment->getSum());
        $frisbeeService->setRequestParameterCurrency($currency);
        $frisbeeService->setRequestParameterServerCallbackUrl($this->getPathResultUrl($payment));
        $frisbeeService->setRequestParameterResponseUrl($this->getReturnUrl($payment));
        $frisbeeService->setRequestParameterLanguage(Application::getInstance()->getContext()->getLanguage());
        $frisbeeService->setRequestParameterSenderEmail($order->getPropertyCollection()->getUserEmail()->getValue());
        $frisbeeService->setRequestParameterReservationData($this->generateReservationDataParameter($order));

        try {
            $checkoutUrl = $frisbeeService->retrieveCheckoutUrl($orderId);
            $orderStatusProcessing = $this->getOrderStatusProcessingId($busValues);
            $this->setOrderStatusProcessing($order, $orderStatusProcessing);

            if ($checkoutUrl) {
                return LocalRedirect($checkoutUrl, true);
            }

            $message = $frisbeeService->getRequestResultErrorMessage();
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
        }

        $this->setExtraParams([
            'message' => $message,
        ]);

        return $this->showTemplate($payment, 'template');
    }

    /**
     * @return array
     */
    public static function getIndicativeFields()
    {
        return ['FRISBEE_SECRET_KEY', 'FRISBEE_MERCHANT_ID'];
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPaymentIdFromRequest(Request $request)
    {
        return $request->get('orderNumber');
    }

    /**
     * @param Payment $payment
     * @param Request $request
     * @return PaySystem\ServiceResult
     */
    public function processRequest(Payment $payment, Request $request)
    {

    }

    /**
     * @return array
     */
    public function getCurrencyList()
    {
        return ['RUB', 'USD', 'EUR', 'UAH'];
    }

    /**
     * @return mixed|string
     */
    private function getOrderStatusProcessingId($busValues)
    {
        $orderStatusProcessing = 'N';

        if (!empty($busValues['FRISBEE_STATUS_PROCESSING'])) {
            return $busValues['FRISBEE_STATUS_PROCESSING'];
        }

        return $orderStatusProcessing;
    }

    /**
     * @param \Bitrix\Sale\Order $order
     * @param $status
     * @return void
     */
    private function setOrderStatusProcessing(\Bitrix\Sale\Order $order, $status)
    {
        \CSaleOrder::Update($order->getId(), [
            'STATUS_ID' => $status
        ]);
    }

    /**
     * @param Payment $payment
     * @return mixed|string
     */
    private function getPathResultUrl(Payment $payment)
    {
        $url = sprintf('%s://%s/%s', stripos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https' : 'http', $_SERVER['HTTP_HOST'], 'bitrix/tools/frisbee_result/frisbee_result.php');

        return str_replace('&', '&amp;', $url);
    }

    /**
     * @param Payment $payment
     * @return mixed|string
     */
    private function getReturnUrl(Payment $payment)
    {
        return $this->getBusinessValue($payment, 'RESPONSE_URL') ?: $this->service->getContext()->getUrl();
    }

    /**
     * @param \Bitrix\Crm\Order\Order $order
     * @return string
     * @throws \Exception
     */
    private function generateReservationDataParameter($order)
    {
        $userId = $order->getField('USER_ID');

        $propertyCollection = $order->getPropertyCollection();
        $zip = $propertyCollection->getDeliveryLocationZip();

        $reservationData = array(
            'phonemobile' => $propertyCollection->getPhone()->getValue(),
            'customer_address' => $propertyCollection->getItemByOrderPropertyCode('ADDRESS')->getValue(),
            'customer_name' => $propertyCollection->getPayerName()->getValue(),
            'account' => $userId,
            'products' => $this->generateProductsParameter($order),
            'cms_name' => 'Bitrix',
            'cms_version' => defined('SM_VERSION') ? SM_VERSION : '',
            'shop_domain' => $_SERVER['SERVER_NAME'] ?: $_SERVER['HTTP_HOST'],
            'path' => $_SERVER['REQUEST_URI'],
            'uuid' => isset($_SERVER['HTTP_USER_AGENT']) ? base64_encode($_SERVER['HTTP_USER_AGENT']) : time()
        );

        if ($zip) {
            $reservationData['customer_zip'] = $zip->getValue();
        }

        if ($userId) {
            /**
             * @var CUser $rsUser
             */
            $rsUser = CUser::GetByID($userId);
            $arUser = $rsUser->fetch();

            $countryId = !empty($arUser['PERSONAL_COUNTRY']) ? $arUser['PERSONAL_COUNTRY'] : $arUser['WORK_COUNTRY'];
            $state = !empty($arUser['PERSONAL_STATE']) ? $arUser['PERSONAL_STATE'] : $arUser['WORK_STATE'];
            $zip = !empty($arUser['PERSONAL_STATE']) ? $arUser['PERSONAL_STATE'] : $arUser['WORK_STATE'];
            $city = !empty($arUser['PERSONAL_CITY']) ? $arUser['PERSONAL_CITY'] : $arUser['WORK_CITY'];

            if ($countryId) {
                $reservationData['customer_country'] = GetCountryCodeById($countryId);
            }

            if ($state) {
                $reservationData['customer_state'] = $state;
            }

            if (empty($reservationData['customer_zip'])) {
                $reservationData['customer_zip'] = $zip;
            }

            $reservationData['customer_city'] = $city;
        }

        return base64_encode(json_encode($reservationData));
    }

    /**
     * @param \Bitrix\Crm\Order\Order $order
     * @return string
     * @throws \Exception
     */
    private function generateOrderDescriptionParameter($order)
    {
        $description = '';

        /**
         * @var \Bitrix\Crm\Order\BasketItem $item
         */
        foreach ($order->getBasket() as $item) {
            $description .= sprintf('Name: %s ', trim($item->getField('NAME')));
            $description .= sprintf('Price: %s ', $item->getPrice());
            $description .= sprintf('Qty: %s ', $item->getQuantity());
            $description .= sprintf("Amount: %s\n", $item->getFinalPrice());
        }

        return $description;
    }

    /**
     * @param \Bitrix\Crm\Order\Order $order
     * @return array
     * @throws \Exception
     */
    private function generateProductsParameter($order)
    {
        $products = [];

        /**
         * @var \Bitrix\Crm\Order\BasketItem $item
         */
        foreach ($order->getBasket() as $item) {
            $products[] = [
                'id' => $item->getId(),
                'name' => trim($item->getField('NAME')),
                'price' => number_format((float) $item->getPrice(), self::PRECISION),
                'total_amount' => number_format((float) $item->getFinalPrice(), self::PRECISION),
                'quantity' => number_format((float) $item->getQuantity(), self::PRECISION),
            ];
        }

        return $products;
    }
}
