<?php
/**
 * Wrapper PDO — conexión segura a MySQL.
 * Usa preparaciones parametrizadas en todos los métodos.
 */
class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // Desactivar emulación → SQL injection imposible
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Nunca exponer detalles de la excepción al usuario
                error_log('[DB] Conexión fallida: ' . $e->getMessage());
                http_response_code(503);
                die('Servicio temporalmente no disponible.');
            }
        }
        return self::$instance;
    }

    /**
     * Ejecuta un SELECT y devuelve todos los filas.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ejecuta un SELECT y devuelve la primera fila.
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Ejecuta INSERT/UPDATE/DELETE; devuelve filas afectadas.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Ejecuta INSERT y devuelve el último ID insertado.
     */
    public static function insert(string $sql, array $params = []): string
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return self::getInstance()->lastInsertId();
    }

    public static function beginTransaction(): void  { self::getInstance()->beginTransaction(); }
    public static function commit(): void            { self::getInstance()->commit(); }
    public static function rollBack(): void          { self::getInstance()->rollBack(); }
}
