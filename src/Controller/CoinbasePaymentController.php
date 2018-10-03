<?php

namespace Drupal\commerce_coinbasepayments\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_coinbasepayments\IpnInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CoinbasePaymentController extends ControllerBase {

  protected $ipn;

  public function __construct(IpnInterface $ipn) {
    $this->ipn = $ipn;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_coinbasepayments.ipn')
    );
  }

  public function process(Request $request) {

    // Get IPN request data and basic processing for the IPN request.
    $result = $this->ipn->process($request);

    $response = new Response();
    $response->setContent(json_encode(['status' => $result]));
    $response->headers->set('Content-Type', 'application/json');
    return $response;
  }
}
