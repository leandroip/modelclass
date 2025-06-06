<?php

namespace ModelClass;

use PDO;
use PDOException;
use Exception;


class Model {
    protected static $pdo;
    protected $table;
    protected $data = [];

    public function __construct($table) {
        $this->table = $table;

        self::connect();
    }

    public static function connect(){
        if (!self::$pdo) {
            $host = DB_HOST;
            $dbname = DB_NAME;
            $user = DB_USER;
            $pass = DB_PASS;
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
            } catch (PDOException $e) {
                die("Erro na conexão: " . $e->getMessage());
            }
        }
    }

    protected function getTableColumns() {
        $stmt = self::$pdo->query("DESCRIBE {$this->table}");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $columns;
    }

    public function insert(array $data = null) {
        if ($data == null) {
            $data = $this->data;
        }
        $validColumns = $this->getTableColumns();
        $data = array_intersect_key($data, array_flip($validColumns));

        //se contem id em $data, chame update
        if (isset($data['id']) && $data['id'] !== null && $data['id'] !== '') {
            return $this->update($data);
        }

        if(!isset($data['id']) || $data['id'] === null || $data['id'] === '') {
            $timestamp = date('YmdHis');
            $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $unique = substr(uniqid('', true), -4);
            $data['id'] = $timestamp . $random . $unique;
            $this->data['id'] = $data['id']; 
        }
    
        if (empty($data)) {
            throw new Exception("Nenhum dado válido para inserção.");
        }
    
        $cols = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $cols);
    
        $sql = "INSERT INTO {$this->table} (" . implode(",", $cols) . ") VALUES (" . implode(",", $placeholders) . ")";
        $stmt = self::$pdo->prepare($sql);
    
        foreach ($data as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }
    
        $stmt->execute();
        //return self::$pdo->lastInsertId();
        return true;
    }

    public function load($id) {
        $stmt = self::$pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $this->data = $stmt->fetch();

        return $this->data !== false;
    }

    public function loadProp(){
        $res = $this->query('SHOW COLUMNS FROM ' . $this->table);
        $a = $this->toArray($res);
        foreach ($a as $k => $v) {
            $this->data[$v["Field"]] = "";
            //$this->_proptype[$v["Field"]] = $v["Type"];
        }
        return $this;
    }

    public function getAll() {
        return $this->data;
    }

    public function get($field) {
        return $this->data[$field] ?? null;
    }

    public function set($field, $value) {
        $this->data[$field] = $value;
    }

    public function setAll(array $data) {
        $validColumns = $this->getTableColumns();
        $this->data = array_intersect_key($data, array_flip($validColumns));
    }

    public function save() {
        if (isset($this->data['id'])) {
            return $this->update();
        } else {
            return $this->insert($this->data);
        }
    }

    public function update($data = null) {
        if ($data !== null) {
            $this->setAll($data);
        }
        if (!isset($this->data['id'])) {
            throw new Exception("ID não definido para atualização.");
        }

        $cols = array_keys($this->data);
        $fields = array_filter($cols, fn($col) => $col !== 'id');

        $setClause = implode(", ", array_map(fn($col) => "$col = :$col", $fields));
        $sql = "UPDATE {$this->table} SET $setClause WHERE id = :id";

        $stmt = self::$pdo->prepare($sql);

        foreach ($this->data as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        return $stmt->execute();
    }

    function select($where = null, $order = null, $from = null, $count = null) {
        $params = array();
        $whereClause = "";
        
        if ($where != null) {
            $conditions = array();
            foreach ($where as $k => $v) {
                $conditions[] = "{$k} = ?";
                $params[] = $v;
            }
            $whereClause = "WHERE " . implode(" AND ", $conditions);
        }
        
        $orderClause = $order ? "ORDER BY {$order}" : "";
        $limitClause = "";
        
        if ($count !== null) {
            $limitClause = "LIMIT ?, ?";
            $params[] = (int)$from;
            $params[] = (int)$count;
        }
        
        $sql = "SELECT * FROM {$this->table} {$whereClause} {$orderClause} {$limitClause}";
        $res = $this->query($sql, $params);

        return $this->toArray($res);
    }

    public function delete($id=null) {
        if($id == null) {
            $id = $this->data['id'] ?? null;
        }
        if ($id === null) {
            throw new Exception("ID não definido para exclusão.");
        }

        $stmt = self::$pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Executa uma query SQL diretamente (útil para SELECTs manuais, etc.)
     * @param string $sql
     * @param array $params (opcional)
     * @return PDOStatement
     */
    public static function query($sql, $params = []) {
        self::connect();
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function toArray($stmt) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function query_ar($qr, $params = array()) {
        return self::toArray(self::query($qr, $params));
    }

    public function getValueByIdCol($id, $col, $table = "") {
        $table = $table ?: $this->table;
        $result = self::query("SELECT {$col} FROM {$table} WHERE id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
        return $result[$col] ?? null;
    }

    public function setValueByIdCol($id, $col, $val, $table = "") {
        $table = $table ?: $this->table;
        $stmt = self::query("UPDATE {$table} SET {$col} = ? WHERE id = ?", [$val, $id]);
        return $stmt->rowCount() > 0;
    }

    public function loadBy($field, $value) {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = :value LIMIT 1";
        $data = self::query_ar($sql, [':value' => $value]);
    
        if (count($data) == 1) {
            $this->data = $data[0];
            return true;
        }
    
        return false;
    }

    public function reload() {
        if (!isset($this->data['id'])) {
            throw new Exception("ID não definido para recarregar.");
        }
        return $this->load($this->data['id']);
    }
    
    public static function startTransaction() {
        self::$pdo->beginTransaction();
    }
    public static function commit() {
        self::$pdo->commit();
    }
    public static function rollback() {
        self::$pdo->rollBack();
    }   
    
    function prepareCaseWhenForSearch($term1, $onCols, $colWeight){
        $terms = preg_split('/[\s,\+]+/', $term1);
        $fieldSearchQueries = array();
        $c = 0;
        foreach ($onCols as $thisField) {
            foreach($terms as $kw){
                //$fieldSearchQueries[] = "CASE WHEN $thisField LIKE '%$kw%' THEN 1 ELSE 0 END";
                $fieldSearchQueries[] = "IF( $thisField LIKE '%$kw%', $colWeight[$c], 0)";
                //$fieldSearchQueries[] = "IF( $thisField = '$kw', ".($colWeight[$c]*2).", 0)";
                $fieldSearchQueries[] = "IF( $thisField LIKE '$kw', ".($colWeight[$c]*2).", 0)";
                
            }
            $c++;
        }
        return "(".implode(' + ', $fieldSearchQueries).")";
    }

    function search($term, $fromTables, $plainWhere = '1', $onCols = array(), $colWeight = array(), $from=null, $count = null, $onlySql=false, $orderby="", $cols="", $group=""){
        $table = $fromTables;
        if($fromTables == NULL){
            $table = $this->table;
        }

        
        if(strlen(trim($cols)) > 0){
            //$cols = ",".$cols;
        }else{
            $cols = "*";
        }
        if(strlen(trim($group)) > 0){
            $group = "GROUP BY ".$group;
        }
        if(trim($term) == ""){
            if(strlen(trim($orderby)) > 0){
                $orderby = "ORDER BY ".$orderby;
            }
            $sql = "SELECT $cols FROM $table WHERE $plainWhere $group $orderby";
        }else{        
            $caseWhen = $this->prepareCaseWhenForSearch($term, $onCols, $colWeight);
            $order = (trim($orderby) == "") ? "" : ','.$orderby;
            
            $sql = "SELECT * FROM (SELECT $cols, $caseWhen AS rankk from $table WHERE ($plainWhere) $group) as tab WHERE rankk > 0 ORDER BY rankk DESC $order";
            //echo $sql;
            //die();
        }
        if ($count != null) {
            $sql = $sql . " limit $from, $count";
        }
        if($onlySql){
            return $sql;
        }
        return $this->query_ar($sql);
    }
    
    
}




/**
 * \brief The database handler
 * 
 * This variable is global to avoid a new connection on each Model instantiation
 * or query call.
 * 
 * */
$_dbHandle = null;

/**
 * \brief The Model class
 * 
 * This class access the database and provides the main functionalities 
 * to manage the database.
 * 
 * This class can insure any SQL command.
 * 
 * An example:
 * 
 *     $m = new Model('table1');
 *     $r = $m->select();
 *     print_r($r); //$r is an array with all cols and lines of table1
 * 
 * Another example
 * 
 *     $m = new Model('table1');
 *     $r = $m->query_ar('SELECT * from table2, table3 where table2.id = table3.t2_id');
 *     print_r($r); //$r is an array with all the result.
 * 
 * 
 * */
class ModelMYSQL {

    /** Propriedades */
    protected $_prop; ///< The cols of the table
    protected $_proptype; ///< the type of each col
    protected $editing = false;
    protected $generateId = true;
    protected $_model; ///< The model name
    protected $_table; ///< The table name
    private $lastSql = "";

    public function setGenerateId($v){
        $this->generateId = $v;
    }

    /**
     * 
     * \brief Access the database on specific table.
     * 
     * Accessing a specific table do means you can only manage this table.
     * 
     * 
     * 
     * */
    function __construct($tablename = "") {
        if ($tablename == "") {
            $this->_model = get_class($this);
            $this->_table = strtolower($this->_model) . "s";
            $this->_prop = array();
        } else {
            $this->_model = $tablename;
            $this->_table = $tablename;
            $this->_prop = array();
        }
    }

    function connect($address = DB_HOST, $account = DB_USER, $pwd = DB_PASS, $name = DB_NAME) {
        global $_dbHandle;
        
        if ($_dbHandle == null) {
            try {
                $dsn = "mysql:host={$address};dbname={$name};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5
                ];
                
                $_dbHandle = new PDO($dsn, $account, $pwd, $options);
                return 1;
            } catch (PDOException $e) {
                throw new Exception("Erro ao conectar ao banco de dados: " . $e->getMessage());
            }
        }
        return 1;
    }

    /**
     * \brief Set a col of the table passed in @ref __construct.
     * 
     * \param[$att] The col.
     * \param[$val] The value
     * 
     * */
    function set($att, $val) {
        $this->_prop[$att] = $val;
    }

    /**
     * \brief Get all cols of the table passed in @ref __construct.
     * 
     * */
    function getProp() {
        $res = $this->query('SHOW COLUMNS FROM ' . $this->_table);
        $a = $this->toArray($res);
        foreach ($a as $k => $v) {
            $this->_prop[$v["Field"]] = "";
            $this->_proptype[$v["Field"]] = $v["Type"];
        }
        return $this->_prop;
        //print_r($this->_prop);
    }
    
    function getColumns(){
        $cols = array();
        $res = $this->query('SHOW COLUMNS FROM ' . $this->_table);
        $a = $this->toArray($res);
        foreach ($a as $k => $v) {
            array_push($cols, $v["Field"]);
        }
        return $cols;
    }

    /**
     * \brief Set all cols throuht an array which keys has the same name 
     * of the cols.
     * 
     * With this function you can pass all data of the $_POST in one time.
     * Avoiding to many call to @ref set.
     * 
     * You can pass all data of a form with only one line: `$m->setAll($_POST)`.
     * 
     * As this function is usually instantiated inside a controller, is a best practice 
     * to use the filtered user input provided by the controller: `$m->setAll($this->_post)`.
     * 
     * \param[$att] The array with all the data. If a key of $att doesn't exists in the cols, it will be ignored.
     * If $att doesn't contains all the cols, the original values of these cols will not be changed (if exists, otherwise the database will use the default).
     * 
     * */
    function setAll($att, $isUpdate = false) {
        $this->getProp();
        if (!$isUpdate){        
            
        }else{
            $this->editing = true;
        }
        foreach ($this->_prop as $k => $v) {
            if (isset($att[$k])) {
                $this->_prop[$k] = $att[$k];
                //$this->set($k, $att[$k]);
            } else {
                unset($this->_prop[$k]);
            }
        }
    }
     

    /**
     * \brief Get the value of a col.
     * 
     * After insuring @ref load, you can take the value of any col using this function.
     * 
     * */
    function get($att) {
        if(isset($this->_prop[$att])){
            return $this->_prop[$att];
        }
        return null;
    }

    function getAll() {
        return $this->_prop;
    }

    /**
     * 
     * \brief Execute a custom query.
     * 
     * */
    function query($qr, $params = array()) {
        global $_dbHandle;
        
        try {
            $this->connect();
            $this->lastSql = $qr;
            
            $stmt = $_dbHandle->prepare($qr);
            
            if (!$stmt) {
                throw new Exception("Erro ao preparar a query: " . implode(" ", $_dbHandle->errorInfo()));
            }
            
            $stmt->execute($params);
            return $stmt;
            
        } catch (PDOException $e) {
            throw new Exception("Erro ao executar a operação no banco de dados: " . $e->getMessage());
        }
        return false;
    }


    function getLastSql(){
        return $this->lastSql;
    }

    function setTimeZone($t) {
        return $this->query("SET time_zone = ?", [$t]);
    }

    function getTime() {
        return $this->query_ar("SELECT NOW() as date");
    }

    function query_ar($qr, $params = array()) {
        return $this->toArray($this->query($qr, $params));
    }

    function getValueByIdCol($id, $col, $table=""){
        if($table=="") $table = $this->_table;
        $r = $this->query_ar("SELECT {$col} FROM {$table} WHERE id = ?", [$id]);
        return $r[0][$col];
    }
    function setValueByIdCol($id, $col, $val, $table = ""){
        if($table=="") $table = $this->_table;
        $stmt = $this->query("UPDATE {$table} SET {$col} = ? WHERE id = ?", [$val, $id]);
        return $stmt->rowCount() > 0;
    }

    function loadBy($field, $value){
        global $_dbHandle;
        $this->connect();
        $r = $this->query("SELECT * FROM {$this->_table} WHERE {$field} = ?", [$value]);
        $data = $this->getRow($r);
        $this->_prop = array();
        if ($data !== false) {
            foreach ($data as $k => $v) {
                $this->_prop[$k] = $v;
            }
            $this->editing = true;
            return true;
        }
        return false;
    }

    /** Load the model by 'id' */
    function load($id) {
        global $_dbHandle;
        $this->connect();
        $r = $this->query("SELECT * FROM {$this->_table} WHERE id = ?", [$id]);
        $data = $this->getRow($r);
        $this->_prop = array();
        if ($data !== false) {
            foreach ($data as $k => $v) {
                $this->_prop[$k] = $v;
            }
            $this->editing = true;
            return true;
        }
        return false;
    }

    function reload() {
        $this->load($this->get("id"));
    }
    
    function setToUpdate($v){
        $this->editing = $v;
    }

    /** Persist the model, inserting or updating if 'id' exists. */
    function persist($getsql = false) {
        global $_dbHandle;

        $sql = "";
        $params = array();
        $op = "";

        // Se não tiver ID ou não estiver em modo de edição, faz INSERT
        if (!isset($this->_prop["id"]) || $this->_prop["id"] === null || $this->_prop["id"] === '' || !$this->editing) {
            if($this->generateId){
                if (!isset($this->_prop["id"]) || $this->_prop["id"] === null || $this->_prop["id"] === '') {
                    $timestamp = date('YmdHis');
                    $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
                    $unique = substr(uniqid('', true), -4);
                    $this->_prop["id"] = $timestamp . $random . $unique;
                }
            } else {
                // Remove o ID para que o banco gere automaticamente
                unset($this->_prop["id"]);
            }
            
            $this->connect();
            
            $cols = array_keys($this->_prop);
            $placeholders = array_fill(0, count($cols), '?');
            
            $sql = "INSERT INTO {$this->_table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $params = array_values($this->_prop);
            $op = "insert";
        } else {
            // Se tiver ID e estiver em modo de edição, faz UPDATE
            $this->connect();
            
            $sets = array();
            foreach ($this->_prop as $k => $v) {
                if ($k != "id") {
                    $sets[] = "{$k} = ?";
                    $params[] = $v;
                }
            }
            $params[] = $this->_prop["id"];
            $sql = "UPDATE {$this->_table} SET " . implode(', ', $sets) . " WHERE id = ?";
            $op = "update";
        }
        
        if ($getsql) {
            return $sql;
        }
        
        $this->editing = true;
        $res = $this->query($sql, $params);
        
        if(!$this->generateId && $op=="insert"){
            $newId = $_dbHandle->lastInsertId();
            if ($newId) {
                $this->_prop["id"] = $newId;
            }
        }
        
        return $res;
    }

    function delete($id = "") {
        if ($id == "") {
            return $this->query("DELETE FROM {$this->_table} WHERE id = ?", [$this->_prop["id"]])->rowCount() > 0;
        } else {
            return $this->query("DELETE FROM {$this->_table} WHERE id = ?", [$id])->rowCount() > 0;
        }
    }

    /**
     * Executa a query $sqlQuery e retorna a coluna 'qtd' do primeiro registro
     */
    function countSql($sqlQuery){
        $res = $this->toArray($this->query($sqlQuery));
        return $res[0]["qtd"];
    }

    /**
     * Conta a quantidade de registros que uma query terá como resultado.
     */
    function countSql2($sqlQuery){
        $res = $this->query_ar("SELECT COUNT(*) as qtd FROM ({$sqlQuery}) as tabbb");
        return $res[0]["qtd"];
    }

    /**
     * Conta a quantidade de registros considerando o WHERE caso seja passado.
     * $where = ["coluna"=>"valor da coluna a ser igual"]
     */
    function count($where = null) {
        $params = array();
        $whereClause = "";
        
        if ($where != null) {
            $conditions = array();
            foreach ($where as $k => $v) {
                $conditions[] = "{$k} = ?";
                $params[] = $v;
            }
            $whereClause = "WHERE " . implode(" AND ", $conditions);
        }
        
        $sql = "SELECT COUNT(*) as qtd FROM {$this->_table} {$whereClause}";
        $res = $this->toArray($this->query($sql, $params));
        return $res[0]["qtd"];
    }

    /** Perform a select. */
    function select($where = null, $order = null, $from = null, $count = null) {
        $params = array();
        $whereClause = "";
        
        if ($where != null) {
            $conditions = array();
            foreach ($where as $k => $v) {
                $conditions[] = "{$k} = ?";
                $params[] = $v;
            }
            $whereClause = "WHERE " . implode(" AND ", $conditions);
        }
        
        $orderClause = $order ? "ORDER BY {$order}" : "";
        $limitClause = "";
        
        if ($count !== null) {
            $limitClause = "LIMIT ?, ?";
            $params[] = (int)$from;
            $params[] = (int)$count;
        }
        
        $sql = "SELECT * FROM {$this->_table} {$whereClause} {$orderClause} {$limitClause}";
        $res = $this->query($sql, $params);
        return $this->toArray($res);
    }

    /** MySQL result to array */
    function toArray($res) {
        return $res->fetchAll();
    }

    function prepareCaseWhenForSearch($term1, $onCols, $colWeight){
        $terms = preg_split('/[\s,\+]+/', $term1);
        $fieldSearchQueries = array();
        $c = 0;
        foreach ($onCols as $thisField) {
            foreach($terms as $kw){
                //$fieldSearchQueries[] = "CASE WHEN $thisField LIKE '%$kw%' THEN 1 ELSE 0 END";
                $fieldSearchQueries[] = "IF( $thisField LIKE '%$kw%', $colWeight[$c], 0)";
                //$fieldSearchQueries[] = "IF( $thisField = '$kw', ".($colWeight[$c]*2).", 0)";
                $fieldSearchQueries[] = "IF( $thisField LIKE '$kw', ".($colWeight[$c]*2).", 0)";
                
            }
            $c++;
        }
        return "(".implode(' + ', $fieldSearchQueries).")";
    }
    //$query = 'SELECT *, ' . implode(' + ' $fieldSearchQueries) . ' AS rank '
    //. 'FROM TABLE WHERE rank > 0 ORDER BY rank';

    function startTransaction(){
        global $_dbHandle;
        $_dbHandle->beginTransaction();
    }

    function commit(){
        global $_dbHandle;
        $_dbHandle->commit();
    }

    function rollback(){
        global $_dbHandle;
        $_dbHandle->rollBack();
    }
    

    function search($term, $fromTables, $plainWhere = '1', $onCols = array(), $colWeight = array(), $from=null, $count = null, $onlySql=false, $orderby="", $cols="", $group=""){
        $table = $fromTables;
        if($fromTables == NULL){
            $table = $this->_table;
        }

        
        if(strlen(trim($cols)) > 0){
            //$cols = ",".$cols;
        }else{
            $cols = "*";
        }
        if(strlen(trim($group)) > 0){
            $group = "GROUP BY ".$group;
        }
        if(trim($term) == ""){
            if(strlen(trim($orderby)) > 0){
                $orderby = "ORDER BY ".$orderby;
            }
            $sql = "SELECT $cols FROM $table WHERE $plainWhere $group $orderby";
        }else{        
            $caseWhen = $this->prepareCaseWhenForSearch($term, $onCols, $colWeight);
            $order = (trim($orderby) == "") ? "" : ','.$orderby;
            
            $sql = "SELECT * FROM (SELECT $cols, $caseWhen AS rankk from $table WHERE ($plainWhere) $group) as tab WHERE rankk > 0 ORDER BY rankk DESC $order";
            //echo $sql;
            //die();
        }
        if ($count != null) {
            $sql = $sql . " limit $from, $count";
        }
        if($onlySql){
            return $sql;
        }
        return $this->query_ar($sql);
    }
    
    

    /** Num of rows. */
    function getNumRows($res) {
        return $res->rowCount();
    }

    function getRow($result) {
        return $result->fetch();
    }

}

/*
class Model extends ModelMYSQL{

}
*/
