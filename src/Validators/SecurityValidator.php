<?php
/**
 * PHP Shomer (שומר) - SQL Query Guardian
 * 
 * @package   Shomer
 * @author    Votre Nom
 * @license   MIT
 */

namespace Shomer\Validators;

/**
 * Security Validator
 * 
 * Validates security aspects of SQL queries
 */
class SecurityValidator
{
    /**
     * Validate security aspects
     * 
     * @param string $query The SQL query
     * @param array $report The validation report
     * @param bool $verbose Verbose mode
     * @return void
     */
    public static function validate(string $query, array &$report, bool $verbose = false): void
    {
        // Only perform deep security checks on non-prepared queries
        if (!$report['is_prepared']) {
            self::checkInjectionPatterns($query, $report, $verbose);
            self::checkSuperglobals($query, $report);
            self::checkDangerousKeywords($query, $report, $verbose);
        }
        
        // Universal checks (both prepared and non-prepared)
        self::checkComments($query, $report, $verbose);
    }
    
    /**
     * Check for SQL injection patterns
     */
    private static function checkInjectionPatterns(string $query, array &$report, bool $verbose): void
    {
        // Extract strings between quotes
        preg_match_all("/'([^']*)'/", $query, $matches);
        
        if ($verbose && !empty($matches[1])) {
            $report['infos'][] = "String count detected : " . count($matches[1]);
        }
        
        $dangerousPatterns = [
            '/--/' => 'SQL comment (--)',
            '/\/\*/' => 'Début de commentaire bloc (/*)',
            '/\*\//' => 'Fin de commentaire bloc (*/)',
            '/xp_/i' => 'Procédure étendue SQL Server (xp_)',
            '/sp_/i' => 'Procédure système SQL Server (sp_)',
            '/\bUNION\b/i' => 'UNION',
            '/\bDROP\b/i' => 'DROP',
            '/;\s*DROP/i' => 'DROP après point-virgule',
            '/OR\s+[\'"]?1[\'"]?\s*=\s*[\'"]?1[\'"]?/i' => 'OR 1=1 pattern',
            '/OR\s+[\'"]?[a-z][\'"]?\s*=\s*[\'"]?[a-z][\'"]?/i' => 'OR \'x\'=\'x\' pattern'
        ];
        
        foreach ($matches[1] as $index => $string) {
            foreach ($dangerousPatterns as $pattern => $description) {
                if (preg_match($pattern, $string)) {
                    $report['erreurs'][] = "❌ SECURITY ERROR: Dangerous pattern '$description' detected in string #" . ($index + 1) . " : '" . substr($string, 0, 50) . "'";
                }
            }
            
            // Check for unescaped backslashes
            if (strpos($string, "\\") !== false && strpos($string, "\\\\") === false) {
                $report['avertissements'][] = "⚠️ Unescaped backslash detected in : '" . substr($string, 0, 50) . "'";
            }
            
            if ($verbose) {
                $preview = substr($string, 0, 50) . (strlen($string) > 50 ? "..." : "");
                $report['infos'][] = "String #" . ($index + 1) . " analyzed : '$preview'";
            }
        }
    }
    
    /**
     * Check for superglobal variables
     */
    private static function checkSuperglobals(string $query, array &$report): void
    {
        $superglobals = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SESSION', '$_SERVER'];
        
        foreach ($superglobals as $global) {
            if (strpos($query, $global) !== false) {
                $report['erreurs'][] = "❌ CRITICAL SECURITY ERROR: Superglobal variable $global directly in query! SQL INJECTION POSSIBLE!";
            }
        }
        
        // Check for any PHP variables
        if (preg_match('/\$\w+/', $query)) {
            $report['avertissements'][] = "⚠️ SECURITY WARNING: PHP variables detected in query. Ensure they are properly escaped!";
        }
    }
    
    /**
     * Check for dangerous SQL keywords
     */
    private static function checkDangerousKeywords(string $query, array &$report, bool $verbose): void
    {
        $dangerousKeywords = [
            'TRUNCATE',
            'LOAD_FILE',
            'OUTFILE',
            'DUMPFILE',
            'INTO OUTFILE',
            'INTO DUMPFILE',
            'EXEC(',
            'EXECUTE(',
            'BENCHMARK(',
            'SLEEP(',
            'WAITFOR DELAY'
        ];
        
        foreach ($dangerousKeywords as $keyword) {
            if (stripos($query, $keyword) !== false) {
                $report['avertissements'][] = "⚠️ Potentially dangerous keyword detected : $keyword";
            }
        }
    }
    
    /**
     * Check for SQL comments
     */
    private static function checkComments(string $query, array &$report, bool $verbose): void
    {
        // Single-line comments
        if (strpos($query, '--') !== false) {
            $report['avertissements'][] = "⚠️ SQL comment ('--') detected in query";
        }
        
        // Block comments
        if (strpos($query, '/*') !== false || strpos($query, '*/') !== false) {
            $report['avertissements'][] = "⚠️ Block comment ('/* */') detected in query";
        }
        
        // Hash comments
        if (preg_match('/#[^\n]*/', $query)) {
            $report['avertissements'][] = "⚠️ Hash comment ('#') detected in query";
        }
    }
}
