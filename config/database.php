<?php
class Database {
    public static function connect() {
        $host = 'localhost';
        $db   = 'sistema_licitacao';
        $user = 'user_cglic';
        $pass = 'Numse!2020';
        $charset = 'utf8mb4';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            return $pdo;
        } catch (PDOException $e) {
            die("Erro na conexão: " . $e->getMessage());
        }
    }
}
