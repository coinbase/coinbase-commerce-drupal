<?php

namespace Drupal\commerce_coinbase_payments;

require_once __DIR__ . '/Coinbase/autoload.php';
require_once __DIR__ . '/Coinbase/const.php';

use CoinbaseCommerce\Webhook;
use CoinbaseCommerce\Resources\Charge;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Entity\Order;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\commerce_price\Price;

class Ipn implements IpnInterface
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * Ipn constructor.
     * @param Connection $connection
     * @param EntityTypeManagerInterface $entityTypeManager
     * @param LoggerInterface $logger
     * @param ConfigFactoryInterface $configFactory
     */
    public function __construct(Connection $connection, EntityTypeManagerInterface $entityTypeManager, LoggerInterface $logger, ConfigFactoryInterface $configFactory)
    {
        $this->connection = $connection;
        $this->entityTypeManager = $entityTypeManager;
        $this->logger = $logger;
        $this->configFactory = $configFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request)
    {
        $body = $request->getContent();
        $bodyArray = \json_decode($request->getContent(), true);

        if (!isset($bodyArray['event']['data']['id'])) {
            throw new BadRequestHttpException('Invalid payload provided.');
            return false;
        }


        $charge = new Charge($bodyArray['event']['data']);
        $order = $this->load_order($charge->getMetadataParam(METADATA_ORDER_ID_PARAM));

        $paymentGateway = $order->payment_gateway->entity;

        try {
            $config = $paymentGateway->get('configuration');
            $sigHeader = $request->headers->get(SIGNATURE_HEADER);
            Webhook::verifySignature($body, $sigHeader, $config['secret_key']);
        } catch (\Exception $exception) {
            $this->logger->warning($exception->getMessage());
            throw new BadRequestHttpException();
            return false;
        }

        $this->update_transaction($order, $charge, $paymentGateway->get('id'));
        $lastTimeLine = end($charge->timeline);

        switch ($lastTimeLine['status']) {
            case 'RESOLVED':
            case 'COMPLETED':
                $this->apply_order_transition($order, 'place');
                return;
            case 'UNRESOLVED':
                // mark order as paid on overpaid
                if ($lastTimeLine['context'] === 'OVERPAID') {
                    $this->apply_order_transition($order, 'place');
                }
                return;
            case 'CANCELED':
            case 'EXPIRED':
                $this->apply_order_transition($order, 'cancel');
                return;
        }
    }

    private function update_transaction($order, $charge, $paymentGateway)
    {
        $total = null;
        $currency = null;

        foreach ($charge->payments as $payment) {
            if (strtolower($payment['status']) === 'confirmed') {
                $total = strval($payment['value']['local']['amount']);
                $currency = $payment['value']['local']['currency'];
                break;
            }
        }

        if (is_null($total) || is_null($currency)) {
            return;
        }

        $price = new Price($total, $currency);
        $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');
        $transactionArray = $paymentStorage->loadByProperties(['order_id' => $order->id()]);

        if (!empty($transactionArray)) {
            $transaction = array_shift($transactionArray);
        } else {
            $transaction = $paymentStorage->create([
                'payment_gateway' => $paymentGateway,
                'order_id' => $order->id(),
                'remote_id' => $charge['id']
            ]);
        }

        $lastTimeLine = end($charge->timeline);
        $transaction->setRemoteState($lastTimeLine['status']);
        $transaction->setState('completed');
        $transaction->setAmount($price);
        $paymentStorage->save($transaction);
    }

    private function apply_order_transition($order, $orderTransition)
    {
        $order_state = $order->getState();
        $order_state_transitions = $order_state->getTransitions();
        if (!empty($order_state_transitions) && isset($order_state_transitions[$orderTransition])) {
            $order_state->applyTransition($order_state_transitions[$orderTransition]);
            $order->save();
        }
    }

    private function load_order($orderId)
    {
        $order = Order::load($orderId);

        if (!$order) {
            $this->logger->warning(
                'Not found order with id @order_id.',
                ['@order_id' => $orderId]
            );
            throw new BadRequestHttpException();

            return false;
        }

        return $order;
    }
}
