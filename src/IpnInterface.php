<?php

namespace Drupal\commerce_coinbasepayments;

use Symfony\Component\HttpFoundation\Request;

interface IpnInterface {

  /**
   * Processes an incoming IPN request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return mixed
   *   The request data array.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function process(Request $request);

}
