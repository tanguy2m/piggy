<?php

  /* Fonction exécutant les requêtes SQL de migration de la DB */
  function upgradeDB($currentDBver,$reqDBver,$conn,$migration_scripts){
    // Récupération des indices des requêtes à appliquer sur la base
    $neededUpdates = array_filter(array_keys($migration_scripts),function ($ver) use ($currentDBver) {
      return ($ver > $currentDBver);
    });
    $scripts = array_intersect_key($migration_scripts,array_flip($neededUpdates));
    foreach($scripts as $sql){
      $st = $conn->prepare($sql);
      $st->execute();
    }
    $st = $conn->prepare("INSERT INTO config (cle,valeur) VALUES (:label, :version) ON DUPLICATE KEY UPDATE valeur=:version");
    $st->execute(array(":label"=>"DBversion", ":version"=>$reqDBver));
  }

  /* Pre-requis: la base utilisée doit être configurée en UTF-8 */
  header('Content-Type: text/html; charset=utf-8');

  // Connexion à la base
  try {
    include("include/conf.inc.php");
    $conn = new PDO('mysql:host='.$conf["dbHost"].';dbname='.$conf["dbName"], $conf["dbUser"], $conf["dbPass"]);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET CHARACTER SET utf8");
  } catch ( PDOException $e ) {
    include_once("include/functions.inc.php");
    answer("Impossible de se connecter à la base de données",'503');
  }

  try {
    include_once("include/sql.inc.php");
    $reqDBver = max(array_keys($migration_scripts));

    // Récupération de la version de base actuelle
    $currentDBver = -1;
    $results = $conn->query("SHOW TABLES LIKE 'config';")->fetchAll();
    if(!empty($results)){ // L'appli a déjà été installée
      $currentDBver = $conn->query("SELECT valeur FROM config WHERE cle='DBversion';")->fetchColumn();
    }

    // Upgrade de la base si nécessaire, en gérant les locks
    if($currentDBver != $reqDBver){
      if($currentDBver >= 0){
        $locked = $conn->exec("
          INSERT INTO config
          VALUES('upgradeLock',NOW())
          ON DUPLICATE KEY UPDATE
          valeur = IF(VALUES(valeur) > (valeur + INTERVAL 5 MINUTE),VALUES(valeur),valeur);");
        if($locked){
          upgradeDB($currentDBver,$reqDBver,$conn,$migration_scripts);
          $conn->exec("DELETE FROM config WHERE cle='upgradeLock';");
        } else { // Une migration est en cours
          do {
            sleep(1);
            $locked = $conn->query("SELECT count(cle) FROM config WHERE cle='upgradeLock';")->fetchColumn();
          } while ($locked);
        }
      } else {
        upgradeDB($currentDBver,$reqDBver,$conn,$migration_scripts);
      }
    }

  } catch (PDOException $e ) {
    include_once("include/functions.inc.php");
    answer($e->getMessage(),'500');
  }
?>