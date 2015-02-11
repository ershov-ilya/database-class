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

    public function errors()
    {
        $info = $this->dbh->errorInfo();
        if(!empty($info[0])){
            if(DEBUG) print $info[2]."\n";
            logMessage($info[2]);
        }
    }

    public function __construct($input)
    {
        $this->cache = false;

        $input_type=gettype($input);
        switch($input_type)
        {
            case 'string':
                /* @var array $pdoconfig */
                require_once($input);
                extract($pdoconfig);
                break;
            case 'array':
                extract($input);
                break;
        }

        if(!(isset($dbtype) && isset($dbhost) && isset($dbname) && isset($dbuser) && isset($dbpass))) return false;
        try
        {
            // Save stream
            $this->dbh = new PDO("$dbtype:host=$dbhost;dbname=$dbname" , $dbuser, $dbpass,
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
        return $result;
    }

    public function getOne($table, $id, $id_field_name='id')
    {
        $sql = "SELECT * FROM `$table` WHERE `$id_field_name`='$id';";
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        if(empty($rows)) return array();
        $result = $rows[0];
//        foreach($rows as $row){} // Изъятие из потока?
        $this->errors();
        return $result;
    }

    public function getAllSQL($sql)
    {
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        $this->errors();
        return $rows;
    }

    public function getAll($sql)
    {
        // TODO: Переписать
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        $this->errors();
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
        if(empty($success)) return false;
        $lastID = $this->dbh->lastInsertId();
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