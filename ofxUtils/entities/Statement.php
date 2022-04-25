<?php

require_once('AbstractEntity.php');

class Statement extends AbstractEntity
{
    public $currency;
    public $transaction;
    public $startDate;
    public $endDate;
}