<?php
/**
 * TCC RRS – Database Layer
 * Singleton PDO wrapper with helper query methods.
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die('<div style="font-family:monospace;padding:20px;color:red;">
                    <strong>Database Connection Error:</strong><br>' .
                    htmlspecialchars($e->getMessage()) . '</div>');
            } else {
                die('<div style="font-family:sans-serif;padding:20px;">
                    A system error occurred. Please contact the administrator.</div>');
            }
        }
    }

    // ── Singleton access ──────────────────────────────────────────────────────
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // ── Query helpers ─────────────────────────────────────────────────────────

    /**
     * Run a SELECT and return all rows.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Run a SELECT and return a single row.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Run a SELECT and return a single column value.
     */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : null;
    }

    /**
     * Run INSERT / UPDATE / DELETE and return affected row count.
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Run INSERT and return the last inserted ID.
     */
    public function insert(string $sql, array $params = []): string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin / commit / rollback wrappers.
     */
    public function beginTransaction(): void  { $this->pdo->beginTransaction(); }
    public function commit(): void            { $this->pdo->commit(); }
    public function rollback(): void          { $this->pdo->rollBack(); }

    // ── Pagination helper ─────────────────────────────────────────────────────

    /**
     * Returns ['data' => [...], 'total' => N, 'pages' => N, 'page' => N, 'per_page' => N]
     */
    public function paginate(string $sql, array $params = [], int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        // Count total
        $countSql = "SELECT COUNT(*) FROM ({$sql}) AS _count_query";
        $total    = (int) $this->fetchValue($countSql, $params);

        // Fetch page
        $pagedSql = $sql . " LIMIT {$perPage} OFFSET {$offset}";
        $data     = $this->fetchAll($pagedSql, $params);

        return [
            'data'     => $data,
            'total'    => $total,
            'pages'    => (int) ceil($total / $perPage),
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    // ── Prevent cloning ───────────────────────────────────────────────────────
    private function __clone() {}
}

/**
 * Convenience function – returns the Database singleton.
 */
function db(): Database
{
    return Database::getInstance();
}
