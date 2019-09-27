<?php

require "lib/OfxParser/Parser.php";
use OfxParser\Entities\Transaction;

/**
 * A financial data (QIF, OFX) management library
 * Fonctions: parser, DB import
 */
class FinData
{
  // Transactions extracted from the export file
  private $transactions = array();
  // Start date for the exported transactions (may not be the older transaction date)
  private $startDate;

  /**
   * Utility function returning 1st transaction date of $transactions array
   */
  private function getFirstDate() {
    $group = reset($this->transactions);
    return $group[0]->date->setTime(0,0);
  }

  /**
   * Utility function deleting the first element of the transactions array
   */
  private function unsetFirstGroup() {
    reset($this->transactions);
    unset($this->transactions[key($this->transactions)]);
  }

  /**
   * Utility function to convert a string to UTF-8
   * @param string $content: string to be converted
   */
  private function convertToUnicode($content) {
    if(!mb_check_encoding($content, 'UTF-8')
      OR !($content === mb_convert_encoding(mb_convert_encoding($content, 'UTF-32', 'UTF-8' ), 'UTF-8', 'UTF-32'))) {
      $content = mb_convert_encoding($content, 'UTF-8');
    }
    return $content;
  }

  /**
   * Load a financial data export file into this parser by way of a filename
   *
   * @param string $exportFile: path to the export file
   * @param string $exportName: export file name (if different from path)
   * @throws \InvalidArgumentException
   */
  public function __construct($exportFile,$exportName="") {
    if (!file_exists($exportFile)) {
      throw new \InvalidArgumentException("File '{$exportFile}' could not be found");
    }

    // Fill transactions array
    if(empty($exportName))
      $exportName = basename($exportFile);
    switch(pathinfo($exportName, PATHINFO_EXTENSION)){
      case "qif":
        $this->loadQIF(file($exportFile));
        break;
      case "ofx":
        $OfxParser = new \OfxParser\Parser;
        $Ofx = $OfxParser->loadFromFile($exportFile);
        // Retrieve rib (excluding last 2 digits), not yet used
        $rib = $Ofx->BankAccount->routingNumber." ".$Ofx->BankAccount->agencyNumber." ".$Ofx->BankAccount->accountNumber;
        // Retrieve the transactions
        foreach ($Ofx->getTransactions() as $transaction) {
          $this->transactions[$transaction->date->format("Ymd")][] = $transaction;
        }
        // Retrieve sample start date
        $this->startDate = $Ofx->BankAccount->Statement->startDate;
        break;
      default:
        throw new \InvalidArgumentException("File '{$exportName}' is not supported");
    }

    //Sort it by keys (transactions are grouped by dates)
    ksort($this->transactions);
    //Store the first date
    if(!isset($this->startDate)) {
      $this->startDate = $this->getFirstDate();
    }
  }

  /**
   * Process QIF data
   *
   * @param string[] $lines
   * Constraints on QIF format:
   *   - use '.' for decimal separator
   *   - format dates as: AAAA/MM/DD ou DD/MM/AAAA
   * Nécessite PHP 5.2.2 (comparaison de dates)
   */
  private function loadQIF($lines){
    $transaction = new Transaction();
    foreach($lines as $line)
    {
      $line = $this->convertToUnicode(trim($line));
      if($line === "^") { /* ^ signale la fin de la transaction, le fichier commence par un ! */
        $this->transactions[$transaction->date->format("Ymd")][] = $transaction;
        $transaction = new Transaction();
      } else {
        switch(mb_substr($line, 0, 1)){
          case 'D': /* Date */
            $transaction->date = new DateTime(str_replace("/","-",trim(mb_substr($line, 1))));
            break;
          case 'T': /* Amount of the item. For payments, a leading minus sign is required. */
            $transaction->amount = trim(mb_substr($line, 1));
            break;
          case 'M': /* Description de l'opération */
            $line = str_replace("  ", "", $line); /* Suppression des espaces au milieu des libellés */
            $transaction->name  = trim(mb_substr($line, 1));
            break;
        }
      }
    }
  }

  /**
   * Store transactions into DB
   */
  public function storeInDB() {
    include_once "init.php";
    $added = 0;
    $deleted = 0;

    $conn->beginTransaction();

    //Retrieve DB last transaction date
    $result = $conn->query('SELECT MAX(date_ecriture) as maximum FROM transactions WHERE ext_ref IS NULL;')->fetch(PDO::FETCH_ASSOC);
    if(!empty($result['maximum'])) {
      $lastDate = new DateTime($result['maximum']);

      // If no overlap between file and DB dates => file rejected
      if($this->startDate > $lastDate) {
        answer("Données manquantes entre le ".$lastDate->format("d-M-Y")." et le ".$this->startDate->format("d-M-Y"),'422');
      }

      // Remove transactions from file already in DB
      while(!empty($this->transactions) && $this->getFirstDate() < $lastDate) {
        $this->unsetFirstGroup();
      }

      // If overlap between file and DB dates
      if(!empty($this->transactions) && $this->getFirstDate() <= $lastDate){
        // Delete last_date records if necessary
        $sql = "SELECT count(id) as numlast FROM transactions WHERE date_ecriture=\"".$lastDate->format('Y-m-d')."\"";
        $result = $conn->query($sql)->fetch(PDO::FETCH_ASSOC);
        if(count($this->transactions[$lastDate->format('Ymd')]) > $result["numlast"]){
          $sth = $conn->prepare("DELETE from transactions WHERE date_ecriture=?");
          $sth->execute(array($lastDate->format('Y-m-d')));
          //REM: already set categories and comments will be erased
          $deleted += $sth->rowCount();
        } else { // Database is up-to-date
          $this->unsetFirstGroup();
        }
      }
   
    }

    // Record $this->transactions into DB
    foreach ($this->transactions as $transacs) {
      foreach ($transacs as $transac) {
        $catMatch = $conn->query("SELECT category_id FROM patterns WHERE instr(".$conn->quote($transac->label).", pattern) > 0")->fetchAll(PDO::FETCH_ASSOC);
        $st = $conn->prepare('INSERT INTO transactions (date_ecriture, montant, label, category_id) VALUES (?, ?, ?, ?)');
        $st->execute(array(
          $transac->date->format('Y-m-d'),
          $transac->amount,
          $transac->label,
          count($catMatch) == 1 ? $catMatch[0]["category_id"] : NULL
        ));
        $added += $st->rowCount();
      }
    }

    $conn->commit();
    answer(array($added." ligne(s) ajoutée(s)",$deleted." ligne(s) supprimée(s)"));
  }
}