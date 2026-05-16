<?php
/**
 * Class ViaFerrata
 * Gestion des via ferrata
 */
class ViaFerrata {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère toutes les via ferrata actives et approuvées
     * @param int $limit
     * @param int $offset
     * @param bool $onlyApproved
     * @return array
     */
    public function getAll(int $limit = 50, int $offset = 0, bool $onlyApproved = true): array {
        $approvedCondition = $onlyApproved ? "AND v.is_approved = 1" : "";

        $query = "SELECT v.*, d.name as department_name, d.code as department_code,
                  vrs.total_ratings, vrs.avg_general, vrs.avg_beauty, vrs.avg_difficulty, vrs.avg_overall
                  FROM vias v
                  LEFT JOIN departments d ON v.department_id = d.code
                  LEFT JOIN via_ratings_summary vrs ON v.id = vrs.via_id
                  WHERE v.is_active = 1 $approvedCondition
                  ORDER BY v.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Récupère une via par ID
     * @param int $id
     * @return array|false
     */
    public function getById(int $id): array|false {
        $query = "SELECT v.*, d.name as department_name, d.code as department_code, d.region,
                  vrs.total_ratings, vrs.avg_general, vrs.avg_beauty, vrs.avg_difficulty, vrs.avg_overall
                  FROM vias v
                  LEFT JOIN departments d ON v.department_id = d.code
                  LEFT JOIN via_ratings_summary vrs ON v.id = vrs.via_id
                  WHERE v.id = :id AND v.is_active = 1";

        return $this->db->fetchOne($query, [':id' => $id]);
    }

    /**
     * Récupère une via par slug
     * @param string $slug
     * @return array|false
     */
    public function getBySlug(string $slug): array|false {
        $query = "SELECT v.*, d.name as department_name, d.code as department_code, d.region,
                  vrs.total_ratings, vrs.avg_general, vrs.avg_beauty, vrs.avg_difficulty, vrs.avg_overall
                  FROM vias v
                  LEFT JOIN departments d ON v.department_id = d.code
                  LEFT JOIN via_ratings_summary vrs ON v.id = vrs.via_id
                  WHERE v.slug = :slug AND v.is_active = 1";

        return $this->db->fetchOne($query, [':slug' => $slug]);
    }

    /**
     * Recherche des via ferrata
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function search(array $filters = [], int $limit = 50, int $offset = 0): array {
        $conditions = ["v.is_active = 1"];
        $params = [];

        // Filtre texte global (nom + localisation + département)
        // Note: PDO ne supporte qu'une occurrence par paramètre nommé → noms distincts
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $conditions[] = "(v.name LIKE :s_name OR v.location LIKE :s_loc OR d.name LIKE :s_dept)";
            $params[':s_name'] = $s;
            $params[':s_loc']  = $s;
            $params[':s_dept'] = $s;
        }

        // Filtre par nom seul (legacy)
        if (!empty($filters['name'])) {
            $conditions[] = "v.name LIKE :name";
            $params[':name'] = '%' . $filters['name'] . '%';
        }

        // Filtre par département
        if (!empty($filters['department_id'])) {
            $conditions[] = "v.department_id = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }

        // Filtre par code département
        if (!empty($filters['department_code'])) {
            $conditions[] = "d.code = :department_code";
            $params[':department_code'] = $filters['department_code'];
        }

        // Filtre par difficulté minimale
        if (isset($filters['difficulty_min'])) {
            $conditions[] = "v.difficulty >= :difficulty_min";
            $params[':difficulty_min'] = $filters['difficulty_min'];
        }

        // Filtre par difficulté maximale
        if (isset($filters['difficulty_max'])) {
            $conditions[] = "v.difficulty <= :difficulty_max";
            $params[':difficulty_max'] = $filters['difficulty_max'];
        }

        // Filtre par note moyenne minimale
        if (isset($filters['rating_min'])) {
            $conditions[] = "vrs.avg_overall >= :rating_min";
            $params[':rating_min'] = $filters['rating_min'];
        }

        $whereClause = implode(' AND ', $conditions);

        // Ordre de tri
        $orderBy = "v.created_at DESC";
        if (!empty($filters['order_by'])) {
            switch ($filters['order_by']) {
                case 'name':
                    $orderBy = "v.name ASC";
                    break;
                case 'rating':
                    $orderBy = "vrs.avg_overall DESC";
                    break;
                case 'difficulty':
                    $orderBy = "v.difficulty DESC";
                    break;
                case 'beauty':
                    $orderBy = "vrs.avg_beauty DESC";
                    break;
            }
        }

        $query = "SELECT v.*, d.name as department_name, d.code as department_code,
                  vrs.total_ratings, vrs.avg_general, vrs.avg_beauty, vrs.avg_difficulty, vrs.avg_overall
                  FROM vias v
                  LEFT JOIN departments d ON v.department_id = d.code
                  LEFT JOIN via_ratings_summary vrs ON v.id = vrs.via_id
                  WHERE $whereClause
                  ORDER BY $orderBy
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->db->getConnection()->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Récupère les meilleures via ferrata
     * @param int $limit
     * @return array
     */
    public function getTopRated(int $limit = 10): array {
        $query = "SELECT v.*, d.name as department_name, d.code as department_code,
                  vrs.total_ratings, vrs.avg_general, vrs.avg_beauty, vrs.avg_difficulty, vrs.avg_overall
                  FROM vias v
                  LEFT JOIN departments d ON v.department_id = d.code
                  LEFT JOIN via_ratings_summary vrs ON v.id = vrs.via_id
                  WHERE v.is_active = 1 AND vrs.total_ratings >= 1
                  ORDER BY vrs.avg_overall DESC, vrs.total_ratings DESC
                  LIMIT :limit";

        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Récupère les via ferrata par département
     * @param int $departmentId
     * @return array
     */
    public function getByDepartment(int $departmentId): array {
        $query = "SELECT v.*, d.name as department_name, d.code as department_code,
                  vrs.total_ratings, vrs.avg_general, vrs.avg_beauty, vrs.avg_difficulty, vrs.avg_overall
                  FROM vias v
                  LEFT JOIN departments d ON v.department_id = d.code
                  LEFT JOIN via_ratings_summary vrs ON v.id = vrs.via_id
                  WHERE v.department_id = :department_id AND v.is_active = 1
                  ORDER BY v.name ASC";

        return $this->db->fetchAll($query, [':department_id' => $departmentId]);
    }

    /**
     * Compte le nombre total de via ferrata
     * @param array $filters
     * @return int
     */
    public function count(array $filters = []): int {
        $conditions = ["v.is_active = 1"];
        $params = [];
        $needsDept = false;

        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $conditions[] = "(v.name LIKE :s_name OR v.location LIKE :s_loc OR d.name LIKE :s_dept)";
            $params[':s_name'] = $s;
            $params[':s_loc']  = $s;
            $params[':s_dept'] = $s;
            $needsDept = true;
        }

        if (!empty($filters['name'])) {
            $conditions[] = "v.name LIKE :name";
            $params[':name'] = '%' . $filters['name'] . '%';
        }

        if (!empty($filters['department_id'])) {
            $conditions[] = "v.department_id = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }

        if (!empty($filters['department_code'])) {
            $conditions[] = "d.code = :department_code";
            $params[':department_code'] = $filters['department_code'];
            $needsDept = true;
        }

        if (isset($filters['difficulty_min'])) {
            $conditions[] = "v.difficulty >= :difficulty_min";
            $params[':difficulty_min'] = $filters['difficulty_min'];
        }

        if (isset($filters['difficulty_max'])) {
            $conditions[] = "v.difficulty <= :difficulty_max";
            $params[':difficulty_max'] = $filters['difficulty_max'];
        }

        $join = $needsDept ? "LEFT JOIN departments d ON v.department_id = d.code" : "";
        $whereClause = implode(' AND ', $conditions);
        $query = "SELECT COUNT(*) as total FROM vias v $join WHERE $whereClause";
        $result = $this->db->fetchOne($query, $params);

        return $result ? (int)$result['total'] : 0;
    }

    /**
     * Crée une nouvelle via ferrata
     * @param array $data
     * @return int|false
     */
    public function create(array $data): int|false {
        $query = "INSERT INTO vias (name, slug, department_id, location, description, image_url,
                  difficulty, difficulty_rating, duration_hours, estimated_duration, length_meters,
                  altitude_min, altitude_max, elevation_gain, length_km, approach_time, return_time,
                  latitude, longitude, google_maps_url, rental_equipment_url, opening_period,
                  tourism_office_name, tourism_office_phone, tourism_office_email,
                  pricing, opening_status, is_approved, submitted_by)
                  VALUES (:name, :slug, :department_id, :location, :description, :image_url,
                  :difficulty, :difficulty_rating, :duration_hours, :estimated_duration, :length_meters,
                  :altitude_min, :altitude_max, :elevation_gain, :length_km, :approach_time, :return_time,
                  :latitude, :longitude, :google_maps_url, :rental_equipment_url, :opening_period,
                  :tourism_office_name, :tourism_office_phone, :tourism_office_email,
                  :pricing, :opening_status, :is_approved, :submitted_by)";

        try {
            return (int) $this->db->insert($query, [
                ':name' => $data['name'],
                ':slug' => $data['slug'],
                ':department_id' => $data['department_id'],
                ':location' => $data['location'] ?? null,
                ':description' => $data['description'] ?? null,
                ':image_url' => $data['image_url'] ?? 'https://viaferrata.delgehier.com/assets/images/via/default.png',
                ':difficulty' => $data['difficulty'] ?? 1,
                ':difficulty_rating' => $data['difficulty_rating'] ?? 5,
                ':duration_hours' => $data['duration_hours'] ?? null,
                ':estimated_duration' => $data['estimated_duration'] ?? null,
                ':length_meters' => $data['length_meters'] ?? null,
                ':altitude_min' => $data['altitude_min'] ?? null,
                ':altitude_max' => $data['altitude_max'] ?? null,
                ':elevation_gain' => $data['elevation_gain'] ?? null,
                ':length_km' => $data['length_km'] ?? null,
                ':approach_time' => $data['approach_time'] ?? null,
                ':return_time' => $data['return_time'] ?? null,
                ':latitude' => $data['latitude'] ?? null,
                ':longitude' => $data['longitude'] ?? null,
                ':google_maps_url' => $data['google_maps_url'] ?? null,
                ':rental_equipment_url' => $data['rental_equipment_url'] ?? null,
                ':opening_period' => $data['opening_period'] ?? null,
                ':tourism_office_name' => $data['tourism_office_name'] ?? null,
                ':tourism_office_phone' => $data['tourism_office_phone'] ?? null,
                ':tourism_office_email' => $data['tourism_office_email'] ?? null,
                ':pricing' => $data['pricing'] ?? 'gratuit',
                ':opening_status' => $data['opening_status'] ?? 'ouvert',
                ':is_approved' => $data['is_approved'] ?? 0,
                ':submitted_by' => $data['submitted_by'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error creating via ferrata: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Génère un slug à partir du nom
     * @param string $name
     * @return string
     */
    public function generateSlug(string $name): string {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Récupère les via ferrata en attente d'approbation
     * @return array
     */
    public function getPending(): array {
        $query = "SELECT v.*, d.name as department_name, d.code as department_code,
                  u.username as submitted_by_username
                  FROM vias v
                  LEFT JOIN departments d ON v.department_id = d.code
                  LEFT JOIN users u ON v.submitted_by = u.id
                  WHERE v.is_active = 1 AND v.is_approved = 0
                  ORDER BY v.created_at DESC";

        return $this->db->fetchAll($query);
    }

    /**
     * Approuve une via ferrata
     * @param int $viaId
     * @param int $approvedBy
     * @return bool
     */
    public function approve(int $viaId, int $approvedBy): bool {
        $query = "UPDATE vias SET is_approved = 1, approved_by = :approved_by, approved_at = NOW()
                  WHERE id = :id";

        return $this->db->execute($query, [
            ':id' => $viaId,
            ':approved_by' => $approvedBy
        ]) > 0;
    }

    /**
     * Rejette une via ferrata (la désactive)
     * @param int $viaId
     * @return bool
     */
    public function reject(int $viaId): bool {
        $query = "UPDATE vias SET is_active = 0 WHERE id = :id";
        return $this->db->execute($query, [':id' => $viaId]) > 0;
    }

    /**
     * Met à jour une via ferrata
     * @param int $viaId
     * @param array $data
     * @return bool
     */
    public function update(int $viaId, array $data): bool {
        $allowedFields = [
            'name', 'slug', 'department_id', 'location', 'description', 'image_url',
            'difficulty', 'difficulty_rating', 'duration_hours', 'estimated_duration',
            'length_meters', 'altitude_min', 'altitude_max', 'elevation_gain', 'length_km',
            'approach_time', 'return_time', 'latitude', 'longitude', 'google_maps_url',
            'description_link', 'rental_equipment_url', 'opening_period', 'tourism_office_name',
            'tourism_office_phone', 'tourism_office_email', 'pricing', 'opening_status'
        ];

        $updates = [];
        $params = [':id' => $viaId];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $query = "UPDATE vias SET " . implode(', ', $updates) . " WHERE id = :id";

        try {
            return $this->db->execute($query, $params) > 0;
        } catch (PDOException $e) {
            error_log("Error updating via ferrata: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime une via ferrata (soft delete)
     * @param int $viaId
     * @return bool
     */
    public function delete(int $viaId): bool {
        $query = "UPDATE vias SET is_active = 0 WHERE id = :id";
        return $this->db->execute($query, [':id' => $viaId]) > 0;
    }
}
