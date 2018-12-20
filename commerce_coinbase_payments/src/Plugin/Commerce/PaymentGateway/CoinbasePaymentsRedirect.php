<?php
namespace Drupal\commerce_coinbase_payments\Plugin\Commerce\PaymentGateway;

require_once __DIR__ . '/../../../Coinbase/autoload.php';

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use CoinbaseCommerce\ApiClient;
use CoinbaseCommerce\Resources\Charge;
use Drupal\commerce_payment\Exception\PaymentGatewayException;

/**
 * Provides the Off-site Redirect payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "coinbasepayments_redirect",
 *   label = "Coinbase Commerce - Pay with Bitcoin, Bitcoin Cash, Litecoin, Ethereum",
 *   display_label = "Coinbase Commerce",
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_coinbase_payments\PluginForm\OffsiteRedirect\CoinbaseForm",
 *   }
 * )
 */
class CoinbasePaymentsRedirect extends OffsitePaymentGatewayBase
{
    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $api_key = !empty($this->configuration['api_key']) ? $this->configuration['api_key'] : '';
        $secret_key = !empty($this->configuration['secret_key']) ? $this->configuration['secret_key'] : '';

        $webhookUrl = Url::fromRoute('commerce_coinbase_payments.ipn', [], ['absolute' => TRUE])->toString();

        $form['webhook_url'] = array(
            '#type' => 'label',
            '#title' => $this->t('Please log into your Coinbase Commerce Dashboard, go to Settings and paste <a>@webhookUrl</a> into Webhook Subscription.', ['@webhookUrl' => $webhookUrl])
        );

        $form['api_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('API Key'),
            '#default_value' => $api_key,
            '#description' => $this->t('API Key from Coinbase Commerce.'),
            '#required' => TRUE
        ];

        $form['secret_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Shared Secret'),
            '#default_value' => $secret_key,
            '#description' => $this->t('Shared Secret Key from Coinbase Commerce Webhook subscriptions.'),
            '#required' => TRUE
        ];

        $form['mode']['#access'] = FALSE;

        return $form;
    }


    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
                'api_key' => '',
                'secret_key' => '',
            ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateConfigurationForm($form, $form_state);

        if (!$form_state->getErrors() && $form_state->isSubmitted()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['api_key'] = $values['api_key'];
            $this->configuration['secret_key'] = $values['secret_key'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['api_key'] = $values['api_key'];
            $this->configuration['secret_key'] = $values['secret_key'];
        }
    }

    public function onReturn(OrderInterface $order, Request $request)
    {
        try {
            $chargeId = $order->getData('charge_id');
            ApiClient::init($this->configuration['api_key']);
            $charge = Charge::retrieve($chargeId);
            $lastTimeLine = end($charge['timeline']);
            if (!in_array($lastTimeLine['status'],  array('COMPLETED', 'RESOLVED'))) {
                throw new \Exception('Invalid charge status');
            }
        } catch (\Exception $exception) {
            throw new PaymentGatewayException('Payment failed!');
        }

        return true;
    }
}
