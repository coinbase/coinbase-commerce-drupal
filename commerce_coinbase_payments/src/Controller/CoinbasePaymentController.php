<?php

namespace Drupal\commerce_coinbase_payments\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_coinbase_payments\IpnInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CoinbasePaymentController extends ControllerBase
{
  private $ipn;

  public function __construct(IpnInterface $ipn)
  {
    $this->ipn = $ipn;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('commerce_coinbase_payments.ipn')
    );
  }

  public function process(Request $request)
  {
    // Get IPN request data and basic processing for the IPN request.
    $this->ipn->process($request);
    $response = new Response();
    $response->setContent('');
    return $response;
  }
}
