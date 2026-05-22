<?php
/**
 * Class Logbook
 * Carnet de bord personnel de l'utilisateur
 */
class Logbook {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crée ou met à jour une entrée de carnet
     */
    public function save(int $userId, int $viaId, string $doneDate = '', string $conditions = '', string $companion = '', string $notes = ''): bool {
        $existing = $this->getEntry($userId, $viaId);

        if ($existing) {
            $query = "UPDATE logbook_entries
                      SET done_date = :done_date, conditions = :conditions,
                          companion = :companion, notes = :notes, updated_at = NOW()
                      WHERE user_id = :user_id AND via_id = :via_id";
        } else {
            $query = "INSERT INTO logbook_entries (user_id, via_id, done_date, conditions, companion, notes)
                      VALUES (:user_id, :via_id, :done_date, :conditions, :companion, :notes)";
        }

        try {
            return $this->db->execute($query, [
                ':user_id'    => $userId,
                ':via_id'     => $viaId,
                ':done_date'  => $doneDate ?: null,
                ':conditions' => $conditions,
                ':companion'  => $companion,
                ':notes'      => $notes,
            ]) > 0;
        } catch (PDOException $e) {
            error_log("Logbook::save error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère une entrée spécifique (user + via)
     */
    public function getEntry(int $userId, int $viaId): array|false {
        try {
            return $this->db->fetchOne(
                "SELECT * FROM logbook_entries WHERE user_id = :u AND via_id = :v",
                [':u' => $userId, ':v' => $viaId]
            );
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Récupère une entrée par son ID
     */
    public function getById(int $entryId, int $userId): array|false {
        try {
            return $this->db->fetchOne(
                "SELECT * FROM logbook_entries WHERE id = :id AND user_id = :u",
                [':id' => $entryId, ':u' => $userId]
            );
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Récupère toutes les entrées d'un utilisateur, avec les infos via
     */
    public function getByUser(int $userId): array {
        $query = "SELECT le.*,
                         v.name     AS via_name,
                         v.slug     AS via_slug,
                         v.image_url,
                         v.difficulty,
                         v.location,
                         d.name     AS department_name,
                         d.code     AS department_code
                  FROM logbook_entries le
                  LEFT JOIN vias v ON le.via_id = v.id
                  LEFT JOIN departments d ON v.department_id = d.code
                  WHERE le.user_id = :user_id
                  ORDER BY le.done_date DESC, le.updated_at DESC";
        try {
            return $this->db->fetchAll($query, [':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Logbook::getByUser - table may not exist: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Supprime une entrée (vérifie l'appartenance)
     */
    public function delete(int $userId, int $entryId): bool {
        try {
            return $this->db->execute(
                "DELETE FROM logbook_entries WHERE id = :id AND user_id = :u",
                [':id' => $entryId, ':u' => $userId]
            ) > 0;
        } catch (PDOException $e) {
            error_log("Logbook::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Compte les entrées de l'utilisateur
     */
    public function countByUser(int $userId): int {
        try {
            $result = $this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM logbook_entries WHERE user_id = :u",
                [':u' => $userId]
            );
            return $result ? (int)$result['total'] : 0;
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Compte les entrées de l'année en cours
     */
    public function countThisYear(int $userId): int {
        try {
            $result = $this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM logbook_entries
                 WHERE user_id = :u AND YEAR(done_date) = :y",
                [':u' => $userId, ':y' => (int)date('Y')]
            );
            return $result ? (int)$result['total'] : 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
}
