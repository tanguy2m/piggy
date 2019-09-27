<?php

namespace OfxParser\Entities;

abstract class AbstractEntity
{
    /**
     * Allow functions to be called as properties
     * to unify the API
     *
     * @param $name
     * @return method | bool
     */
    public function __get($name)
    {
        if(method_exists($this, lcfirst($name))) {
            return $this->{$name}();
        }
        return false;
    }
}

class AccountInfo extends AbstractEntity
{
    public $desc;
    public $number;
}

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

class Institute extends AbstractEntity
{
    public $name;
    public $id;
}

class SignOn extends AbstractEntity
{
    public $status;
    public $date;
    public $language;
    public $institute;
}

class Statement extends AbstractEntity
{
    public $currency;
    public $transaction;
    public $startDate;
    public $endDate;
}

class Status extends AbstractEntity
{
    protected $codes= array(
        '0'       => 'Success',
        '2000'    => 'General error',
        '15000'   => 'Must change USERPASS',
        '15500'   => 'Signon invalid',
        '15501'   => 'Customer account already in use',
        '15502'   => 'USERPASS Lockout'
    );

    public $code;
    public $severity;
    public $message;

    /**
     * Get the associated code description
     *
     * @return string
     */
    public function codeDesc()
    {
        // Cast code to string from SimpleXMLObject
        $code = (string) $this->code;
        return isset($this->codes[$code]) ? $this->codes[$code] : '';
    }

}

class Transaction extends AbstractEntity
{

    protected $types = array(
        "CREDIT"      => "Generic credit",
        "DEBIT"       => "Generic debit",
        "INT"         => "Interest earned or paid ",
        "DIV"         => "Dividend",
        "FEE"         => "FI fee",
        "SRVCHG"      => "Service charge",
        "DEP"         => "Deposit",
        "ATM"         => "ATM debit or credit",
        "POS"         => "Point of sale debit or credit ",
        "XFER"        => "Transfer",
        "CHECK"       => "Cheque",
        "PAYMENT"     => "Electronic payment",
        "CASH"        => "Cash withdrawal",
        "DIRECTDEP"   => "Direct deposit",
        "DIRECTDEBIT" => "Merchant initiated debit",
        "REPEATPMT"   => "Repeating payment/standing order",
        "OTHER"       => "Other"
    );

  public $type;
  public $date;
  public $amount;
  public $uniqueId;
  public $name;
  public $memo;
    public $sic;
    public $checkNumber;

    /**
     * Get the associated type description
     *
     * @return string
     */
    public function typeDesc()
    {
        // Cast SimpleXMLObject to string
        $type = (string) $this->type;
        return isset($this->types[$type]) ? $this->types[$type] : '';
    }

  public function label()
  {
    return $this->name.(isset($this->memo) ? " ".$this->memo : "");
  }
}

?>