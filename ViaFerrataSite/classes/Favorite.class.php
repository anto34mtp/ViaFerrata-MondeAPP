<?php
/**
 * Class Favorite
 * Gestion des favoris utilisateurs
 */
class Favorite {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Ajoute ou met à jour un favori
     * @param int $userId
     * @param int $viaId
     * @param string $status ('to_do' ou 'done')
     * @return bool
     */
    public function addOrUpdate(int $userId, int $viaId, string $status = 'to_do'): bool {
        // Vérifier si le favori existe déjà
        $existing = $this->get($userId, $viaId);

        if ($existing) {
            // Mettre à jour
            $query = "UPDATE favorites SET status = :status, updated_at = NOW()
                      WHERE user_id = :user_id AND via_id = :via_id";
        } else {
            // Créer
            $query = "INSERT INTO favorites (user_id, via_id, status)
                      VALUES (:user_id, :via_id, :status)";
        }

        try {
            return $this->db->execute($query, [
                ':user_id' => $userId,
                ':via_id' => $viaId,
                ':status' => $status
            ]) > 0;
        } catch (PDOException $e) {
            error_log("Error adding/updating favorite: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère un favori spécifique
     * @param int $userId
     * @param int $viaId
     * @return array|false
     */
    public function get(int $userId, int $viaId): array|false {
        $query = "SELECT * FROM favorites WHERE user_id = :user_id AND via_id = :via_id";
        return $this->db->fetchOne($query, [
            ':user_id' => $userId,
            ':via_id' => $viaId
        ]);
    }

    /**
     * Supprime un favori
     * @param int $userId
     * @param int $viaId
     * @return bool
     */
    public function remove(int $userId, int $viaId): bool {
        $query = "DELETE FROM favorites WHERE user_id = :user_id AND via_id = :via_id";
        return $this->db->execute($query, [
            ':user_id' => $userId,
            ':via_id' => $viaId
        ]) > 0;
    }

    /**
     * Toggle le statut d'un favori (to_do <-> done)
     * @param int $userId
     * @param int $viaId
     * @return bool
     */
    public function toggleStatus(int $userId, int $viaId): bool {
        $favorite = $this->get($userId, $viaId);

        if (!$favorite) {
            return false;
        }

        $newStatus = $favorite['status'] === 'to_do' ? 'done' : 'to_do';

        return $this->addOrUpdate($userId, $viaId, $newStatus);
    }

    /**
     * Récupère tous les favoris d'un utilisateur
     * @param int $userId
     * @param string|null $status Filter by status ('to_do', 'done', or null for all)
     * @return array
     */
    public function getByUser(int $userId, ?string $status = null): array {
        $statusCondition = $status ? "AND f.status = :status" : "";

        $query = "SELECT f.*, v.*, d.name as department_name, d.code as department_code,
                  vrs.total_ratings, vrs.avg_general, vrs.avg_beauty, vrs.avg_difficulty, vrs.avg_overall
                  FROM favorites f
                  INNER JOIN vias v ON f.via_id = v.id
                  LEFT JOIN departments d ON v.department_id = d.code
                  LEFT JOIN via_ratings_summary vrs ON v.id = vrs.via_id
                  WHERE f.user_id = :user_id $statusCondition
                  ORDER BY f.updated_at DESC";

        $params = [':user_id' => $userId];
        if ($status) {
            $params[':status'] = $status;
        }

        return $this->db->fetchAll($query, $params);
    }

    /**
     * Compte les favoris d'un utilisateur
     * @param int $userId
     * @param string|null $status
     * @return int
     */
    public function countByUser(int $userId, ?string $status = null): int {
        $statusCondition = $status ? "AND status = :status" : "";
        $query = "SELECT COUNT(*) as total FROM favorites WHERE user_id = :user_id $statusCondition";

        $params = [':user_id' => $userId];
        if ($status) {
            $params[':status'] = $status;
        }

        $result = $this->db->fetchOne($query, $params);
        return $result ? (int)$result['total'] : 0;
    }

    /**
     * Vérifie si une via est dans les favoris d'un utilisateur
     * @param int $userId
     * @param int $viaId
     * @return bool
     */
    public function isFavorite(int $userId, int $viaId): bool {
        return $this->get($userId, $viaId) !== false;
    }

    /**
     * Récupère le statut d'un favori
     * @param int $userId
     * @param int $viaId
     * @return string|null 'to_do', 'done', or null if not favorite
     */
    public function getStatus(int $userId, int $viaId): ?string {
        $favorite = $this->get($userId, $viaId);
        return $favorite ? $favorite['status'] : null;
    }
}
