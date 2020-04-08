<?php

namespace Volyanytsky\Database;

class MyPdo
{
  private $connection;
  private $trust;

  private $type;
  private $host;
  private $name;
  private $charset;
  private $user;
  private $pass;
  private $options;

  public function __construct(DatabaseCreditsInterface $db, $trust = false)
  {
    $this->type = $db->getType();
    $this->host = $db->getHost();
    $this->name = $db->getName();
    $this->charset = $db->getCharset();
    $this->user = $db->getUser();
    $this->pass = $db->getPass();
    $this->setConnectionOptions($db->getOptions());
    $this->trust = boolval($trust);
  }

  private function connect()
  {
    if(empty($this->connection))
    {
      $dsn = "$this->type:host=$this->host;dbname=$this->name;charset=$this->charset";
      try {
        $this->connection = new \PDO($dsn, $this->user, $this->pass, $this->options);
      } catch (\PDOException $e) {
        throw new MyPdoException($e->getMessage());
      }
    }
    return $this->connection;
  }

  public function setConnectionOptions(array $options)
  {
    $this->options = $options;
    $this->connection = null;
  }

  public function delete($table, $key, $value)
  {
    $this->ensureTableExists($table);
    $sql = "DELETE FROM `$table` WHERE `$key` = :$key";
    $this->execute($sql, self::serializeData([$key => $value]));
  }

  public function insert($table, array $data)
  {
    $this->ensureTableExists($table);
    $columns = implode(",", array_keys($data));
    $values = [];
    foreach($data as $index => $value)
    {
      $values[] = ":".$index;
    }
    $values = implode(",", $values);
    $sql = "INSERT INTO `$table`($columns) VALUES($values)";
    return $this->execute($sql, self::serializeData($data), true);
  }

  public function update($table, array $data, $key = 'id')
  {
    $this->ensureTableExists($table);
    if(!isset($data[$key]))
    {
      throw new MyPdoException("Unable to update $table: data array does not contain $key");
    }
    $sql = "UPDATE `$table` SET ";
    foreach(array_keys($data) as $index)
    {
      if($index != $key)
      {
        $sql .= "$index = :$index,";
      }
    }
    $sql = rtrim($sql, ",");
    $sql .= " WHERE $key = :$key";

    $values = self::serializeData($data);

    $this->execute($sql, $values);
  }

  public function insertOrUpdate($table, array $data, array $keys = ['id'])
  {
    $this->ensureTableExists($table);
    $columns = implode(",", array_keys($data));
    $values = [];
    foreach($data as $index => $value)
    {
      $values[] = ":".$index;
    }
    $values = implode(",", $values);
    $sql = "INSERT INTO `$table`($columns) VALUES($values) ON DUPLICATE KEY UPDATE ";
    foreach(array_keys($data) as $column)
    {
      if(!in_array($column, $keys))
      {
        $sql .= "$column=VALUES($column),";
      }
    }
    $sql = rtrim($sql,",");
    $this->execute($sql, self::serializeData($data));
  }

  public function execute($query, $args = [], $isInsert = false)
  {
    try {

      $connection = $this->connect();
      $stn = $connection->prepare($query);
      $stn->execute($args);
      if($isInsert)
      {
        return $connection->lastInsertId();
      }
      return $stn;

    } catch (\PDOException $e) {
      throw new MyPdoException($e->getMessage());
    }

  }

  public function fetch($query, $args = [])
  {
    return $this->execute($query, $args)->fetch();
  }

  public function fetchAll($query, $args = [])
  {
    return $this->execute($query, $args)->fetchAll();
  }

  public function fetchColumn($query, $args = [])
  {
    return $this->execute($query, $args)->fetchColumn();
  }

  static public function createPlaceholders(array $args)
  {
    $placeholders = [];
    for($i = 0; $i < count($args); $i++)
    {
      $placeholders[$i] = '?';
    }
    return implode(",", $placeholders);
  }

  private function tableExists($table)
  {
    $data = $this->fetchAll("SHOW TABLES FROM ".$this->name);
    $tables = array_column($data, 'Tables_in_'.$this->name);
    return in_array($table, $tables);
  }

  static private function serializeData($data)
  {
    $serialized = [];
    foreach($data as $key => $value)
    {
      $serialized[":".$key] = $value;
    }
    return $serialized;
  }

  private function ensureTableExists($table)
  {
    if(!$this->trust)
    {
      if(!$this->tableExists($table))
      {
        throw new MyPdoException("$table does not exist");
      }
    }
  }
}

 ?>
