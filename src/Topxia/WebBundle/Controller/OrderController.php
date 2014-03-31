<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Service\Common\ServiceKernel;
use Topxia\Component\Payment\Payment;

class OrderController extends BaseController
{

    public function submitPayRequestAction(Request $request , $order, $requestParams)
    {
        $paymentRequest = $this->createPaymentRequest($order, $requestParams);

        return $this->render('TopxiaWebBundle:Order:submit-pay-request.html.twig', array(
            'form' => $paymentRequest->form(),
            'order' => $order,
        ));
    }

    protected function doPayReturn(Request $request, $name, $successCallback = null, $successUrl = null)
    {
        $this->getLogService()->info('order', 'pay_result',  "{$name}页面跳转支付通知", $request->query->all());
        $response = $this->createPaymentResponse($name, $request->query->all());

        $payData = $response->getPayData();
        $order = $this->getOrderService()->payOrder($payData);

        if ($order['status'] == 'paid' and $successCallback) {
            $successCallback($order);
        }

        $goto = empty($successUrl) ? $this->generateUrl('homepage', array(), true) : $successUrl;

        return $this->redirect($goto);
    }

    protected function doPayNotify(Request $request, $name, $successCallback = null)
    {
        $this->getLogService()->info('order', 'pay_result', "{$name}服务器端支付通知", $request->request->all());
        $response = $this->createPaymentResponse($name, $request->request->all());

        $payData = $response->getPayData();
        try {
            $order = $this->getOrderService()->payOrder($payData);
            if ($order['status'] == 'paid' and $successCallback) {
                $successCallback($order);
            }

            return new Response('success');
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function createPaymentRequest($order, $requestParams)
    {
        $options = $this->getPaymentOptions($order['payment']);
        $request = Payment::createRequest($order['payment'], $options);

        $requestParams = array_merge($requestParams, array(
            'orderSn' => $order['sn'],
            'title' => $order['title'],
            'summary' => '',
            'amount' => $order['amount'],
        ));

        return $request->setParams($requestParams);
    }

    private function createPaymentResponse($name, $params)
    {
        $options = $this->getPaymentOptions($name);
        $response = Payment::createResponse($name, $options);

        return $response->setParams($params);
    }


    private function getPaymentOptions($payment)
    {
        $settings = $this->setting('payment');

        if (empty($settings)) {
            throw new \RuntimeException('支付参数尚未配置，请先配置。');
        }

        if (empty($settings['enabled'])) {
            throw new \RuntimeException("支付模块未开启，请先开启。");
        }

        if (empty($settings[$payment. '_enabled'])) {
            throw new \RuntimeException("支付模块({$payment})未开启，请先开启。");
        }

        if (empty($settings["{$payment}_key"]) or empty($settings["{$payment}_secret"])) {
            throw new \RuntimeException("支付模块({$payment})参数未设置，请先设置。");
        }

        $options = array(
            'key' => $settings["{$payment}_key"],
            'secret' => $settings["{$payment}_secret"],
            'type' => $settings["{$payment}_type"]
        );

        return $options;
    }

}