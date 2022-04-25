<?php 
/**
 * monta funções dentro da classe ofx
 */

use SimpleXMLElement;
require('entities/AccountInfo.php');
require('entities/BankAccount.php');
require('entities/Institute.php');
require('entities/SignOn.php');
require('entities/Statement.php');
require('entities/Status.php');
require('entities/Transaction.php');
class Ofx{
    
    public $Header;
    public $SignOn;
    public $SignupAccountInfo;
    public $BankAccounts = array();
    public $BankAccount;
    public $Investment;

    public function __construct(SimpleXMLElement $xml)
    {
        $this->SignOn = $this->buildSignOn($xml->SIGNONMSGSRSV1->SONRS);
        $this->SignupAccountInfo = $this->buildAccountInfo($xml->SIGNUPMSGSRSV1->ACCTINFOTRNRS);
        $this->BankAccounts = $this->buildBankAccounts($xml);
        if (count($this->BankAccounts) == 1) {
            $this->BankAccount = $this->BankAccounts[0];
        }
    }

    public function getTransactions()
    {
        return $this->BankAccount->Statement->Transactions;
    }
    
    private function buildBankAccounts(SimpleXMLElement $xml)
    {
        // Loop through the bank accounts
        $bankAccounts = array();
        foreach ($xml->BANKMSGSRSV1->STMTTRNRS as $accountStatement) {
            $bankAccounts[] = $this->buildBankAccount($accountStatement);
        }
        return $bankAccounts;
    }

    private function buildBankAccount($xml)
    {
        $Bank = new BankAccount();
        $Bank->transactionUid = $xml->TRNUID;
        $Bank->agencyNumber = $xml->STMTRS->BANKACCTFROM->BRANCHID;
        $Bank->accountNumber = $xml->STMTRS->BANKACCTFROM->ACCTID;
        $Bank->routingNumber = $xml->STMTRS->BANKACCTFROM->BANKID;
        $Bank->accountType = $xml->STMTRS->BANKACCTFROM->ACCTTYPE;
        $Bank->balance = $xml->STMTRS->LEDGERBAL->BALAMT;
        $Bank->balanceDate = $this->createDateTimeFromStr($xml->STMTRS->LEDGERBAL->DTASOF);

        $Bank->Statement = new Statement();
        $Bank->Statement->currency = $xml->STMTRS->CURDEF;
        $Bank->Statement->startDate = $this->createDateTimeFromStr($xml->STMTRS->BANKTRANLIST->DTSTART);
        $Bank->Statement->endDate = $this->createDateTimeFromStr($xml->STMTRS->BANKTRANLIST->DTEND);
        $Bank->Statement->transactions = $this->buildTransactions($xml->STMTRS->BANKTRANLIST->STMTTRN);

        return $Bank;
    }

    private function buildTransactions($transactions)
    {
        $return = array();
        foreach ($transactions as $t) {
            $Transaction = new Transaction();
            $Transaction->type = (string)$t->TRNTYPE;
            $Transaction->date = $this->createDateTimeFromStr($t->DTPOSTED);
            $Transaction->amount = $this->createAmountFromStr($t->TRNAMT);
            $Transaction->uniqueId = (string)$t->FITID;
            $Transaction->name = (string)$t->NAME;
            $Transaction->memo = utf8_decode($t->MEMO);
            $Transaction->sic = $t->SIC;
            $Transaction->checkNumber = $t->CHECKNUM;
            $return[] = $Transaction;
        }

        return $return;
    }

    private function createAmountFromStr($amountString)
    {
        //000.00 or 0,000.00
        if (preg_match("/^-?([0-9,]+)(\.?)([0-9]{2})$/", $amountString) == 1) {
            $amountString = preg_replace(
                array("/([,]+)/",
                    "/\.?([0-9]{2})$/"
                    ),
                array("",
                    ".$1"),
                $amountString);
        }

        //000,00 or 0.000,00
        elseif (preg_match("/^-?([0-9\.]+,?[0-9]{2})$/", $amountString) == 1) {
            $amountString = preg_replace(
                array("/([\.]+)/",
                    "/,?([0-9]{2})$/"
                    ),
                array("",
                    ".$1"),
                $amountString);
        }

        return (float)$amountString;
    }


    private function buildAccountInfo($xml)
    {
        if (!isset($xml->ACCTINFO)) return array();

        $accounts = array();
        foreach ($xml->ACCTINFO as $account) {
            $AccountInfo = new AccountInfo();
            $AccountInfo->desc = $account->DESC;
            $AccountInfo->number = $account->ACCTID;
            $accounts[] = $AccountInfo;
        }

        return $accounts;
    }

    private function buildSignOn($xml)
    {
        $SignOn = new SignOn;
        $SignOn->Status = $this->buildStatus($xml->STATUS);
        $SignOn->date = $this->createDateTimeFromStr($xml->DTSERVER);
        $SignOn->language = $xml->LANGUAGE;

        $SignOn->Institute = new Institute();
        $SignOn->Institute->name = $xml->FI->ORG;
        $SignOn->Institute->id = $xml->FI->FID;
        
        return $SignOn;
    }

    private function buildStatus($xml)
    {
        $Status = new Status();
        $Status->code = $xml->CODE;
        $Status->severity = $xml->SEVERITY;
        $Status->message = $xml->MESSAGE;

        return $Status;
    }

    private function createDateTimeFromStr($dateString)
    {
        $ano = substr($dateString,0,4);
        $mes = substr($dateString,4,2);
        $dia = substr($dateString,6,2);

        if(strlen($dateString) > 8){   

            $hora = substr($dateString,8,2);
            $minuto = substr($dateString,10,2);
            $segundo = substr($dateString,12,2);
            $op = true;

        }
        return $ano . '-' . $mes . '-' . $dia . (!empty($op)? ' ' . $hora . ':' . $minuto . ':' . $segundo : '');
        // return $dateString;
    }
}
?>