<?php
/**
 * Class User
 * Gestion des utilisateurs
 */
class User {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crée un nouvel utilisateur
     * @param string $username
     * @param string $email
     * @param string $password
     * @return int|false ID de l'utilisateur créé ou false
     */
    public function create(string $username, string $email, string $password): int|false {
        // Vérifier si l'utilisateur existe déjà
        if ($this->existsByUsername($username) || $this->existsByEmail($email)) {
            return false;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $query = "INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)";
        $params = [
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash
        ];

        try {
            return (int) $this->db->insert($query, $params);
        } catch (PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère un utilisateur par ID
     * @param int $id
     * @return array|false
     */
    public function getById(int $id): array|false {
        $query = "SELECT id, username, email, created_at, last_login, is_active, role FROM users WHERE id = :id";
        return $this->db->fetchOne($query, [':id' => $id]);
    }

    /**
     * Récupère un utilisateur par username
     * @param string $username
     * @return array|false
     */
    public function getByUsername(string $username): array|false {
        $query = "SELECT * FROM users WHERE username = :username";
        return $this->db->fetchOne($query, [':username' => $username]);
    }

    /**
     * Récupère un utilisateur par email
     * @param string $email
     * @return array|false
     */
    public function getByEmail(string $email): array|false {
        $query = "SELECT * FROM users WHERE email = :email";
        return $this->db->fetchOne($query, [':email' => $email]);
    }

    /**
     * Vérifie si un username existe
     * @param string $username
     * @return bool
     */
    public function existsByUsername(string $username): bool {
        $query = "SELECT COUNT(*) as count FROM users WHERE username = :username";
        $result = $this->db->fetchOne($query, [':username' => $username]);
        return $result && $result['count'] > 0;
    }

    /**
     * Vérifie si un email existe
     * @param string $email
     * @return bool
     */
    public function existsByEmail(string $email): bool {
        $query = "SELECT COUNT(*) as count FROM users WHERE email = :email";
        $result = $this->db->fetchOne($query, [':email' => $email]);
        return $result && $result['count'] > 0;
    }

    /**
     * Vérifie le mot de passe
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Met à jour la date de dernière connexion
     * @param int $userId
     * @return bool
     */
    public function updateLastLogin(int $userId): bool {
        $query = "UPDATE users SET last_login = NOW() WHERE id = :id";
        return $this->db->execute($query, [':id' => $userId]) > 0;
    }

    /**
     * Met à jour le profil utilisateur
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function update(int $userId, array $data): bool {
        $allowedFields = ['username', 'email'];
        $updates = [];
        $params = [':id' => $userId];

        foreach ($allowedFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";

        try {
            return $this->db->execute($query, $params) > 0;
        } catch (PDOException $e) {
            error_log("Error updating user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Change le mot de passe
     * @param int $userId
     * @param string $newPassword
     * @return bool
     */
    public function changePassword(int $userId, string $newPassword): bool {
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $query = "UPDATE users SET password_hash = :password_hash WHERE id = :id";

        try {
            return $this->db->execute($query, [
                ':id' => $userId,
                ':password_hash' => $passwordHash
            ]) > 0;
        } catch (PDOException $e) {
            error_log("Error changing password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Désactive un utilisateur
     * @param int $userId
     * @return bool
     */
    public function deactivate(int $userId): bool {
        $query = "UPDATE users SET is_active = 0 WHERE id = :id";
        return $this->db->execute($query, [':id' => $userId]) > 0;
    }

    /**
     * Active un utilisateur
     * @param int $userId
     * @return bool
     */
    public function activate(int $userId): bool {
        $query = "UPDATE users SET is_active = 1 WHERE id = :id";
        return $this->db->execute($query, [':id' => $userId]) > 0;
    }

    /**
     * Récupère tous les utilisateurs (pour admin)
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll(int $limit = 50, int $offset = 0): array {
        $query = "SELECT id, username, email, created_at, last_login, is_active, role
                  FROM users
                  ORDER BY created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Vérifie si un utilisateur a un rôle donné
     * @param int $userId
     * @param string $role
     * @return bool
     */
    public function hasRole(int $userId, string $role): bool {
        $query = "SELECT role FROM users WHERE id = :id";
        $result = $this->db->fetchOne($query, [':id' => $userId]);
        return $result && $result['role'] === $role;
    }

    /**
     * Vérifie si un utilisateur est admin
     * @param int $userId
     * @return bool
     */
    public function isAdmin(int $userId): bool {
        return $this->hasRole($userId, 'admin');
    }

    /**
     * Vérifie si un utilisateur est modérateur ou admin
     * @param int $userId
     * @return bool
     */
    public function isModerator(int $userId): bool {
        $query = "SELECT role FROM users WHERE id = :id";
        $result = $this->db->fetchOne($query, [':id' => $userId]);
        return $result && in_array($result['role'], ['modo', 'admin']);
    }

    /**
     * Change le rôle d'un utilisateur (admin seulement)
     * @param int $userId
     * @param string $role
     * @return bool
     */
    public function setRole(int $userId, string $role): bool {
        if (!in_array($role, ['membre', 'modo', 'admin'])) {
            return false;
        }

        $query = "UPDATE users SET role = :role WHERE id = :id";
        return $this->db->execute($query, [
            ':id' => $userId,
            ':role' => $role
        ]) > 0;
    }
}
