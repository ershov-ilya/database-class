<?php
/**
 * Created by PhpStorm.
 * User: ershov-ilya
 * Website: ershov.pw
 * GitHub : https://github.com/ershov-ilya
 * Date: 07.02.2015
 * Time: 0:32
 */

class Database
{
    public $dbh; // Database handler
    public $cache;
    public $last;

    public static function test(){
        return "Database class: OK";
    }

    public function errors()
    {
        $info = $this->dbh->errorInfo();
        if(!empty($info[2])){
            if(DEBUG) print $info[2]."\n";
            if(function_exists('logMessage')) logMessage($info[2]);
        }
    }

    public function sayError(){
        if(DEBUG) print_r($this->dbh->errorInfo());
    }

    public function __construct($input)
    {
        $this->cache = false;
        $this->last = array();

        $input_type=gettype($input);
        switch($input_type)
        {
            case 'string':
                // Ждём путь к файлу
                /* @var array $pdoconfig */
                if(is_file($input)) {
                    require($input);
                    extract($pdoconfig);
                }
                else{
                    throw new Exception('Нет настроек БД',500);
                }
                break;
            case 'array':
                // Массив с настройками
                extract($input);
                break;
        }

        if(!(isset($dbtype) && isset($dbhost) && isset($dbname) && isset($dbuser) && isset($dbpass))) return false;
        try
        {
            /* @var PDO $DBH */
            // Save stream
            $this->dbh = $DBH = new PDO("$dbtype:host=$dbhost;dbname=$dbname" , $dbuser, $dbpass,
                array (PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
            );
        }
        catch (PDOException $e ) {
            if(DEBUG) print 'Exception: ' . $e-> getMessage();
            logMessage('Exception: ' . $e-> getMessage());
            exit();
        }
    } // function __construct

    public function getOneSQL($sql)
    {
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        if(empty($rows)) return array();
        $result = $rows[0];
//        foreach($rows as $row){} // Изъятие из потока?
        $this->errors();
        $this->last['getOneSQL']=$result;
        return $result;
    }

    public function getOne($table, $id, $id_field_name='id', $filter='')
    {
        $sql = "SELECT ";
        if(empty($filter)) {
            $sql .= "*";
        }
        else{
            if(is_array($filter)){
                $sql.=implode(',',$filter);
            }
            else{
                $sql.=$filter;
            }
        }

        $sql .= " FROM `$table` WHERE `$id_field_name`='$id';";
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        if(empty($rows)) return array();
        $result = $rows[0];
//        foreach($rows as $row){} // Изъятие из потока?
        $this->errors();
        $this->last['getOne']=$result;
        return $result;
    }

    public function toKeyValue($storage='getTable'){
        $res=array();
        foreach($this->last[$storage] as $row){
            $i=0;
            $key='';
            $val='';
            foreach($row as $field){
                if($i==0) $key=$field;
                if($i==1) $val=$field;
                $i++;
                if($i>1) break;
            }
            $res[$key]=$val;
        }
        return $res;
    }

    function pick($columns=NULL, $map=array(), $storage='getOne'){
        if($columns==NULL) {
            if(isset($this->last[$storage])) return $this->last[$storage];
            return NULL;
        }
        if (is_string($columns)){
            $columns=explode(',',$columns);
        }

        $columns_count=count($columns);

        $res=array();
        foreach($columns as $column){
            if(isset($this->last[$storage][$column])){
                $resindex=$column;
                if(isset($map[$column])) $resindex=$map[$column];
                $res[$resindex]=$this->last[$storage][$column];
            }
        }

        if($columns_count==1) return $res[$column];
        return $res;
    }

    function pickLine($field, $key, $map=array(), $filter=array(), $storage='getTableByKey'){
        $doFiltrate=false;
        if(!empty($filter)){
            $doFiltrate=true;
            if(gettype($filter)=='string') $filter=explode(',',$filter);
        }
        $service=NULL;
        foreach($this->last[$storage] as $arr){
            if($arr[$field]==$key) $service=$arr;
        }
        if(empty($service)) return NULL;
        unset($service['id']);
        if(empty($map)) return $service;

        $translated=array();
        foreach($service as $k => $v){
            $resindex=$k;
            if(isset($map[$k])) $resindex=$map[$k];
            $translated[$resindex]=$v;
            if($doFiltrate && !in_array($resindex,$filter)){
                unset($translated[$resindex]);
            }
        }
        return $translated;
    }

    // Транспонирование (поворот) таблицы
    function transpose($storage='getTable'){
        $i=0;
        $transpose=array();
        $columns=array();
        foreach($this->last[$storage][0] as $k => $v){
            $columns[]=$k;
        }
        foreach($this->last[$storage] as $el){
            foreach($columns as $column){
                $transpose[$column][$i]=$el[$column];
            }
            $i++;
        }
        $this->last['group']=$transpose;
        return $transpose;
    }

    public function get($sql)
    {
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        $this->errors();
        $this->last['get']=$rows;
        return $rows;
    }

    public function getTable($table, $columns='', $from=0, $limit=-1)
    {
        $page='';
        if(isset($limit) && $limit>=0) $page = "LIMIT $from, $limit";
        if(empty($columns)) $sql = "SELECT * FROM `$table` $page;";
        else{
            if(gettype($columns)=='string') $columns=explode(',',$columns);
            if(is_array($columns)) $columns = "`".implode("`,`",$columns)."`";
            $columns = preg_replace('/;/', '', $columns);
            $sql = "SELECT $columns FROM `$table` $page;";
        }
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        $this->errors();
        $this->last['getTable']=$rows;
        return $rows;
    }

    public function getTableByKey($table, $key, $keyColumnName='scope')
    {
        $sql = "SELECT * FROM `".$table."` WHERE $keyColumnName='".$key."';";
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        $this->errors();
        $this->last['getTableByKey']=$rows;
        return $rows;
    }

    public function getCount($sql)
    {
        // TODO: Неэкономичная функция, надо поправить
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        $count = count($rows);
        $this->errors();
        $this->last['getCount']=$rows;
        return $count;
    }

    public function putOne($table, $data){
        $fields=array();
        $placeholders=array();
        foreach($data as $key => $val){
            $fields[]='`'.$key.'`';
            $placeholders[]=':'.$key;
        }
        $sql = "INSERT INTO `".$table."` (".implode(', ',$fields).") VALUES (".implode(', ',$placeholders).");";

        $stmt = $this->dbh->prepare($sql);
        foreach($data as $key => $val){
            $stmt->bindParam(':'.$key, $data[$key]);
        }
        $success = $stmt->execute();

        if(empty($success)) {
            if(DEBUG){
                print "ERROR:\n";
                print_r($stmt->errorInfo());
            }
            return false;
        }

        $lastID = $this->dbh->lastInsertId();
        $this->last['putOne']=$lastID;
        return $lastID;
    }

    public function updateOne($table, $id, $data, $id_field_name='id'){
        $fields=array();
        $placeholders=array();
        foreach($data as $key => $val){
            $fields[]='`'.$key.'`';
            $placeholders[]=':'.$key;
        }
        $sql = "UPDATE `".$table."` SET ";

        $count = count($data);
        $i=0;
        foreach($data as $key => $val){
            $sql .= "`$key`=:$key";
            $i++;
            if($i<$count) $sql .= ",";
            $sql .= " ";
        }
        $sql .= " WHERE `$id_field_name`='".$id."';";

        $stmt = $this->dbh->prepare($sql);
        foreach($data as $key => $val){
            $stmt->bindParam(':'.$key, $data[$key]);
        }
        $success = $stmt->execute();
        if($success) return true;
        return false;
    }
} // class Database