<?php
/**
 * Class ViaSubmission
 * Gestion des propositions de nouvelles Via Ferrata (mono et multi-parties)
 */
class ViaSubmission {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crée une nouvelle proposition de via (une partie)
     * @param array $data
     * @return int|false  ID de la soumission ou false
     */
    public function create(array $data): int|false {
        $query = "INSERT INTO via_submissions (
                    user_id, ip_address, name, part_number, total_parts, group_token,
                    location, latitude, longitude,
                    difficulty, duration_hours, approach_time, return_time,
                    elevation_gain, description, author_email, status
                  ) VALUES (
                    :user_id, :ip_address, :name, :part_number, :total_parts, :group_token,
                    :location, :latitude, :longitude,
                    :difficulty, :duration_hours, :approach_time, :return_time,
                    :elevation_gain, :description, :author_email, 'pending'
                  )";

        $params = [
            ':user_id'        => $data['user_id'] ?? null,
            ':ip_address'     => $_SERVER['REMOTE_ADDR'] ?? null,
            ':name'           => $data['name'],
            ':part_number'    => $data['part_number'] ?? null,
            ':total_parts'    => $data['total_parts'] ?? null,
            ':group_token'    => $data['group_token'] ?? null,
            ':location'       => $data['location'],
            ':latitude'       => $data['latitude'] ?: null,
            ':longitude'      => $data['longitude'] ?: null,
            ':difficulty'     => $data['difficulty'] ?: null,
            ':duration_hours' => $data['duration_hours'] ?: null,
            ':approach_time'  => $data['approach_time'] ?: null,
            ':return_time'    => $data['return_time'] ?: null,
            ':elevation_gain' => $data['elevation_gain'] ?: null,
            ':description'    => $data['description'],
            ':author_email'   => $data['author_email'] ?? null,
        ];

        try {
            return (int) $this->db->insert($query, $params);
        } catch (PDOException $e) {
            error_log("Error creating via submission: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crée plusieurs parties d'un même groupe en une seule transaction
     * @param array  $shared  Champs communs (location, latitude, longitude, author_email, user_id)
     * @param array  $parts   Tableau de parties, chacune avec name, difficulty, etc.
     * @return bool
     */
    public function createGroup(array $shared, array $parts): bool {
        $token      = bin2hex(random_bytes(8));
        $total      = count($parts);
        $allOk      = true;

        foreach ($parts as $i => $part) {
            $data = array_merge($shared, $part, [
                'part_number' => $i + 1,
                'total_parts' => $total,
                'group_token' => $token,
            ]);
            if (!$this->create($data)) {
                $allOk = false;
            }
        }
        return $allOk;
    }

    /**
     * Récupère toutes les soumissions en attente
     */
    public function getPending(): array {
        $query = "SELECT * FROM via_submissions WHERE status = 'pending' ORDER BY group_token, part_number, created_at DESC";
        return $this->db->fetchAll($query);
    }

    /**
     * Met à jour le statut d'une soumission
     */
    public function updateStatus(int $id, string $status): bool {
        $query = "UPDATE via_submissions SET status = :status WHERE id = :id";
        return $this->db->execute($query, [':id' => $id, ':status' => $status]) > 0;
    }

    /**
     * Récupère une soumission par ID
     */
    public function getById(int $id): array|false {
        $query = "SELECT * FROM via_submissions WHERE id = :id";
        return $this->db->fetchOne($query, [':id' => $id]);
    }
}
