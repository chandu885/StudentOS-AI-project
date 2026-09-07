<?php
// backend/config/database.php

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection = null;

    private function __construct() {
        $config = Config::getInstance();
        $host = $config->get('db_host', 'localhost');
        $user = $config->get('db_user', 'root');
        $pass = $config->get('db_pass', '');
        $name = $config->get('db_name', 'studentos_ai');
        $port = (int)$config->get('db_port', 3306);
        $charset = $config->get('db_charset', 'utf8mb4');

        $this->connection = new mysqli($host, $user, $pass, $name, $port);

        if ($this->connection->connect_error) {
            error_log("Database Connection Error: " . $this->connection->connect_error);
            throw new Exception("Database connection failed: " . $this->connection->connect_error);
        }

        $this->connection->set_charset($charset);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function prepare($query) {
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            error_log("Database Prepare Error: " . $this->connection->error . " Query: " . $query);
            throw new Exception("Failed to prepare statement: " . $this->connection->error);
        }
        return $stmt;
    }

    public function query($sql) {
        $result = $this->connection->query($sql);
        if ($result === false) {
            error_log("Database Query Error: " . $this->connection->error . " Query: " . $sql);
            throw new Exception("Database query failed: " . $this->connection->error);
        }
        return $result;
    }

    public function lastInsertId() {
        return $this->connection->insert_id;
    }

    public function affectedRows() {
        return $this->connection->affected_rows;
    }

    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }

    public function beginTransaction() {
        return $this->connection->begin_transaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollback() {
        return $this->connection->rollback();
    }

    public function ping() {
        return $this->connection->ping();
    }

    private function __clone() {}
    public function __wakeup() {}
}
