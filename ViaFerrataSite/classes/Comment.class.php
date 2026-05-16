<?php
/**
 * Class Comment
 * Gestion des commentaires sur les via ferrata
 */
class Comment {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crée un commentaire
     * @param int $viaId
     * @param string $authorName
     * @param string $content
     * @param int|null $userId
     * @param string $visitorHash
     * @param string|null $authorEmail
     * @param string|null $ipAddress
     * @return int|false
     */
    public function create(
        int $viaId,
        string $authorName,
        string $content,
        ?int $userId,
        string $visitorHash,
        ?string $authorEmail = null,
        ?string $ipAddress = null
    ): int|false {
        // Vérifier si le visiteur a déjà commenté cette via
        if ($this->hasCommented($viaId, $visitorHash)) {
            return false;
        }

        $query = "INSERT INTO comments (via_id, user_id, visitor_hash, author_name, author_email, content, ip_address)
                  VALUES (:via_id, :user_id, :visitor_hash, :author_name, :author_email, :content, :ip_address)";

        try {
            return (int) $this->db->insert($query, [
                ':via_id' => $viaId,
                ':user_id' => $userId,
                ':visitor_hash' => $visitorHash,
                ':author_name' => $authorName,
                ':author_email' => $authorEmail,
                ':content' => $content,
                ':ip_address' => $ipAddress
            ]);
        } catch (PDOException $e) {
            error_log("Error creating comment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si un visiteur a déjà commenté une via
     * @param int $viaId
     * @param string $visitorHash
     * @return bool
     */
    public function hasCommented(int $viaId, string $visitorHash): bool {
        $query = "SELECT COUNT(*) as count FROM comments WHERE via_id = :via_id AND visitor_hash = :visitor_hash";
        $result = $this->db->fetchOne($query, [
            ':via_id' => $viaId,
            ':visitor_hash' => $visitorHash
        ]);

        return $result && $result['count'] > 0;
    }

    /**
     * Récupère tous les commentaires d'une via
     * @param int $viaId
     * @param bool $onlyApproved
     * @return array
     */
    public function getByVia(int $viaId, bool $onlyApproved = true): array {
        $approvedCondition = $onlyApproved ? "AND c.is_approved = 1" : "";

        $query = "SELECT c.*, u.username
                  FROM comments c
                  LEFT JOIN users u ON c.user_id = u.id
                  WHERE c.via_id = :via_id AND (c.parent_id IS NULL) $approvedCondition
                  ORDER BY c.created_at DESC";

        return $this->db->fetchAll($query, [':via_id' => $viaId]);
    }

    /**
     * Récupère les réponses à un commentaire
     */
    public function getByParent(int $parentId, bool $onlyApproved = true): array {
        $approvedCondition = $onlyApproved ? "AND c.is_approved = 1" : "";
        $query = "SELECT c.*, u.username
                  FROM comments c
                  LEFT JOIN users u ON c.user_id = u.id
                  WHERE c.parent_id = :parent_id $approvedCondition
                  ORDER BY c.created_at ASC";
        return $this->db->fetchAll($query, [':parent_id' => $parentId]);
    }

    /**
     * Crée une réponse à un commentaire existant
     */
    public function createReply(
        int $viaId,
        int $parentId,
        string $authorName,
        string $content,
        ?int $userId,
        string $visitorHash,
        ?string $authorEmail = null,
        ?string $ipAddress = null
    ): int|bool {
        $query = "INSERT INTO comments (via_id, parent_id, user_id, visitor_hash, author_name, author_email, content, ip_address, is_approved)
                  VALUES (:via_id, :parent_id, :user_id, :visitor_hash, :author_name, :author_email, :content, :ip_address, 1)";
        try {
            return (int) $this->db->insert($query, [
                ':via_id'       => $viaId,
                ':parent_id'    => $parentId,
                ':user_id'      => $userId,
                ':visitor_hash' => $visitorHash,
                ':author_name'  => $authorName,
                ':author_email' => $authorEmail,
                ':content'      => $content,
                ':ip_address'   => $ipAddress,
            ]);
        } catch (PDOException $e) {
            error_log("Error creating reply: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère un commentaire par ID
     * @param int $id
     * @return array|false
     */
    public function getById(int $id): array|false {
        $query = "SELECT c.*, u.username
                  FROM comments c
                  LEFT JOIN users u ON c.user_id = u.id
                  WHERE c.id = :id";

        return $this->db->fetchOne($query, [':id' => $id]);
    }

    /**
     * Met à jour un commentaire
     * @param int $commentId
     * @param string $content
     * @return bool
     */
    public function update(int $commentId, string $content): bool {
        $query = "UPDATE comments SET content = :content WHERE id = :id";
        return $this->db->execute($query, [
            ':id' => $commentId,
            ':content' => $content
        ]) > 0;
    }

    /**
     * Approuve un commentaire
     * @param int $commentId
     * @return bool
     */
    public function approve(int $commentId): bool {
        $query = "UPDATE comments SET is_approved = 1 WHERE id = :id";
        return $this->db->execute($query, [':id' => $commentId]) > 0;
    }

    /**
     * Désapprouve un commentaire
     * @param int $commentId
     * @return bool
     */
    public function disapprove(int $commentId): bool {
        $query = "UPDATE comments SET is_approved = 0 WHERE id = :id";
        return $this->db->execute($query, [':id' => $commentId]) > 0;
    }

    /**
     * Supprime un commentaire
     * @param int $commentId
     * @return bool
     */
    public function delete(int $commentId): bool {
        $query = "DELETE FROM comments WHERE id = :id";
        return $this->db->execute($query, [':id' => $commentId]) > 0;
    }

    /**
     * Compte les commentaires d'une via
     * @param int $viaId
     * @param bool $onlyApproved
     * @return int
     */
    public function countByVia(int $viaId, bool $onlyApproved = true): int {
        $approvedCondition = $onlyApproved ? "AND is_approved = 1" : "";
        $query = "SELECT COUNT(*) as total FROM comments WHERE via_id = :via_id $approvedCondition";
        $result = $this->db->fetchOne($query, [':via_id' => $viaId]);

        return $result ? (int)$result['total'] : 0;
    }

    /**
     * Récupère les derniers commentaires
     * @param int $limit
     * @return array
     */
    public function getRecent(int $limit = 10): array {
        $query = "SELECT c.*, u.username, v.name as via_name, v.slug as via_slug
                  FROM comments c
                  LEFT JOIN users u ON c.user_id = u.id
                  LEFT JOIN vias v ON c.via_id = v.id
                  WHERE c.is_approved = 1
                  ORDER BY c.created_at DESC
                  LIMIT :limit";

        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Récupère les commentaires d'un utilisateur
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getByUser(int $userId, int $limit = 50): array {
        $query = "SELECT c.*, v.name as via_name, v.slug as via_slug
                  FROM comments c
                  LEFT JOIN vias v ON c.via_id = v.id
                  WHERE c.user_id = :user_id
                  ORDER BY c.created_at DESC
                  LIMIT :limit";

        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Récupère tous les commentaires (pour modération)
     * @param bool $onlyApproved
     * @param int $limit
     * @return array
     */
    public function getAll(bool $onlyApproved = true, int $limit = 100): array {
        $approvedCondition = $onlyApproved ? "WHERE c.is_approved = 1" : "";

        $query = "SELECT c.*, u.username, v.name as via_name, v.slug as via_slug
                  FROM comments c
                  LEFT JOIN users u ON c.user_id = u.id
                  LEFT JOIN vias v ON c.via_id = v.id
                  $approvedCondition
                  ORDER BY c.created_at DESC
                  LIMIT :limit";

        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
