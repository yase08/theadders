<?php

namespace App\Interfaces;

interface ExchangeInterface
{
  public function requestExchange(array $data);

  public function approveExchange(int $exchangeId);

  public function declineExchange(int $exchangeId);

  public function getUserExchanges();

  public function getIncomingExchanges();

  public function getOutgoingExchanges();

  public function getExchangeById(int $exchangeId);

  public function completeExchange(int $exchangeId);

  public function cancelExchange(int $exchangeId);

  public function getProductExchangeRequests(int $productId);
}
