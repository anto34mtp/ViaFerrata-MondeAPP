<?php
class JWT {
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function generate(int $userId, string $username, string $email, string $role): string {
        $header  = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode([
            'sub'      => $userId,
            'username' => $username,
            'email'    => $email,
            'role'     => $role,
            'iat'      => time(),
            'exp'      => time() + 86400 * 30,
        ]));
        $sig = self::base64UrlEncode(hash_hmac('sha256', "$header.$payload", SECRET_KEY, true));
        return "$header.$payload.$sig";
    }

    public static function verify(string $token): array|false {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        [$header, $payload, $sig] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', "$header.$payload", SECRET_KEY, true));
        if (!hash_equals($expected, $sig)) return false;
        $data = json_decode(self::base64UrlDecode($payload), true);
        if (!$data || empty($data['exp']) || $data['exp'] < time()) return false;
        return $data;
    }

    public static function fromRequest(): array|false {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return false;
        return self::verify($m[1]);
    }
}
