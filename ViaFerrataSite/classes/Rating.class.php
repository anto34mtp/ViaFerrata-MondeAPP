<?php
/**
 * Class Rating
 * Gestion des notes/évaluations des via ferrata
 */
class Rating {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crée une note pour une via
     * @param int $viaId
     * @param float $ratingGeneral
     * @param float $ratingBeauty
     * @param float $ratingDifficulty
     * @param int|null $userId
     * @param string $visitorHash
     * @param string|null $ipAddress
     * @return int|false
     */
    public function create(
        int $viaId,
        float $ratingGeneral,
        float $ratingBeauty,
        float $ratingDifficulty,
        ?int $userId,
        string $visitorHash,
        ?string $ipAddress
    ): int|false {
        // Vérifier si l'utilisateur/visiteur a déjà voté
        if ($this->hasVoted($viaId, $visitorHash)) {
            return false;
        }

        $query = "INSERT INTO ratings (via_id, user_id, visitor_hash, rating_general, rating_beauty, rating_difficulty, ip_address)
                  VALUES (:via_id, :user_id, :visitor_hash, :rating_general, :rating_beauty, :rating_difficulty, :ip_address)";

        try {
            return (int) $this->db->insert($query, [
                ':via_id' => $viaId,
                ':user_id' => $userId,
                ':visitor_hash' => $visitorHash,
                ':rating_general' => $ratingGeneral,
                ':rating_beauty' => $ratingBeauty,
                ':rating_difficulty' => $ratingDifficulty,
                ':ip_address' => $ipAddress
            ]);
        } catch (PDOException $e) {
            error_log("Error creating rating: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si un visiteur a déjà voté pour une via
     * @param int $viaId
     * @param string $visitorHash
     * @return bool
     */
    public function hasVoted(int $viaId, string $visitorHash): bool {
        $query = "SELECT COUNT(*) as count FROM ratings WHERE via_id = :via_id AND visitor_hash = :visitor_hash";
        $result = $this->db->fetchOne($query, [
            ':via_id' => $viaId,
            ':visitor_hash' => $visitorHash
        ]);

        return $result && $result['count'] > 0;
    }

    /**
     * Récupère toutes les notes d'une via
     * @param int $viaId
     * @return array
     */
    public function getByVia(int $viaId): array {
        $query = "SELECT r.*, u.username
                  FROM ratings r
                  LEFT JOIN users u ON r.user_id = u.id
                  WHERE r.via_id = :via_id
                  ORDER BY r.created_at DESC";

        return $this->db->fetchAll($query, [':via_id' => $viaId]);
    }

    /**
     * Récupère les moyennes des notes d'une via
     * @param int $viaId
     * @return array|false
     */
    public function getAverages(int $viaId): array|false {
        $query = "SELECT
                    COUNT(*) as total_ratings,
                    ROUND(AVG(rating_general), 1) as avg_general,
                    ROUND(AVG(rating_beauty), 1) as avg_beauty,
                    ROUND(AVG(rating_difficulty), 1) as avg_difficulty,
                    ROUND((AVG(rating_general) + AVG(rating_beauty) + AVG(rating_difficulty)) / 3, 1) as avg_overall
                  FROM ratings
                  WHERE via_id = :via_id";

        return $this->db->fetchOne($query, [':via_id' => $viaId]);
    }

    /**
     * Récupère la note d'un utilisateur pour une via
     * @param int $viaId
     * @param string $visitorHash
     * @return array|false
     */
    public function getUserRating(int $viaId, string $visitorHash): array|false {
        $query = "SELECT * FROM ratings WHERE via_id = :via_id AND visitor_hash = :visitor_hash";
        return $this->db->fetchOne($query, [
            ':via_id' => $viaId,
            ':visitor_hash' => $visitorHash
        ]);
    }

    /**
     * Met à jour une note existante
     * @param int $ratingId
     * @param float $ratingGeneral
     * @param float $ratingBeauty
     * @param float $ratingDifficulty
     * @return bool
     */
    public function update(
        int $ratingId,
        float $ratingGeneral,
        float $ratingBeauty,
        float $ratingDifficulty
    ): bool {
        $query = "UPDATE ratings
                  SET rating_general = :rating_general,
                      rating_beauty = :rating_beauty,
                      rating_difficulty = :rating_difficulty
                  WHERE id = :id";

        return $this->db->execute($query, [
            ':id' => $ratingId,
            ':rating_general' => $ratingGeneral,
            ':rating_beauty' => $ratingBeauty,
            ':rating_difficulty' => $ratingDifficulty
        ]) > 0;
    }

    /**
     * Supprime une note
     * @param int $ratingId
     * @return bool
     */
    public function delete(int $ratingId): bool {
        $query = "DELETE FROM ratings WHERE id = :id";
        return $this->db->execute($query, [':id' => $ratingId]) > 0;
    }

    /**
     * Génère un hash unique pour un visiteur (basé sur IP + User Agent + Cookie)
     * @return string
     */
    public static function generateVisitorHash(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Utiliser ou créer un cookie unique pour ce visiteur
        $cookieName = 'visitor_id';
        if (!isset($_COOKIE[$cookieName])) {
            $cookieValue = bin2hex(random_bytes(16));
            setcookie($cookieName, $cookieValue, time() + (365 * 24 * 60 * 60), '/', '', false, true);
        } else {
            $cookieValue = $_COOKIE[$cookieName];
        }

        return hash('sha256', $ip . $userAgent . $cookieValue);
    }
}
