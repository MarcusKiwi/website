<?php

class database
{
    private $pdo;

    function get() {
        return $this->pdo;
    }

    function __construct() {
        global $config;

        $host = "127.0.0.1";
        $user = $config['db_user'];
        $pass = $config['db_pass'];
        $charset = "utf8mb4";

        $dsn = "mysql:host=$host;charset=$charset";
        $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            echo 'mysql error';
            die;
        }
    }
}