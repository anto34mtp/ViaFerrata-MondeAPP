<?php
class Lang {
    private static string $current = 'fr';
    private static array  $strings  = [];
    private static array  $available = ['fr', 'en', 'de', 'es'];

    public static function init(): void {
        $lang = null;
        if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], self::$available)) {
            $lang = $_SESSION['lang'];
        } elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], self::$available)) {
            $lang = $_COOKIE['lang'];
        }
        if ($lang) {
            self::$current = $lang;
        }
        self::load();
    }

    public static function set(string $lang): void {
        if (!in_array($lang, self::$available)) $lang = 'fr';
        self::$current          = $lang;
        $_SESSION['lang']       = $lang;
        setcookie('lang', $lang, time() + 365 * 24 * 3600, '/', '', false, false);
        self::load();
    }

    private static function load(): void {
        $file = ROOT_PATH . '/lang/' . self::$current . '.php';
        if (file_exists($file)) {
            self::$strings = require $file;
        }
    }

    public static function get(): string { return self::$current; }

    public static function t(string $key, array $params = []): string {
        $str = self::$strings[$key] ?? $key;
        foreach ($params as $k => $v) {
            $str = str_replace('{' . $k . '}', (string)$v, $str);
        }
        return $str;
    }

    public static function getAvailable(): array { return self::$available; }

    public static function getNativeName(string $lang): string {
        return match($lang) {
            'fr' => 'Français',
            'en' => 'English',
            'de' => 'Deutsch',
            'es' => 'Español',
            default => $lang,
        };
    }

    public static function getFlag(string $lang): string {
        return match($lang) {
            'fr' => '🇫🇷',
            'en' => '🇬🇧',
            'de' => '🇩🇪',
            'es' => '🇪🇸',
            default => '',
        };
    }
}
