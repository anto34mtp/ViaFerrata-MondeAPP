<?php
/**
 * Class Database
 * Gestion de la connexion à la base de données avec PDO
 * Pattern Singleton pour une instance unique
 */
class Database {

    private static ?Database $instance = null;
    private ?PDO $connection = null;

    private string $host;
    private string $dbname;
    private string $username;
    private string $password;
    private string $charset = 'utf8mb4';

    /**
     * Constructeur privé (pattern Singleton)
     */
    private function __construct() {
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;

        $this->connect();
    }

    /**
     * Récupère l'instance unique de Database
     * @return Database
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Établit la connexion PDO
     * @throws PDOException
     */
    private function connect(): void {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);

            // Force UTF-8 encoding pour toutes les connexions
            $this->connection->exec("SET character_set_client = utf8mb4");
            $this->connection->exec("SET character_set_connection = utf8mb4");
            $this->connection->exec("SET character_set_results = utf8mb4");
            $this->connection->exec("SET collation_connection = utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // En production, logger l'erreur au lieu de l'afficher
            if (ENVIRONMENT === 'development') {
                throw new PDOException("Erreur de connexion à la base de données: " . $e->getMessage());
            } else {
                throw new PDOException("Erreur de connexion à la base de données");
            }
        }
    }

    /**
     * Récupère la connexion PDO
     * @return PDO
     */
    public function getConnection(): PDO {
        return $this->connection;
    }

    /**
     * Exécute une requête préparée
     * @param string $query
     * @param array $params
     * @return PDOStatement
     */
    public function query(string $query, array $params = []): PDOStatement {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Récupère un seul résultat
     * @param string $query
     * @param array $params
     * @return array|false
     */
    public function fetchOne(string $query, array $params = []): array|false {
        $stmt = $this->query($query, $params);
        return $stmt->fetch();
    }

    /**
     * Récupère tous les résultats
     * @param string $query
     * @param array $params
     * @return array
     */
    public function fetchAll(string $query, array $params = []): array {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert et retourne le dernier ID inséré
     * @param string $query
     * @param array $params
     * @return string
     */
    public function insert(string $query, array $params = []): string {
        $this->query($query, $params);
        return $this->connection->lastInsertId();
    }

    /**
     * Update/Delete et retourne le nombre de lignes affectées
     * @param string $query
     * @param array $params
     * @return int
     */
    public function execute(string $query, array $params = []): int {
        $stmt = $this->query($query, $params);
        return $stmt->rowCount();
    }

    /**
     * Commence une transaction
     */
    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }

    /**
     * Valide une transaction
     */
    public function commit(): bool {
        return $this->connection->commit();
    }

    /**
     * Annule une transaction
     */
    public function rollBack(): bool {
        return $this->connection->rollBack();
    }

    /**
     * Empêche le clonage de l'instance (Singleton)
     */
    private function __clone() {}

    /**
     * Empêche la désérialisation de l'instance (Singleton)
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
