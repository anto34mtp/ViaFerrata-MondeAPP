<?php
/**
 * Class Auth
 * Gestion de l'authentification et des sessions
 */
class Auth {

    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
        $this->startSession();
    }

    /**
     * Démarre la session si elle n'est pas démarrée
     */
    private function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Connexion utilisateur
     * @param string $login (username ou email)
     * @param string $password
     * @return bool
     */
    public function login(string $login, string $password): bool {
        // Déterminer si c'est un email ou username
        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? $this->userModel->getByEmail($login)
            : $this->userModel->getByUsername($login);

        if (!$user) {
            return false;
        }

        // Vérifier si le compte est actif
        if (!$user['is_active']) {
            return false;
        }

        // Vérifier le mot de passe
        if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
            return false;
        }

        // Régénérer l'ID de session pour éviter le session fixation
        session_regenerate_id(true);

        // Stocker les informations en session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'] ?? 'member';
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        // Mettre à jour la dernière connexion
        $this->userModel->updateLastLogin($user['id']);

        return true;
    }

    /**
     * Inscription utilisateur
     * @param string $username
     * @param string $email
     * @param string $password
     * @return int|false
     */
    public function register(string $username, string $email, string $password): int|false {
        return $this->userModel->create($username, $email, $password);
    }

    /**
     * Déconnexion utilisateur
     */
    public function logout(): void {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Vérifie si l'utilisateur est connecté
     * @return bool
     */
    public function isLoggedIn(): bool {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Récupère l'ID de l'utilisateur connecté
     * @return int|null
     */
    public function getUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Récupère le username de l'utilisateur connecté
     * @return string|null
     */
    public function getUsername(): ?string {
        return $_SESSION['username'] ?? null;
    }

    /**
     * Récupère l'email de l'utilisateur connecté
     * @return string|null
     */
    public function getUserEmail(): ?string {
        return $_SESSION['email'] ?? null;
    }

    /**
     * Récupère toutes les données de l'utilisateur connecté
     * @return array|null
     */
    public function getUser(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'login_time' => $_SESSION['login_time']
        ];
    }

    /**
     * Génère un token CSRF
     * @return string
     */
    public function generateCsrfToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifie un token CSRF
     * @param string $token
     * @return bool
     */
    public function verifyCsrfToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Nécessite une authentification (pour protéger des pages)
     * @param string $redirectUrl URL de redirection si non connecté
     */
    public function requireAuth(string $redirectUrl = '/pages/login.php'): void {
        if (!$this->isLoggedIn()) {
            header("Location: $redirectUrl");
            exit;
        }
    }

    /**
     * Empêche l'accès aux utilisateurs connectés (ex: pages login/register)
     * @param string $redirectUrl
     */
    public function requireGuest(string $redirectUrl = '/pages/dashboard.php'): void {
        if ($this->isLoggedIn()) {
            header("Location: $redirectUrl");
            exit;
        }
    }

    /**
     * Récupère le rôle de l'utilisateur connecté
     * @return string|null
     */
    public function getUserRole(): ?string {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Vérifie si l'utilisateur est admin
     * @return bool
     */
    public function isAdmin(): bool {
        return $this->isLoggedIn() && $this->getUserRole() === 'admin';
    }

    /**
     * Vérifie si l'utilisateur est modérateur ou admin
     * @return bool
     */
    public function isModerator(): bool {
        return $this->isLoggedIn() && in_array($this->getUserRole(), ['modo', 'admin']);
    }

    /**
     * Nécessite le rôle admin
     * @param string $redirectUrl
     */
    public function requireAdmin(string $redirectUrl = '/'): void {
        if (!$this->isAdmin()) {
            header("Location: $redirectUrl");
            exit;
        }
    }

    /**
     * Nécessite le rôle modérateur ou admin
     * @param string $redirectUrl
     */
    public function requireModerator(string $redirectUrl = '/'): void {
        if (!$this->isModerator()) {
            header("Location: $redirectUrl");
            exit;
        }
    }
}
