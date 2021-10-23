<?php
  $conf = array(
    // MySQL connection data
    "dbHost" => getenv('MYSQL_DB_HOST'),
    "dbName" => getenv('PIGGY_DB_NAME'),
    "dbUser" => getenv('PIGGY_USER'),
    "dbPass" => getenv('PIGGY_PWD'),

    //Préfixes des prélèvements
    "prelev_prefix" => "Prélèvement sur salaire: ",
    "compens_prelev_prefix" => "Compensation prélèvement sur salaire: "
  );
?>
