<?php


/*

Две дополнительные таблицы для валидных и для не валидных почт

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `tbl_fail`;
CREATE TABLE `tbl_fail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `i_id` int(11) NOT NULL,
  `m_mail` varchar(255) NOT NULL,
  `err` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `tbl_valid`;
CREATE TABLE `tbl_valid` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `i_id` int(11) NOT NULL,
  `m_mail` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

*/


class DBClass
{
    private
        $server, $user, $pass, $dbname, $db;

    function __construct($server, $user, $pass, $dbname)
    {
        $this->server = $server;
        $this->user = $user;
        $this->pass = $pass;
        $this->dbname = $dbname;
        $this->openConnection();
    }

    public function openConnection()
    {
        if (!$this->db) {
            $connection = mysqli_connect($this->server, $this->user, $this->pass);
            if ($connection) {
                $selectDB = mysqli_select_db($connection, $this->dbname);
                if ($selectDB) {
                    $this->db = $connection;
                    mysqli_query($this->db, 'SET NAMES UTF8');
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    public function select($arrFields = '[*]', $from, $where = null, $order = null, $limit = null, $offset = 0)
    {
        $fetched = array();
        $fields = implode(',', $arrFields);
        $sql = 'SELECT ' . $fields . ' FROM ' . $from;
        if ($where != null) $sql .= ' WHERE ' . $where;
        if ($order != null) $sql .= ' ORDER BY ' . $order;
        //Чтобы снизить потребление памяти и не забирать сразу все 100500 тысяч записей.
        //При необходимости: $offset по умолчанию 0 - берем сначала. Добавляется кратно $limit в вызове метода
        if ($limit != null) $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $query = mysqli_query($this->db, $sql);
        if ($query) {
            $rows = mysqli_num_rows($query);
            for ($i = 0; $i < $rows; $i++) {
                $results = mysqli_fetch_assoc($query);
                $key = array_keys($results);
                $numKeys = count($key);
                for ($x = 0; $x < $numKeys; $x++) {
                    $fetched[$i][$key[$x]] = $results[$key[$x]];
                }
            }
            return $fetched;
        } else {
            return false;
        }
    }

    public function insert($table, $arrValues, $arrRows = null)
    {
        $insert = 'INSERT INTO ' . $table;
        if ($arrRows != null) {
            $rows = implode(',', $arrRows);
            $insert .= ' (' . $rows . ')';
        }
        $numValues = count($arrValues);
        for ($i = 0; $i < $numValues; $i++) {
            if (is_string($arrValues[$i])) $arrValues[$i] = "'$arrValues[$i]'";
        }
        $values = implode(',', $arrValues);
        $insert .= ' VALUES (' . $values . ')';
        $ins = mysqli_query($this->db, $insert);
        return ($ins) ? true : false;

    }

    public function delete($table, $where = null)
    {
        $sql = 'DELETE FROM ' . $table . ' WHERE ' . $where;
        if ($where == null) {
            $sql = 'DELETE ' . $table;
        }
        $deleted = mysqli_query($this->db, $sql);
        return ($deleted) ? true : false;
    }

    public function count($table, $where = null)
    {
        $sql = 'SELECT COUNT(*) as total FROM ' . $table;
        if ($where != null) $sql .= ' WHERE ' . $where;

        $result = mysqli_query($this->db, $sql);
        $data = mysqli_fetch_assoc($result);

        return $data['total'];
    }

}