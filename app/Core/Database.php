<?php

namespace App\Core;

use PDO;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $config = require __DIR__ . '/../../config/app.php';
            $dir = dirname($config['database']);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            self::$connection = new PDO('sqlite:' . $config['database']);
            self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$connection->exec('PRAGMA foreign_keys = ON');
        }

        return self::$connection;
    }
}

