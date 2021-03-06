<?php

require_once('AbstractEntity.php');

class BankAccount extends AbstractEntity
{
    public $accountNumber;
    public $accountType;
    public $balance;
    public $balanceDate;
    public $routingNumber;
    public $statement;
    public $transactionUid;
}