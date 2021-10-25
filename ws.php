<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
include_once("include/functions.inc.php");

class API {
  public static function get($path,$data){
    include_once "include/init.php";
    $datesWhere = '';

    // Get transactions list
    if( $path[0] == "transactions"){

      $table = "transactions";
      // Array of database columns which should be read and sent back to DataTables.
      // The `db` parameter represents the column name in the database
      // The `dt` parameter represents the DataTables column identifier
      $columns = array(
        array('db' => 'id', 'dt' => 0),
        array('db' => 'date_ecriture', 'dt' => 1),
        array('db' => 'label', 'dt' => 2),
        array('db' => 'montant', 'dt' => 3),
        array('db' => 'category_id', 'dt' => 4),
        array('db' => 'commentaire', 'dt' => 5)
      );

      /* Date range filtering */
      if ( isset($_GET['start_date']) && isset($_GET['end_date'])){
        $datesWhere .= $columns[1]['db'] . " BETWEEN '".$_GET['start_date']."' AND '".$_GET['end_date']."' ";
      }

    // Get patterns list
    } else if ( $path[0] == "patterns"){
      $table = "patterns";
      $columns = array(
        array('db' => 'id', 'dt' => 0),
        array('db' => 'pattern', 'dt' => 1),
        array('db' => 'category_id', 'dt' => 2)
      );
    }

    /* Tables primary key */
    $primaryKey = "id";

    // Return results
    require "lib/ssp.class.php";
    echo json_encode(
      SSP::complex( $_GET, $conn, $table, $primaryKey, $columns, $datesWhere )
    );
  }
  public static function put($path,$data) {
    include_once "include/init.php";
    $st = $conn->prepare('UPDATE '.$path[0].' SET category_id=? WHERE id=?;');
    $st->execute(array(
      ($data->cat_id > 0) ? $data->cat_id : NULL,
      $path[1]
    ));
  }
}

class API_db {
  public static $supported_methods = array("delete");
  public static function delete($path,$data) {
    include_once "include/init.php";
    $conn->beginTransaction();
    $st = $conn->prepare($resetSQL);
    $st->execute();
    $conn->commit();
    answer("Base effacée");
  }
}

class API_patterns extends API {
  public static $supported_methods = array("get","post","put","delete");
  public static function post($path,$data) {
    include_once "include/init.php";
    if ($data->cat_id > 0 && !empty($data->pattern)){
      $st = $conn->prepare('INSERT INTO patterns (pattern,category_id) VALUES (?,?)');
      $st->execute(array(
        $data->pattern,
        $data->cat_id
      ));
    }
  }
  public static function delete($path,$data) {
    include_once "include/init.php";
    $conn->beginTransaction();
    $st = $conn->prepare('DELETE FROM patterns WHERE id = ?');
    $st->execute(array($path[1]));
    $conn->commit();
  }
}

class API_withdrawals {
  public static $supported_methods = array("get","post","delete");
  public static function get($path,$data) {
    include_once "include/init.php";
    $result = $conn->query('SELECT id,CONCAT(comment," (Total = ",CAST(total as char),"€)") FROM withdrawals;')->fetchAll(PDO::FETCH_KEY_PAIR);
    answer($result);
  }
  public static function post($path,$data) {
    include_once "include/init.php";
    $conn->beginTransaction();

    $st = $conn->prepare("INSERT INTO withdrawals (comment,total) VALUES(?,?)");
    $st->execute(array(
      $data->comment,
      $data->total
    ));
    $id = $conn->lastInsertId();

    foreach($data->mensualites as $mensualite){
      $st = $conn->prepare('INSERT INTO transactions (date_ecriture, montant, label, category_id, ext_ref) VALUES (?, ?, ?, ?, ?)');
      $st->execute(array(
        $mensualite->date,
        "-".$mensualite->montant,
        $conf["prelev_prefix"] . $data->comment,
        (empty($data->cat_id) ? NULL : $data->cat_id),
        $id
      ));
      $st = $conn->prepare("INSERT INTO transactions (date_ecriture, montant, label, category_id, ext_ref) SELECT ?, ?, ?, valeur, ? FROM config WHERE cle='salary_cat'");
      $st->execute(array(
        $mensualite->date,
        $mensualite->montant,
        $conf["compens_prelev_prefix"] . $data->comment,
        $id
      ));
    }

    $conn->commit();
    answer(array(
      "Prélèvement '".$data->comment."' ajouté",
      count($data->mensualites)." mensualité(s) pour un total de ".$data->total."€"
    ));
  }
  public static function delete($path,$data) {
    include_once "include/init.php";
    $conn->beginTransaction();

    $st = $conn->prepare('DELETE FROM transactions WHERE ext_ref = ?');
    $st->execute(array($path[1]));

    $st = $conn->prepare('DELETE FROM withdrawals WHERE id = ?');
    $st->execute(array($path[1]));

    $conn->commit();
  }
}

class API_transactions extends API {
  public static $supported_methods = array("get","post","put");
  public static function post($path,$data) {
    require "lib/FinData.php";
    if (strpos($_SERVER['CONTENT_TYPE'],'multipart/form-data') !== false) { // File upload
      if ($_FILES['exportFile']['error'] != UPLOAD_ERR_OK)
        answer("Erreur [".$_FILES['exportFile']['error']."]",500);
      $findata = new FinData($_FILES['exportFile']['tmp_name'],$_FILES['exportFile']['name']);
    } else { // File already on server
      include "include/conf.inc.php"; //For $folder var
      $findata = new FinData($conf["folder"].$data->file);
    }
    $findata->storeInDB();
  }
  public static function put($path,$data) {
    if (isset($data->cat_id)) {
      parent::put($path,$data);
    } else if (isset($data->comment)) {
      include_once "include/init.php";
      $st = $conn->prepare('UPDATE transactions SET commentaire=? WHERE id=?;');
      $st->execute(array(
        $data->comment,
        $path[1]
      ));
    }
  }
}

class API_categories {
  public static $supported_methods = array("get");
  public static function get($path,$data) {
    include_once "include/init.php";
    $result = $conn->query('SELECT * FROM categories;')->fetchAll(PDO::FETCH_KEY_PAIR);
    answer($result);
  }
}

class API_synthesis {
  public static $supported_methods = array("get");
  public static function get($path,$data) {
    include_once "include/init.php";
    $params = array();

    // Check URL
    if (!isset($path[1]))
      answer("Incomplete URL: missing type of synthesis", 400);
    if (!isset($_GET["debut"]))
      answer("Missing parameter: 'debut'", 400);
    if (!isset($_GET["fin"]))
      answer("Missing parameter: 'fin'", 400);

    if ($path[1] === "categories") {
      $st = $conn->prepare("SELECT DATE_FORMAT(date_ecriture,'%Y-%m') AS mois, category_id AS id, SUM(montant) AS montant
        FROM transactions
        WHERE date_ecriture BETWEEN ? AND ?
        GROUP BY mois, category_id
        ORDER BY mois,id ASC;");
    }
    else if ($path[1] === "withdrawals") {
      $st = $conn->prepare("SELECT DATE_FORMAT(date_ecriture,'%Y-%m') AS mois, ext_ref AS id, montant
        FROM transactions
        WHERE ext_ref IS NOT NULL AND montant >= 0
          AND date_ecriture BETWEEN ? AND ?
        ORDER BY mois,id ASC;");
    }

    // Get sums
    $params[] = $_GET["debut"]."-01";
    $params[] = date("Y-m-t", strtotime($_GET["fin"])); // t=nombre de jours dans le mois
    $st->execute($params);
    $output = $st->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_NUM); // Groupe sur le mois, tableau de [id,montant] pour les mensualités
    $data = json_encode($output,JSON_NUMERIC_CHECK); // Convertit en nombre autant que possible

    // Get categories to be excluded from total
    $excluded_cats = $conn->query("SELECT valeur FROM config WHERE cle='excluded_cats';")->fetchAll(PDO::FETCH_COLUMN);

    // Return json result
    header('Content-Type: application/json');
    echo '{"data": '.$data.', "excluded_cats": '.(count($excluded_cats) > 0 ? $excluded_cats[0] : '[]').'}';
  }
}

class API_years {
  public static $supported_methods = array("get");
  public static function get($path,$data) {
    include_once "include/init.php";
    $result = $conn->query('SELECT DISTINCT YEAR(date_ecriture) FROM transactions')->fetchAll(PDO::FETCH_COLUMN, 0);
    answer($result);
  }
}

// Launch the relevant API function
try{
  $path = explode("/", substr(@$_SERVER['PATH_INFO'], 1));
  $handler = 'API_'.$path[0];
  $method = strtolower($_SERVER['REQUEST_METHOD']);
  if (!class_exists($handler) || !in_array($method,$handler::$supported_methods)) {
    answer("Unknown resource name or unsupported action",400);
  }
  $data = file_get_contents('php://input'); // php://input does not support enctype="multipart/form-data"
  if (($method === "post" || $method === "put") &&
    strpos($_SERVER['CONTENT_TYPE'],'application/json') !== false &&
    !$data = json_decode($data)) {
      answer("Unsupported request body",400);
  }
  $handler::$method($path,$data);
} catch(Exception $e){
  if(isset($conn) && $conn->inTransaction())
    $conn->rollBack();
  answer($e->getMessage(),"500");
}
?>