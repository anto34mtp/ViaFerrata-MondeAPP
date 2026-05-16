<?php
/**
 * Classe pour nettoyer et sécuriser le HTML sans dépendances externes
 * Alternative légère à HTMLPurifier
 */
class HtmlSanitizer {
    
    /**
     * Liste des balises HTML autorisées
     */
    private static $allowedTags = [
        'p', 'br', 'strong', 'em', 'u', 'b', 'i',
        'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'a', 'span', 'div',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'blockquote', 'code', 'pre'
    ];
    
    /**
     * Liste des attributs autorisés par balise
     */
    private static $allowedAttributes = [
        'a' => ['href', 'target', 'rel', 'title'],
        'img' => ['src', 'alt', 'width', 'height', 'title'],
        'div' => ['class', 'id'],
        'span' => ['class', 'id'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        'table' => ['class', 'border', 'cellpadding', 'cellspacing', 'style'],
        'tr' => ['class'],
    ];
    
    /**
     * Nettoie et sécurise du HTML
     * 
     * @param string $html HTML brut à nettoyer
     * @return string HTML nettoyé et sécurisé
     */
    public static function clean($html) {
        if (empty($html)) {
            return '';
        }
        
        // Construction de la liste des balises autorisées pour strip_tags
        $allowedTagsString = '<' . implode('><', self::$allowedTags) . '>';
        
        // Étape 1 : Ne garder que les balises autorisées
        $cleaned = strip_tags($html, $allowedTagsString);
        
        // Étape 2 : Nettoyer les attributs dangereux
        $cleaned = self::cleanAttributes($cleaned);
        
        // Étape 3 : Sécuriser les liens
        $cleaned = self::secureLinks($cleaned);
        
        return $cleaned;
    }
    
    /**
     * Nettoie les attributs HTML dangereux
     * 
     * @param string $html
     * @return string
     */
    private static function cleanAttributes($html) {
        // Supprimer les attributs JavaScript (onclick, onload, onerror, etc.)
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s*on\w+\s*=\s*\w+/i', '', $html);
        
        // Supprimer les javascript: dans les attributs
        $html = preg_replace('/javascript\s*:/i', '', $html);
        
        // Supprimer les data: URIs potentiellement dangereux (sauf images)
        $html = preg_replace('/src\s*=\s*["\']data:(?!image\/)/i', 'src="', $html);
        
        // Nettoyer les attributs style pour éviter les injections CSS
        $html = preg_replace_callback(
            '/style\s*=\s*["\']([^"\']*?)["\']/i',
            function($matches) {
                return 'style="' . self::cleanStyleAttribute($matches[1]) . '"';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Nettoie l'attribut style pour ne garder que les propriétés CSS sûres
     * 
     * @param string $style
     * @return string
     */
    private static function cleanStyleAttribute($style) {
        // Liste blanche de propriétés CSS autorisées
        $allowedProperties = [
            'color', 'background-color', 'font-size', 'font-weight', 'font-family',
            'text-align', 'text-decoration', 'margin', 'padding',
            'border', 'border-collapse', 'width', 'height'
        ];
        
        $cleanedStyles = [];
        $declarations = explode(';', $style);
        
        foreach ($declarations as $declaration) {
            $declaration = trim($declaration);
            if (empty($declaration)) continue;
            
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) continue;
            
            $property = trim(strtolower($parts[0]));
            $value = trim($parts[1]);
            
            // Vérifier que la propriété est autorisée
            if (in_array($property, $allowedProperties)) {
                // Supprimer les expressions et URLs dangereuses
                if (!preg_match('/(expression|javascript|vbscript|behaviour|data:)/i', $value)) {
                    $cleanedStyles[] = $property . ': ' . $value;
                }
            }
        }
        
        return implode('; ', $cleanedStyles);
    }
    
    /**
     * Sécurise les liens externes
     * 
     * @param string $html
     * @return string
     */
    private static function secureLinks($html) {
        // Ajouter rel="noopener noreferrer" aux liens externes avec target="_blank"
        $html = preg_replace_callback(
            '/<a\s+([^>]*?)>/i',
            function($matches) {
                $attributes = $matches[1];
                
                // Si le lien a target="_blank"
                if (preg_match('/target\s*=\s*["\']_blank["\']/i', $attributes)) {
                    // Vérifier si rel existe déjà
                    if (!preg_match('/rel\s*=/i', $attributes)) {
                        $attributes .= ' rel="noopener noreferrer"';
                    } else {
                        // Ajouter noopener et noreferrer à l'attribut rel existant
                        $attributes = preg_replace(
                            '/rel\s*=\s*["\']([^"\']*)["\']/i',
                            function($m) {
                                $relValues = $m[1];
                                if (strpos($relValues, 'noopener') === false) {
                                    $relValues .= ' noopener';
                                }
                                if (strpos($relValues, 'noreferrer') === false) {
                                    $relValues .= ' noreferrer';
                                }
                                return 'rel="' . trim($relValues) . '"';
                            },
                            $attributes
                        );
                    }
                }
                
                return '<a ' . $attributes . '>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Nettoie du HTML tout en préservant les sauts de ligne
     * 
     * @param string $html HTML brut à nettoyer
     * @return string HTML nettoyé
     */
    public static function cleanWithLineBreaks($html) {
        // Convertir les \n en <br> avant le nettoyage
        $html = nl2br($html);
        return self::clean($html);
    }
    
    /**
     * Ajoute une balise autorisée
     * 
     * @param string $tag Nom de la balise (ex: 'img')
     * @param array $attributes Liste des attributs autorisés (optionnel)
     */
    public static function addAllowedTag($tag, $attributes = []) {
        if (!in_array($tag, self::$allowedTags)) {
            self::$allowedTags[] = $tag;
        }
        
        if (!empty($attributes)) {
            self::$allowedAttributes[$tag] = $attributes;
        }
    }
    
    /**
     * Retire une balise autorisée
     * 
     * @param string $tag Nom de la balise
     */
    public static function removeAllowedTag($tag) {
        $key = array_search($tag, self::$allowedTags);
        if ($key !== false) {
            unset(self::$allowedTags[$key]);
        }
        
        if (isset(self::$allowedAttributes[$tag])) {
            unset(self::$allowedAttributes[$tag]);
        }
    }
}
