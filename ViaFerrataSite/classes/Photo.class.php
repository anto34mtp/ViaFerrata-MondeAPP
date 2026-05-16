<?php
/**
 * Class Photo
 * Gestion des photos uploadées par les utilisateurs
 */
class Photo {

    private Database $db;
    private string $uploadDir;
    private array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif', 'image/jpg', 'image/pjpeg'];
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
    private int $maxFileSize = 20971520; // 20 MB

    public function __construct() {
        $this->db = Database::getInstance();
        $this->uploadDir = __DIR__ . '/../uploads/photos/';

        // Créer le répertoire d'upload s'il n'existe pas
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Upload une photo
     * @return int|string ID de la photo en cas de succès, ou string code d'erreur
     */
    public function upload(int $viaId, array $file, ?int $userId, string $authorName, string $visitorHash, ?string $ipAddress = null): int|string {
        // Validation du fichier
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            error_log("Photo upload error: Invalid file upload");
            return false;
        }

        // Vérifier les erreurs d'upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log("Photo upload error: Upload error code " . $file['error']);
            return false;
        }

        // Vérifier la taille
        if ($file['size'] > $this->maxFileSize) {
            error_log("Photo upload error: File too large (" . $file['size'] . " bytes)");
            return 'err_size';
        }

        // Vérifier l'extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            error_log("Photo upload error: Invalid extension (" . $extension . ")");
            return 'err_ext:' . $extension;
        }

        // Vérifier le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            error_log("Photo upload error: Invalid MIME type (" . $mimeType . ")");
            return 'err_mime:' . $mimeType;
        }

        // Vérifier que le répertoire existe et est accessible en écriture
        if (!is_dir($this->uploadDir)) {
            error_log("Photo upload error: Upload directory does not exist: " . $this->uploadDir);
            return 'err_nodir:' . $this->uploadDir;
        }

        if (!is_writable($this->uploadDir)) {
            error_log("Photo upload error: Upload directory is not writable: " . $this->uploadDir);
            return 'err_nowrite:' . $this->uploadDir;
        }

        // Générer un nom de fichier unique
        $filename = uniqid('photo_', true) . '_' . time() . '.' . $extension;
        $filePath = $this->uploadDir . $filename;

        // Déplacer le fichier uploadé
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            error_log("Photo upload error: Failed to move uploaded file to " . $filePath);
            return 'err_move:' . $filePath;
        }

        error_log("Photo upload success: File moved to " . $filePath);

        // Insérer dans la base de données
        $query = "INSERT INTO user_photos (via_id, user_id, visitor_hash, author_name, filename, original_filename, file_path, file_size, mime_type, ip_address)
                  VALUES (:via_id, :user_id, :visitor_hash, :author_name, :filename, :original_filename, :file_path, :file_size, :mime_type, :ip_address)";

        $params = [
            ':via_id' => $viaId,
            ':user_id' => $userId,
            ':visitor_hash' => $visitorHash,
            ':author_name' => $authorName,
            ':filename' => $filename,
            ':original_filename' => $file['name'],
            ':file_path' => 'uploads/photos/' . $filename,
            ':file_size' => $file['size'],
            ':mime_type' => $mimeType,
            ':ip_address' => $ipAddress
        ];

        try {
            return (int) $this->db->insert($query, $params);
        } catch (PDOException $e) {
            error_log("Error uploading photo: " . $e->getMessage());
            unlink($filePath);
            return 'err_db:' . $e->getMessage();
        }
    }

    /**
     * Récupère les photos approuvées d'une via ferrata
     * @param int $viaId
     * @return array
     */
    public function getApprovedByVia(int $viaId): array {
        $query = "SELECT * FROM user_photos
                  WHERE via_id = :via_id AND is_approved = 1
                  ORDER BY created_at DESC";
        return $this->db->fetchAll($query, [':via_id' => $viaId]);
    }

    /**
     * Récupère toutes les photos d'une via (pour admin)
     * @param int $viaId
     * @return array
     */
    public function getAllByVia(int $viaId): array {
        $query = "SELECT p.*, u.username as approved_by_username
                  FROM user_photos p
                  LEFT JOIN users u ON p.approved_by = u.id
                  WHERE p.via_id = :via_id
                  ORDER BY p.created_at DESC";
        return $this->db->fetchAll($query, [':via_id' => $viaId]);
    }

    /**
     * Récupère les photos en attente de validation
     * @return array
     */
    public function getPending(): array {
        $query = "SELECT p.*, v.name as via_name, v.slug as via_slug
                  FROM user_photos p
                  INNER JOIN vias v ON p.via_id = v.id
                  WHERE p.is_approved = 0
                  ORDER BY p.created_at ASC";
        return $this->db->fetchAll($query);
    }

    /**
     * Récupère le nombre de photos en attente
     * @return int
     */
    public function getPendingCount(): int {
        $query = "SELECT COUNT(*) as count FROM user_photos WHERE is_approved = 0";
        $result = $this->db->fetchOne($query);
        return $result ? (int)$result['count'] : 0;
    }

    /**
     * Approuve une photo
     * @param int $photoId
     * @param int $approvedBy
     * @return bool
     */
    public function approve(int $photoId, int $approvedBy): bool {
        $query = "UPDATE user_photos
                  SET is_approved = 1, approved_by = :approved_by, approved_at = NOW()
                  WHERE id = :id";
        return $this->db->execute($query, [
            ':id' => $photoId,
            ':approved_by' => $approvedBy
        ]) > 0;
    }

    /**
     * Rejette une photo
     * @param int $photoId
     * @param int $rejectedBy
     * @param string|null $reason
     * @return bool
     */
    public function reject(int $photoId, int $rejectedBy, ?string $reason = null): bool {
        $query = "UPDATE user_photos
                  SET is_approved = 2, approved_by = :approved_by, approved_at = NOW(), rejection_reason = :reason
                  WHERE id = :id";
        return $this->db->execute($query, [
            ':id' => $photoId,
            ':approved_by' => $rejectedBy,
            ':reason' => $reason
        ]) > 0;
    }

    /**
     * Supprime une photo (physiquement et en BDD)
     * @param int $photoId
     * @return bool
     */
    public function delete(int $photoId): bool {
        // Récupérer le chemin du fichier
        $query = "SELECT filename FROM user_photos WHERE id = :id";
        $photo = $this->db->fetchOne($query, [':id' => $photoId]);

        if (!$photo) {
            return false;
        }

        // Supprimer le fichier physique
        $filePath = $this->uploadDir . $photo['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Supprimer de la BDD
        $query = "DELETE FROM user_photos WHERE id = :id";
        return $this->db->execute($query, [':id' => $photoId]) > 0;
    }

    /**
     * Récupère une photo par ID
     * @param int $photoId
     * @return array|false
     */
    public function getById(int $photoId): array|false {
        $query = "SELECT * FROM user_photos WHERE id = :id";
        return $this->db->fetchOne($query, [':id' => $photoId]);
    }

    /**
     * Vérifie si un utilisateur peut uploader une photo pour cette via
     * @param int $viaId
     * @param string $visitorHash
     * @return bool
     */
    public function canUpload(int $viaId, string $visitorHash): bool {
        // Limiter à 3 photos par via et par utilisateur (en attente ou approuvées)
        $query = "SELECT COUNT(*) as count FROM user_photos
                  WHERE via_id = :via_id AND visitor_hash = :visitor_hash AND is_approved != 2";
        $result = $this->db->fetchOne($query, [
            ':via_id' => $viaId,
            ':visitor_hash' => $visitorHash
        ]);

        return $result && $result['count'] < 3;
    }
}
