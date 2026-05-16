<?php
/**
 * Class Captcha
 * Système simple de captcha mathématique pour éviter le spam
 * Alternative légère à reCAPTCHA
 */
class Captcha {

    /**
     * Génère une question mathématique simple
     * @param string $context Contexte pour différencier les captchas (ex: 'comment', 'photo')
     * @return array ['question' => string, 'answer' => int]
     */
    public static function generate(string $context = 'default'): array {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operation = rand(0, 1); // 0 = addition, 1 = soustraction

        if ($operation === 0) {
            $question = "$num1 + $num2";
            $answer = $num1 + $num2;
        } else {
            // S'assurer que le résultat est positif
            if ($num1 < $num2) {
                $temp = $num1;
                $num1 = $num2;
                $num2 = $temp;
            }
            $question = "$num1 - $num2";
            $answer = $num1 - $num2;
        }

        // Stocker la réponse en session (hashée) avec le contexte
        $_SESSION['captcha_answer_' . $context] = hash('sha256', (string)$answer);
        $_SESSION['captcha_time_' . $context] = time();

        return [
            'question' => $question,
            'answer' => $answer // Ne pas utiliser dans le HTML, juste pour référence
        ];
    }

    /**
     * Vérifie la réponse du captcha
     * @param string|int $userAnswer
     * @param string $context Contexte pour différencier les captchas (ex: 'comment', 'photo')
     * @return bool
     */
    public static function verify($userAnswer, string $context = 'default'): bool {
        // Vérifier que le captcha existe en session
        if (!isset($_SESSION['captcha_answer_' . $context]) || !isset($_SESSION['captcha_time_' . $context])) {
            return false;
        }

        // Vérifier que le captcha n'est pas expiré (10 minutes max)
        if (time() - $_SESSION['captcha_time_' . $context] > 600) {
            self::clear($context);
            return false;
        }

        // Vérifier la réponse
        $isValid = hash_equals($_SESSION['captcha_answer_' . $context], hash('sha256', (string)$userAnswer));

        // Clear le captcha après vérification
        self::clear($context);

        return $isValid;
    }

    /**
     * Nettoie le captcha de la session
     * @param string $context Contexte pour différencier les captchas (ex: 'comment', 'photo')
     */
    public static function clear(string $context = 'default'): void {
        unset($_SESSION['captcha_answer_' . $context]);
        unset($_SESSION['captcha_time_' . $context]);
    }

    /**
     * Génère un HTML pour afficher le captcha
     * @param string $inputName
     * @param string $context Contexte pour différencier les captchas (ex: 'comment', 'photo')
     * @return string
     */
    public static function renderHtml(string $inputName = 'captcha_answer', string $context = 'default'): string {
        $captcha = self::generate($context);

        return <<<HTML
        <div class="captcha-container">
            <label for="{$inputName}" class="captcha-label">
                Combien font {$captcha['question']} ? <span class="required">*</span>
            </label>
            <input type="number"
                   id="{$inputName}"
                   name="{$inputName}"
                   class="captcha-input"
                   required
                   min="0"
                   placeholder="Votre réponse"
                   autocomplete="off">
        </div>
HTML;
    }
}
