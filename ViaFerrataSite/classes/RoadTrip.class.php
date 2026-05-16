<?php
class RoadTrip {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ── Ownership ──────────────────────────────────────────────────────────

    public function owns(int $tripId, int $userId): bool {
        $row = $this->db->fetchOne(
            'SELECT id FROM road_trips WHERE id = :id AND user_id = :uid',
            [':id' => $tripId, ':uid' => $userId]
        );
        return !empty($row);
    }

    // ── Queries ────────────────────────────────────────────────────────────

    public function getByUser(int $userId): array {
        $trips = $this->db->fetchAll(
            'SELECT rt.*,
                    COUNT(DISTINCT rtv.id) AS via_count
             FROM road_trips rt
             LEFT JOIN road_trip_vias rtv ON rtv.trip_id = rt.id
             WHERE rt.user_id = :uid
             GROUP BY rt.id
             ORDER BY rt.created_at DESC',
            [':uid' => $userId]
        );
        return $trips ?: [];
    }

    public function getById(int $tripId): array|false {
        return $this->db->fetchOne(
            'SELECT rt.*, u.username AS owner_name
             FROM road_trips rt
             JOIN users u ON u.id = rt.user_id
             WHERE rt.id = :id',
            [':id' => $tripId]
        );
    }

    /** Returns vias for the trip grouped by day_number. */
    public function getViasByDay(int $tripId): array {
        $rows = $this->db->fetchAll(
            'SELECT rtv.*, rtv.via_id,
                    v.name, v.slug, v.image_url, v.location,
                    v.difficulty, v.duration_hours, v.length_km,
                    v.latitude, v.longitude, v.opening_status
             FROM road_trip_vias rtv
             JOIN vias v ON v.id = rtv.via_id
             WHERE rtv.trip_id = :tid
             ORDER BY rtv.day_number, rtv.position',
            [':tid' => $tripId]
        );
        $byDay = [];
        foreach ($rows ?: [] as $row) {
            $byDay[(int)$row['day_number']][] = $row;
        }
        return $byDay;
    }

    // ── Mutations ──────────────────────────────────────────────────────────

    public function create(int $userId, string $name, ?string $desc, ?string $startDate, ?string $endDate, int $nbDays): int|false {
        $lastId = $this->db->insert(
            'INSERT INTO road_trips (user_id, name, description, start_date, end_date, nb_days)
             VALUES (:uid, :name, :desc, :start, :end, :days)',
            [
                ':uid'   => $userId,
                ':name'  => mb_substr($name, 0, 255),
                ':desc'  => $desc ?: null,
                ':start' => $startDate ?: null,
                ':end'   => $endDate ?: null,
                ':days'  => max(1, min(30, $nbDays)),
            ]
        );
        return $lastId ? (int)$lastId : false;
    }

    public function update(int $tripId, int $userId, array $data): bool {
        if (!$this->owns($tripId, $userId)) return false;
        $allowed = ['name','description','start_date','end_date','nb_days'];
        $set = array_intersect_key($data, array_flip($allowed));
        if (empty($set)) return false;
        return $this->db->execute(
            'UPDATE road_trips SET ' . implode(', ', array_map(fn($k) => "$k = :$k", array_keys($set))) .
            ' WHERE id = :id',
            array_merge(array_combine(array_map(fn($k) => ":$k", array_keys($set)), array_values($set)), [':id' => $tripId])
        );
    }

    public function delete(int $tripId, int $userId): bool {
        if (!$this->owns($tripId, $userId)) return false;
        $this->db->execute('DELETE FROM road_trip_vias WHERE trip_id = :id', [':id' => $tripId]);
        return $this->db->execute('DELETE FROM road_trips WHERE id = :id AND user_id = :uid', [':id' => $tripId, ':uid' => $userId]);
    }

    // ── Via management ─────────────────────────────────────────────────────

    public function addVia(int $tripId, int $viaId, int $dayNumber, ?string $notes = null): bool {
        $maxPos = $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), -1) AS m FROM road_trip_vias WHERE trip_id = :tid AND day_number = :day',
            [':tid' => $tripId, ':day' => $dayNumber]
        );
        $pos = (int)($maxPos['m'] ?? -1) + 1;
        try {
            return $this->db->execute(
                'INSERT IGNORE INTO road_trip_vias (trip_id, via_id, day_number, position, notes)
                 VALUES (:tid, :vid, :day, :pos, :notes)',
                [':tid' => $tripId, ':vid' => $viaId, ':day' => $dayNumber, ':pos' => $pos, ':notes' => $notes]
            );
        } catch (Exception $e) { return false; }
    }

    public function removeVia(int $tripId, int $viaId): bool {
        return $this->db->execute(
            'DELETE FROM road_trip_vias WHERE trip_id = :tid AND via_id = :vid',
            [':tid' => $tripId, ':vid' => $viaId]
        );
    }

    public function moveViaToDay(int $tripId, int $viaId, int $newDay): bool {
        $maxPos = $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), -1) AS m FROM road_trip_vias WHERE trip_id = :tid AND day_number = :day',
            [':tid' => $tripId, ':day' => $newDay]
        );
        $pos = (int)($maxPos['m'] ?? -1) + 1;
        return $this->db->execute(
            'UPDATE road_trip_vias SET day_number = :day, position = :pos WHERE trip_id = :tid AND via_id = :vid',
            [':day' => $newDay, ':pos' => $pos, ':tid' => $tripId, ':vid' => $viaId]
        );
    }

    /** Re-order vias within a day. $viaIds is an ordered list of via IDs. */
    public function reorderDay(int $tripId, int $dayNumber, array $viaIds): bool {
        $ok = true;
        foreach ($viaIds as $pos => $viaId) {
            $ok = $this->db->execute(
                'UPDATE road_trip_vias SET position = :pos WHERE trip_id = :tid AND via_id = :vid AND day_number = :day',
                [':pos' => (int)$pos, ':tid' => $tripId, ':vid' => (int)$viaId, ':day' => $dayNumber]
            ) && $ok;
        }
        return $ok;
    }

    // ── Sharing ────────────────────────────────────────────────────────────

    public function canView(int $tripId, int $userId): bool {
        if ($this->owns($tripId, $userId)) return true;
        $row = $this->db->fetchOne(
            'SELECT id FROM road_trip_shares WHERE trip_id = :tid AND shared_with = :uid',
            [':tid' => $tripId, ':uid' => $userId]
        );
        return !empty($row);
    }

    public function shareWithUser(int $tripId, int $sharedBy, int $sharedWith): bool {
        if ($sharedBy === $sharedWith) return false;
        if (!$this->owns($tripId, $sharedBy)) return false;
        try {
            return $this->db->execute(
                'INSERT IGNORE INTO road_trip_shares (trip_id, shared_by, shared_with, accepted_at)
                 VALUES (:tid, :by, :with, NOW())',
                [':tid' => $tripId, ':by' => $sharedBy, ':with' => $sharedWith]
            );
        } catch (\Exception $e) { return false; }
    }

    public function shareByEmail(int $tripId, int $sharedBy, string $email): array {
        if (!$this->owns($tripId, $sharedBy)) return ['ok' => false, 'msg' => 'access'];
        $user = $this->db->fetchOne(
            'SELECT id, username FROM users WHERE email = :email AND is_active = 1',
            [':email' => $email]
        );
        if ($user) {
            $uid = (int)$user['id'];
            if ($uid === $sharedBy) return ['ok' => false, 'msg' => 'self'];
            $existing = $this->db->fetchOne(
                'SELECT id FROM road_trip_shares WHERE trip_id = :tid AND shared_with = :uid',
                [':tid' => $tripId, ':uid' => $uid]
            );
            if ($existing) return ['ok' => true, 'type' => 'already', 'username' => $user['username']];
            $ok = $this->db->execute(
                'INSERT INTO road_trip_shares (trip_id, shared_by, shared_with, accepted_at) VALUES (:tid, :by, :with, NOW())',
                [':tid' => $tripId, ':by' => $sharedBy, ':with' => $uid]
            );
            return ['ok' => $ok, 'type' => 'direct', 'username' => $user['username']];
        } else {
            $existing = $this->db->fetchOne(
                'SELECT id FROM road_trip_shares WHERE trip_id = :tid AND invite_email = :email',
                [':tid' => $tripId, ':email' => $email]
            );
            if ($existing) return ['ok' => false, 'msg' => 'already_invited'];
            $token = bin2hex(random_bytes(32));
            $ok = $this->db->execute(
                'INSERT INTO road_trip_shares (trip_id, shared_by, invite_email, invite_token) VALUES (:tid, :by, :email, :token)',
                [':tid' => $tripId, ':by' => $sharedBy, ':email' => $email, ':token' => $token]
            );
            return $ok ? ['ok' => true, 'type' => 'invite', 'token' => $token, 'email' => $email]
                       : ['ok' => false, 'msg' => 'db_error'];
        }
    }

    public function getShares(int $tripId, int $userId): array {
        if (!$this->owns($tripId, $userId)) return [];
        return $this->db->fetchAll(
            'SELECT rts.*, u.username
             FROM road_trip_shares rts
             LEFT JOIN users u ON u.id = rts.shared_with
             WHERE rts.trip_id = :tid
             ORDER BY rts.created_at',
            [':tid' => $tripId]
        ) ?: [];
    }

    public function removeShare(int $tripId, int $sharedBy, int $shareId): bool {
        return $this->db->execute(
            'DELETE FROM road_trip_shares WHERE id = :id AND trip_id = :tid AND shared_by = :by',
            [':id' => $shareId, ':tid' => $tripId, ':by' => $sharedBy]
        );
    }

    public function getSharedTrips(int $userId): array {
        return $this->db->fetchAll(
            'SELECT rt.*, rts.shared_by, rts.id AS share_id,
                    u.username AS owner_name,
                    COUNT(DISTINCT rtv.id) AS via_count
             FROM road_trip_shares rts
             JOIN road_trips rt ON rt.id = rts.trip_id
             JOIN users u ON u.id = rts.shared_by
             LEFT JOIN road_trip_vias rtv ON rtv.trip_id = rt.id
             WHERE rts.shared_with = :uid
             GROUP BY rt.id, rts.shared_by, u.username, rts.id',
            [':uid' => $userId]
        ) ?: [];
    }

    public function findShareByToken(string $token): array|false {
        return $this->db->fetchOne(
            'SELECT rts.*, rt.name AS trip_name, rt.nb_days,
                    u.username AS owner_name
             FROM road_trip_shares rts
             JOIN road_trips rt ON rt.id = rts.trip_id
             JOIN users u ON u.id = rts.shared_by
             WHERE rts.invite_token = :token',
            [':token' => $token]
        );
    }

    public function consumeInvite(string $token, int $userId): bool {
        return $this->db->execute(
            'UPDATE road_trip_shares SET shared_with = :uid, accepted_at = NOW(), invite_token = NULL
             WHERE invite_token = :token',
            [':uid' => $userId, ':token' => $token]
        );
    }

    public function searchUsersToShare(int $tripId, int $userId, string $q): array {
        if (strlen($q) < 2) return [];
        $shares    = $this->getShares($tripId, $userId);
        $excludeIds = [$userId];
        foreach ($shares as $s) {
            if (!empty($s['shared_with'])) $excludeIds[] = (int)$s['shared_with'];
        }
        $notIn = implode(',', $excludeIds);
        return $this->db->fetchAll(
            "SELECT id, username FROM users WHERE username LIKE :q AND is_active = 1 AND id NOT IN ($notIn) ORDER BY username LIMIT 10",
            [':q' => "%{$q}%"]
        ) ?: [];
    }
}
