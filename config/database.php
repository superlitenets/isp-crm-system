<?php

class Database {
    private static ?PDO $connection = null;

    public static function getConnection(): PDO {
        if (self::$connection === null) {
            $host = getenv('PGHOST');
            $port = getenv('PGPORT');
            $dbname = getenv('PGDATABASE');
            $user = getenv('PGUSER');
            $password = getenv('PGPASSWORD');

            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            
            try {
                self::$connection = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$connection;
    }
}
