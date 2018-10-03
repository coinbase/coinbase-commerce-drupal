<?php

namespace Drupal\commerce_coinbasepayments\PluginForm\OffsiteRedirect;

require_once __DIR__ . '/../../Coinbase/init.php';
require_once __DIR__ . '/../../Coinbase/const.php';

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class CoinbaseForm extends PaymentOffsiteForm
{

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $payment = $this->entity;
        /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $paymentGatewayPlugin */
        $paymentGatewayPlugin = $payment->getPaymentGateway()->getPlugin();
        $paymentConfiguration = $paymentGatewayPlugin->getConfiguration();
        /** @var \Drupal\commerce_order\Entity\Order $order */
        $order = $payment->getOrder();
        $entity_manager = \Drupal::entityTypeManager();
        $store = $entity_manager->getStorage('commerce_store')->load($order->getStoreId());
        $totalPrice = $order->getTotalPrice();

        $products = [];
        foreach ($order->getItems() as $item) {
            $products[] = t('@product x @quantity', [
                '@product' => $item->getTitle(),
                '@quantity' => (int) $item->getQuantity()
            ]);
        }

        $chargeData = array(
            'local_price' => array(
                'amount' => $totalPrice->getNumber(),
                'currency' => $totalPrice->getCurrencyCode()
            ),
            'pricing_type' => 'fixed_price',
            'name' => t('@store order #@order_number', ['@order_number' => $order->id(), '@store' => $store->getName()]),
            'description' => implode(',', $products),
            'metadata' => [
                METADATA_SOURCE_PARAM => METADATA_SOURCE_VALUE,
                METADATA_ORDERID_PARAM => $payment->getOrderId(),
                METADATA_CLIENTID_PARAM => $order->getCustomerId(),
                'firstName' => $order->getCustomer()->getName(),
                'email' => $order->getEmail()
            ],
            'redirect_url' => $form['#return_url']
        );

        \Coinbase\ApiClient::init($paymentConfiguration['api_key']);
        $chargeObj = \Coinbase\Resources\Charge::create($chargeData);


        return $this->buildRedirectForm($form, $form_state, $chargeObj->hosted_url, $chargeData, PaymentOffsiteForm::REDIRECT_GET);
    }

}
