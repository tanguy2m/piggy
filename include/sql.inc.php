<?php

  $migration_scripts = array();

  /* Suppression de toutes les tables / triggers créés par l'appli POUR LA VERSION COURANTE */
  $resetSQL =
  "DROP VIEW IF EXISTS day_uid;
  DROP VIEW IF EXISTS transactions_to_es;
  DROP TABLE IF EXISTS patterns;
  DROP TABLE IF EXISTS transactions;
  DROP TABLE IF EXISTS withdrawals;
  DROP TABLE IF EXISTS categories;
  DROP TABLE IF EXISTS config;";

  /* Installation de la base from scratch à la dernière version */

  $migration_scripts["0.62"] =
  "CREATE TABLE categories (
    id int AUTO_INCREMENT NOT NULL,
    label varchar(20) NOT NULL,
    PRIMARY KEY (id)
  );

  INSERT INTO categories (label) VALUES ('ok'), ('ko');
  INSERT INTO categories (label) VALUES ('Salaires - Allocs'), ('Epargne');

  CREATE TABLE withdrawals (
    id INT AUTO_INCREMENT NOT NULL,
    comment VARCHAR(200) NOT NULL,
    total DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (id)
  );

  CREATE TABLE transactions (
    id int AUTO_INCREMENT NOT NULL,
    date_ecriture date NOT NULL,
    label varchar(100) NOT NULL,
    montant decimal(8,2) NOT NULL,
    category_id int,
    commentaire varchar(200),
    ext_ref int,
    PRIMARY KEY (id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (ext_ref) REFERENCES withdrawals (id)
  );

  CREATE TABLE config (
    cle varchar(20) NOT NULL,
    valeur varchar(50) NOT NULL,
    PRIMARY KEY (cle)
  );

  INSERT INTO config VALUES ('salary_cat','3');
  INSERT INTO config VALUES ('excluded_cats','[4]');

  CREATE TABLE patterns (
    id int AUTO_INCREMENT NOT NULL,
    pattern varchar(50) NOT NULL,
    category_id int,
    PRIMARY KEY (id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
  );

CREATE VIEW `day_uid` AS 
select 
  `t2`.`date_ecriture` AS `date_ecriture`, 
  `t2`.`toBeHashed` AS `toBeHashed`, 
  md5(`t2`.`toBeHashed`) AS `md5` 
from 
  (
    select 
      `t1`.`date_ecriture` AS `date_ecriture`, 
      concat(
        `t1`.`date_ecriture`, 
        group_concat(`t1`.`data` separator '')
      ) AS `toBeHashed` 
    from 
      (
        select 
          `transactions`.`date_ecriture` AS `date_ecriture`, 
          concat(
            `transactions`.`label`, 
            `transactions`.`montant`
          ) AS `data` 
        from 
          `transactions` 
        where 
          `transactions`.`label` not like '%prélèvement sur salaire%'
      ) `t1` 
    group by 
      `t1`.`date_ecriture`
  ) `t2`;

CREATE VIEW `transactions_to_es` AS 
select 
  `t1`.`@timestamp` AS `@timestamp`, 
  `t1`.`amount` AS `amount`, 
  `t1`.`message` AS `message`, 
  `t1`.`comment` AS `comment`, 
  `t1`.`category` AS `tags`, 
  concat(
    `t1`.`md5`, 
    '-', 
    lpad(
      cast(
        row_number() over (
          partition by `t1`.`md5` 
          order by 
            `t1`.`message`
        ) as char(3) charset utf8mb4
      ), 
      3, 
      '0'
    )
  ) AS `uid` 
from 
  (
    select 
      concat(
        `transactions`.`date_ecriture`, 
        'T22:00:00.000Z'
      ) AS `@timestamp`, 
      `transactions`.`montant` AS `amount`, 
      `transactions`.`commentaire` AS `comment`, 
      `transactions`.`label` AS `message`, 
      `categories`.`label` AS `category`, 
      `day_uid`.`md5` AS `md5` 
    from 
      (
        (
          `transactions` 
          left join `day_uid` on(
            `transactions`.`date_ecriture` = `day_uid`.`date_ecriture`
          )
        ) 
        left join `categories` on(
          `transactions`.`category_id` = `categories`.`id`
        )
      ) 
    where 
      `transactions`.`label` not like '%prélèvement sur salaire%'
  ) `t1` 
order by 
  `t1`.`@timestamp`;
";

?>
