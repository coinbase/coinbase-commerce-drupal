<?php

namespace Drupal\commerce_coinbasepayments;

require_once __DIR__ . '/Coinbase/init.php';
require_once __DIR__ . '/Coinbase/const.php';

use Coinbase\Webhook;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Entity\Order;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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

        if (!isset($bodyArray['event'])) {
            throw new BadRequestHttpException('Invalid payload provided.');

            return false;
        }

        $event = new \Coinbase\Resources\Event($bodyArray['event']);
        $charge = $event->data;
        $orderId = $charge->getMetadataParam(METADATA_ORDERID_PARAM);
        $clientId = $charge->getMetadataParam(METADATA_CLIENTID_PARAM);

        $order = $this->load_order($orderId);
        $paymentGateway = $order->get('payment_gateway')->getString();

        if (null === $order) {
            $this->logger->warning('Not found order with id @order_id and client with id @client_id.', ['@order_id' => $orderId, '@client_id' => $clientId]);
            throw new BadRequestHttpException('Not found order');

            return false;
        }

        $config = $this->load_gateway_configuration($paymentGateway);

        if (null === $config) {
            $this->logger->warning('Not found config for payment gateway @payment_gateway.', ['@payment_gateway' => $order->payment_gateway]);
            throw new BadRequestHttpException('Not found config');

            return false;
        }

        $sigHeader = $request->headers->get(SIGNATURE_HEADER);

        try {
            Webhook::verifySignature($body, $sigHeader, $config['secret_key']);
        } catch (\Exception $exception) {
            $this->logger->warning($exception->getMessage());
            throw new BadRequestHttpException($exception->getMessage());

            return false;
        }

        $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');
        $transactionArray = $paymentStorage->loadByProperties(['remote_id' => $charge->id]);

        if (!empty($transactionArray)) {
            $transaction = array_shift($transactionArray);
        } else {
            $transaction = $paymentStorage->create([
                'state' => 'new',
                'amount' => $order->getTotalPrice(),
                'payment_gateway' => $paymentGateway,
                'order_id' => $order->id(),
                'remote_id' => $charge->id
            ]);
            $transaction->setState('new');
        }

        switch ($event->type) {
            case 'charge:failed':
            case 'charge:delayed':

                $transaction->setRemoteState($charge->status);
                $transaction->setState('failed');
                $order_state = $order->getState();
                $order_state_transitions = $order_state->getTransitions();
                $order_state->applyTransition($order_state_transitions['cancel']);
                $order->save();

                break;
            case 'charge:confirmed':
                $transactionId = '';
                $total = '';
                $currency = '';

                foreach ($charge->payments as $payment) {
                    if (strtolower($payment['status']) === 'confirmed') {
                        $transactionId = $payment['transaction_id'];
                        $total = isset($payment['value']['local']['amount']) ? $payment['value']['local']['amount'] : $total;
                        $currency = isset($payment['value']['local']['currency']) ? $payment['value']['local']['currency'] : $currency;
                    }
                }

                $transaction->setRemoteState($charge->status);
                $transaction->setState('completed');
                $transition = $order->getState()->getWorkflow()->getTransition('place');
                $order->getState()->applyTransition($transition);
                $order->save();

                $this->logger->info('Order @order_number was completed. Transaction id: @transaction_id.',
                    ['@transaction_id' => $transactionId, '@order_number' => $order->id()]);

                break;
            default:
                $transaction->setRemoteState($charge->status);
                $transaction->setState('authorization');
        }

        $this->logger->info(
            'Got notification about order @order_number with status @status.',
            ['@status' => $charge->status, '@order_number' => $order->id()]
        );

        $paymentStorage->save($transaction);
        return true;
    }


    protected function load_gateway_configuration($gateway)
    {
        return $this->configFactory
            ->get('commerce_payment.commerce_payment_gateway.' . $gateway)
            ->get('configuration');
    }

    protected function load_order($orderId)
    {
        return Order::load($orderId);
    }
}
