<?php

  $migration_scripts = array();

  /* Suppression de toutes les tables / triggers créés par l'appli POUR LA VERSION COURANTE */
  $resetSQL =
  "DROP TABLE IF EXISTS patterns;
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
  );";

?>