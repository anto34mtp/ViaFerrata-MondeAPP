<?php
class Translator {
    private const DEEPL_FREE_URL  = 'https://api-free.deepl.com/v2/translate';
    private const MYMEMORY_URL    = 'https://api.mymemory.translated.net/get';

    // ── Public API ─────────────────────────────────────────────────────────

    /**
     * Translate text from French into the target language.
     * Falls back to MyMemory if DeepL quota is exceeded or key absent.
     */
    public static function translate(string $text, string $targetLang): string {
        if (empty(trim($text)) || $targetLang === 'fr') return $text;
        $deepLResult = self::deepL($text, strtoupper($targetLang), 'FR');
        if ($deepLResult !== null) return $deepLResult;
        return self::myMemory($text, 'fr', $targetLang) ?? $text;
    }

    /**
     * Return $via with name/description translated, using DB cache.
     */
    public static function getViaTranslation(array $via, string $lang): array {
        if ($lang === 'fr' || empty($via['id'])) return $via;

        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT name, description FROM via_translations WHERE via_id = :id AND lang = :lang',
            [':id' => (int)$via['id'], ':lang' => $lang]
        );
        if ($row) {
            if (!empty($row['name']))        $via['name']        = $row['name'];
            if (!empty($row['description'])) $via['description'] = $row['description'];
            return $via;
        }

        // Translate and cache
        $tName = self::translate($via['name'] ?? '', $lang);
        $tDesc = self::translate(strip_tags($via['description'] ?? ''), $lang);

        try {
            $db->execute(
                'INSERT INTO via_translations (via_id, lang, name, description, translated_at)
                 VALUES (:vid, :lang, :name, :desc, NOW())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), translated_at = NOW()',
                [':vid' => (int)$via['id'], ':lang' => $lang, ':name' => $tName, ':desc' => $tDesc]
            );
        } catch (Exception $e) { /* silent */ }

        if (!empty($tName)) $via['name']        = $tName;
        if (!empty($tDesc)) $via['description'] = $tDesc;
        return $via;
    }

    /**
     * Detect language and translate text to French if it is not already French.
     * Returns ['text' => ..., 'translated' => bool, 'from' => 'xx']
     */
    public static function toFrench(string $text): array {
        if (empty(trim($text))) return ['text' => $text, 'translated' => false, 'from' => 'fr'];

        $apiKey = env('DEEPL_API_KEY', '');
        if (!empty($apiKey)) {
            $result = self::deepLDetect($text, $apiKey);
            if ($result !== null) {
                return $result;
            }
        }

        // MyMemory fallback with auto-detect
        $result = self::myMemoryToFrench($text);
        return $result ?? ['text' => $text, 'translated' => false, 'from' => 'fr'];
    }

    // ── Private: DeepL ────────────────────────────────────────────────────

    private static function deepL(string $text, string $targetLang, string $sourceLang): ?string {
        $apiKey = env('DEEPL_API_KEY', '');
        if (empty($apiKey)) return null;

        $body = http_build_query([
            'text'        => $text,
            'target_lang' => $targetLang,
            'source_lang' => $sourceLang,
        ]);
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Authorization: DeepL-Auth-Key {$apiKey}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 6,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents(self::DEEPL_FREE_URL, false, $ctx);
        if ($resp === false) return null;

        $data = json_decode($resp, true);
        // 456 = quota exceeded → fall through to MyMemory
        if (isset($data['message']) && stripos($data['message'], 'quota') !== false) return null;
        return $data['translations'][0]['text'] ?? null;
    }

    private static function deepLDetect(string $text, string $apiKey): ?array {
        // Detect via short snippet, then translate full text if not French
        $snippet = mb_substr($text, 0, 300);
        $body = http_build_query(['text' => $snippet, 'target_lang' => 'FR']);
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Authorization: DeepL-Auth-Key {$apiKey}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 8,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents(self::DEEPL_FREE_URL, false, $ctx);
        if (!$resp) return null;

        $data = json_decode($resp, true);
        if (empty($data['translations'][0])) return null;
        if (isset($data['message']) && stripos($data['message'], 'quota') !== false) return null;

        $detectedLang = strtolower($data['translations'][0]['detected_source_language'] ?? 'fr');
        if ($detectedLang === 'fr') return ['text' => $text, 'translated' => false, 'from' => 'fr'];

        // Translate full text
        $body2 = http_build_query(['text' => $text, 'target_lang' => 'FR']);
        $ctx2 = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Authorization: DeepL-Auth-Key {$apiKey}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body2,
            'timeout' => 12,
            'ignore_errors' => true,
        ]]);
        $resp2 = @file_get_contents(self::DEEPL_FREE_URL, false, $ctx2);
        if (!$resp2) return null;
        $data2 = json_decode($resp2, true);
        $translated = $data2['translations'][0]['text'] ?? null;
        if ($translated === null) return null;
        return ['text' => $translated, 'translated' => true, 'from' => $detectedLang];
    }

    // ── Private: MyMemory ────────────────────────────────────────────────

    private static function myMemory(string $text, string $fromLang, string $toLang): ?string {
        $url = self::MYMEMORY_URL . '?' . http_build_query([
            'q'        => $text,
            'langpair' => "{$fromLang}|{$toLang}",
            'de'       => env('ADMIN_EMAIL', ''),
        ]);
        $ctx = stream_context_create(['http' => ['timeout' => 6]]);
        $resp = @file_get_contents($url, false, $ctx);
        if (!$resp) return null;
        $data = json_decode($resp, true);
        if (($data['responseStatus'] ?? 0) == 200) {
            return $data['responseData']['translatedText'] ?? null;
        }
        return null;
    }

    private static function myMemoryToFrench(string $text): ?array {
        $snippet = mb_substr($text, 0, 200);
        $url = self::MYMEMORY_URL . '?' . http_build_query([
            'q'        => $snippet,
            'langpair' => 'autodetect|fr',
            'de'       => env('ADMIN_EMAIL', ''),
        ]);
        $ctx = stream_context_create(['http' => ['timeout' => 8]]);
        $resp = @file_get_contents($url, false, $ctx);
        if (!$resp) return null;
        $data = json_decode($resp, true);
        if (($data['responseStatus'] ?? 0) != 200) return null;

        // If the snippet came back translated, the source language was detected
        $translated = $data['responseData']['translatedText'] ?? '';
        if (empty($translated) || $translated === $snippet) {
            return ['text' => $text, 'translated' => false, 'from' => 'fr'];
        }

        // Translate full text
        $url2 = self::MYMEMORY_URL . '?' . http_build_query([
            'q'        => $text,
            'langpair' => 'autodetect|fr',
            'de'       => env('ADMIN_EMAIL', ''),
        ]);
        $resp2 = @file_get_contents($url2, false, stream_context_create(['http' => ['timeout' => 12]]));
        if (!$resp2) return null;
        $data2 = json_decode($resp2, true);
        $fullTrans = $data2['responseData']['translatedText'] ?? null;
        if (!$fullTrans) return null;
        return ['text' => $fullTrans, 'translated' => true, 'from' => 'auto'];
    }
}
