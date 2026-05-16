<?php
/**
 * Class Department
 * Gestion des départements français
 */
class Department {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère tous les départements
     * @return array
     */
    public function getAll(): array {
        $query = "SELECT d.*, dvc.via_count
                  FROM departments d
                  LEFT JOIN department_via_count dvc ON d.id = dvc.department_id
                  ORDER BY d.code ASC";

        return $this->db->fetchAll($query);
    }

    /**
     * Récupère un département par ID
     * @param int $id
     * @return array|false
     */
    public function getById(int $id): array|false {
        $query = "SELECT d.*, dvc.via_count
                  FROM departments d
                  LEFT JOIN department_via_count dvc ON d.id = dvc.department_id
                  WHERE d.id = :id";

        return $this->db->fetchOne($query, [':id' => $id]);
    }

    /**
     * Récupère un département par code
     * @param string $code
     * @return array|false
     */
    public function getByCode(string $code): array|false {
        $query = "SELECT d.*, dvc.via_count
                  FROM departments d
                  LEFT JOIN department_via_count dvc ON d.id = dvc.department_id
                  WHERE d.code = :code";

        return $this->db->fetchOne($query, [':code' => $code]);
    }

    /**
     * Récupère tous les départements avec au moins une via ferrata
     * @return array
     */
    public function getWithVias(): array {
        $query = "SELECT d.*, dvc.via_count
                  FROM departments d
                  INNER JOIN department_via_count dvc ON d.id = dvc.department_id
                  WHERE dvc.via_count > 0
                  ORDER BY dvc.via_count DESC";

        return $this->db->fetchAll($query);
    }

    /**
     * Compte le nombre de via ferrata par département
     * @param int $departmentId
     * @return int
     */
    public function countVias(int $departmentId): int {
        $query = "SELECT COUNT(*) as total FROM vias WHERE department_id = :department_id AND is_active = 1";
        $result = $this->db->fetchOne($query, [':department_id' => $departmentId]);

        return $result ? (int)$result['total'] : 0;
    }

    /**
     * Crée un département
     * @param string $code
     * @param string $name
     * @param string|null $region
     * @return int|false
     */
    public function create(string $code, string $name, ?string $region = null): int|false {
        $query = "INSERT INTO departments (code, name, region) VALUES (:code, :name, :region)";

        try {
            return (int) $this->db->insert($query, [
                ':code' => $code,
                ':name' => $name,
                ':region' => $region
            ]);
        } catch (PDOException $e) {
            error_log("Error creating department: " . $e->getMessage());
            return false;
        }
    }
}
