<?php
/**
 * Classe de détection d'appareil (PC, Smartphone, Tablette, Robot)
 * Via Ferrata France
 */

class DeviceDetector {

    private $userAgent;
    private $deviceInfo;

    const COOKIE_NAME = 'vf_stay_desktop';
    const COOKIE_DURATION = 86400; // 24 heures en secondes

    /**
     * Constructeur
     */
    public function __construct() {
        $this->userAgent = isset($_SERVER['HTTP_USER_AGENT'])
            ? strtolower($_SERVER['HTTP_USER_AGENT'])
            : '';
        $this->deviceInfo = $this->detectDevice();
    }

    /**
     * Détecte le type d'appareil
     * @return array
     */
    private function detectDevice(): array {
        $ua = $this->userAgent;

        $details = [
            'type' => 'PC',
            'os' => 'Inconnu',
            'browser' => 'Inconnu',
            'is_robot' => false,
            'raw_ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        // 1. Détection des robots
        $robots = [
            'googlebot', 'bingbot', 'slurp', 'duckduckbot',
            'baiduspider', 'yandex', 'facebookexternalhit',
            'twitterbot', 'linkedinbot', 'whatsapp'
        ];

        foreach ($robots as $bot) {
            if (!empty($bot) && strpos($ua, $bot) !== false) {
                $details['type'] = 'Robot';
                $details['os'] = 'N/A';
                $details['browser'] = 'N/A';
                $details['is_robot'] = true;
                return $details;
            }
        }

        // 2. Détection du système d'exploitation
        if (strpos($ua, 'windows') !== false) {
            $details['os'] = 'Windows';
        } elseif (strpos($ua, 'mac os') !== false || strpos($ua, 'macintosh') !== false) {
            $details['os'] = 'MacOS';
        } elseif (strpos($ua, 'linux') !== false && strpos($ua, 'android') === false) {
            $details['os'] = 'Linux';
        } elseif (strpos($ua, 'iphone') !== false) {
            $details['os'] = 'iOS (iPhone)';
        } elseif (strpos($ua, 'ipad') !== false) {
            $details['os'] = 'iOS (iPad)';
        } elseif (strpos($ua, 'android') !== false) {
            $details['os'] = 'Android';
        }

        // 3. Détection du navigateur
        if (strpos($ua, 'chrome') !== false && strpos($ua, 'edge') === false && strpos($ua, 'opr') === false) {
            $details['browser'] = 'Chrome';
        } elseif (strpos($ua, 'safari') !== false && strpos($ua, 'chrome') === false) {
            $details['browser'] = 'Safari';
        } elseif (strpos($ua, 'firefox') !== false) {
            $details['browser'] = 'Firefox';
        } elseif (strpos($ua, 'edge') !== false || strpos($ua, 'edg/') !== false) {
            $details['browser'] = 'Edge';
        } elseif (strpos($ua, 'opera') !== false || strpos($ua, 'opr') !== false) {
            $details['browser'] = 'Opera';
        }

        // 4. Détection spécifique des tablettes (AVANT les smartphones car iPad contient "mobile")
        $tablets = [
            'ipad', 'tablet', 'tab', 'kindle', 'silk',
            'nexus 7', 'nexus 10', 'sm-t', 'gt-p',
            'mediapad', 'yoga tablet', 'xoom'
        ];

        foreach ($tablets as $t) {
            if (!empty($t) && strpos($ua, $t) !== false) {
                $details['type'] = 'Tablette';
                return $details;
            }
        }

        // 5. Détection des smartphones
        $phones = [
            'iphone', 'android', 'mobile', 'windows phone',
            'blackberry', 'huawei', 'miui', 'samsung', 'sm-',
            'htc', 'nokia', 'pixel', 'oneplus', 'oppo', 'vivo',
            'xiaomi', 'realme', 'redmi'
        ];

        foreach ($phones as $p) {
            if (!empty($p) && strpos($ua, $p) !== false) {
                $details['type'] = 'Smartphone';
                break;
            }
        }

        // 6. Cas spécial Android (affiner)
        if (strpos($ua, 'android') !== false) {
            if (strpos($ua, 'mobile') !== false) {
                $details['type'] = 'Smartphone';
            } elseif ($details['type'] === 'PC') {
                // Si Android sans "mobile" et pas détecté comme tablette, c'est probablement une tablette
                $details['type'] = 'Tablette';
            }
        }

        return $details;
    }

    /**
     * Retourne le type d'appareil
     * @return string PC|Smartphone|Tablette|Robot
     */
    public function getType(): string {
        return $this->deviceInfo['type'];
    }

    /**
     * Retourne toutes les informations
     * @return array
     */
    public function getInfo(): array {
        return $this->deviceInfo;
    }

    /**
     * Vérifie si c'est un appareil mobile (smartphone ou tablette)
     * @return bool
     */
    public function isMobile(): bool {
        return in_array($this->deviceInfo['type'], ['Smartphone', 'Tablette']);
    }

    /**
     * Vérifie si c'est un smartphone
     * @return bool
     */
    public function isSmartphone(): bool {
        return $this->deviceInfo['type'] === 'Smartphone';
    }

    /**
     * Vérifie si c'est une tablette
     * @return bool
     */
    public function isTablet(): bool {
        return $this->deviceInfo['type'] === 'Tablette';
    }

    /**
     * Vérifie si c'est un PC
     * @return bool
     */
    public function isPC(): bool {
        return $this->deviceInfo['type'] === 'PC';
    }

    /**
     * Vérifie si c'est un robot
     * @return bool
     */
    public function isRobot(): bool {
        return $this->deviceInfo['is_robot'];
    }

    /**
     * Vérifie si l'utilisateur a choisi de rester sur la version desktop
     * @return bool
     */
    public function wantsDesktopVersion(): bool {
        return isset($_COOKIE[self::COOKIE_NAME]) && $_COOKIE[self::COOKIE_NAME] === 'true';
    }

    /**
     * Définit le cookie pour rester sur la version desktop
     * @return void
     */
    public function setStayDesktop(): void {
        setcookie(
            self::COOKIE_NAME,
            'true',
            time() + self::COOKIE_DURATION,
            '/',
            '',
            true,  // secure (HTTPS)
            true   // httponly
        );
    }

    /**
     * Vérifie si le popup de redirection mobile doit être affiché
     * @return bool
     */
    public function shouldShowMobileRedirectPopup(): bool {
        // Ne pas afficher si :
        // - C'est un PC ou un robot
        // - L'utilisateur a déjà choisi de rester sur desktop (cookie)
        // - On est déjà sur le domaine mobile

        if (!$this->isMobile()) {
            return false;
        }

        if ($this->isRobot()) {
            return false;
        }

        if ($this->wantsDesktopVersion()) {
            return false;
        }

        // Vérifier qu'on n'est pas déjà sur le site mobile
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($currentHost, 'm.france.viaferrata-monde.fr') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Retourne l'OS de l'appareil
     * @return string
     */
    public function getOS(): string {
        return $this->deviceInfo['os'];
    }

    /**
     * Retourne le navigateur
     * @return string
     */
    public function getBrowser(): string {
        return $this->deviceInfo['browser'];
    }

    /**
     * Retourne le User-Agent complet
     * @return string
     */
    public function getUserAgent(): string {
        return $this->deviceInfo['raw_ua'];
    }
}
