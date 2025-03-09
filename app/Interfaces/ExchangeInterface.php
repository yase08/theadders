<?php

namespace App\Interfaces;

interface ExchangeInterface
{
  public function requestExchange(array $data);

  public function approveExchange(int $exchangeId);

  public function declineExchange(int $exchangeId);

  public function getUserExchanges();

  public function getExchangeById(int $exchangeId);
}
