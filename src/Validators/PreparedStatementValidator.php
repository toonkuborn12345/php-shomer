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
 * Prepared Statement Validator
 * 
 * Validates prepared statements and their parameters
 */
class PreparedStatementValidator
{
    /**
     * Validate prepared statement
     * 
     * @param string $query The SQL query
     * @param array $report The validation report
     * @param bool $verbose Verbose mode
     * @return void
     */
    public static function validate(string $query, array &$report, bool $verbose = false): void
    {
        // Count placeholders
        $questionMarkCount = substr_count($query, '?');
        $namedPlaceholderCount = preg_match_all('/:\w+/', $query, $namedMatches);
        
        $totalPlaceholders = $questionMarkCount + $namedPlaceholderCount;
        $paramCount = count($report['params']);
        
        if ($verbose) {
            $report['infos'][] = "Placeholders '?' found : $questionMarkCount";
            $report['infos'][] = "Named placeholders found : $namedPlaceholderCount";
            
            if ($namedPlaceholderCount > 0) {
                $report['infos'][] = "Placeholder names : " . implode(', ', $namedMatches[0]);
            }
        }
        
        // Check placeholder vs parameter count
        if ($totalPlaceholders !== $paramCount) {
            $report['erreurs'][] = "❌ CRITICAL ERROR: Placeholder count ($totalPlaceholders) differs from parameter count ($paramCount)";
        } elseif ($verbose) {
            $report['infos'][] = "✓ Perfect match : $totalPlaceholders placeholders = $paramCount parameters";
        }
        
        // Check for mixed placeholder types
        if ($questionMarkCount > 0 && $namedPlaceholderCount > 0) {
            $report['erreurs'][] = "❌ ERROR: Mixed '?' and named ':name' placeholders in same query (forbidden)";
        }
        
        // Validate parameters
        if ($verbose && !empty($report['params'])) {
            $report['infos'][] = "=== PARAMETER ANALYSIS ===";
        }
        
        self::validateParameters($report, $verbose);
        
        // Check for hardcoded values in prepared queries
        self::checkHardcodedValues($query, $report, $verbose);
        
        // Check for PHP variables in query
        self::checkPhpVariables($query, $report);
    }
    
    /**
     * Validate individual parameters
     */
    private static function validateParameters(array &$report, bool $verbose): void
    {
        foreach ($report['params'] as $key => $value) {
            if ($verbose) {
                $type = gettype($value);
                $preview = is_string($value) ? substr($value, 0, 50) : var_export($value, true);
                $report['infos'][] = "Parameter [$key] : type=$type, valeur=$preview";
            }
            
            if (is_string($value)) {
                // Check for suspicious characters
                $suspiciousChars = ['--', '/*', '*/', 'xp_', 'sp_'];
                
                foreach ($suspiciousChars as $suspect) {
                    if (stripos($value, $suspect) !== false) {
                        $report['avertissements'][] = "⚠️ Suspicious character '$suspect' detected in parameter [$key]";
                    }
                }
                
                // Check for SQL keywords (possible injection attempt)
                $sqlKeywords = ['UNION', 'DROP', 'DELETE', 'UPDATE', 'INSERT', 'SELECT', 'OR 1=1', 'OR \'1\'=\'1\''];
                
                foreach ($sqlKeywords as $keyword) {
                    if (stripos($value, $keyword) !== false) {
                        $report['avertissements'][] = "⚠️ SQL keyword '$keyword' detected in parameter [$key]. Verify this is intentional.";
                    }
                }
            }
        }
    }
    
    /**
     * Check for hardcoded values in prepared queries
     */
    private static function checkHardcodedValues(string $query, array &$report, bool $verbose): void
    {
        if (preg_match("/'[^']*'/", $query)) {
            $report['avertissements'][] = "⚠️ Hardcoded values (in quotes) detected in prepared query. Use placeholders!";
            
            if ($verbose) {
                preg_match_all("/'([^']*)'/", $query, $matches);
                if (!empty($matches[1])) {
                    $values = array_map(function($v) {
                        return "'" . substr($v, 0, 30) . (strlen($v) > 30 ? "..." : "") . "'";
                    }, $matches[1]);
                    $report['infos'][] = "Hardcoded values found : " . implode(', ', $values);
                }
            }
        }
    }
    
    /**
     * Check for PHP variables in query string
     */
    private static function checkPhpVariables(string $query, array &$report): void
    {
        // Check for superglobals
        if (preg_match('/\$_(?:GET|POST|REQUEST|COOKIE|SESSION)\[/', $query)) {
            $report['erreurs'][] = "❌ CRITICAL ERROR: PHP superglobal variables detected in query! SQL INJECTION POSSIBLE!";
        }
        
        // Check for regular variables
        if (preg_match('/\$\w+/', $query) || preg_match('/\{\$\w+\}/', $query)) {
            $report['erreurs'][] = "❌ CRITICAL ERROR: PHP variables detected in prepared query! Use placeholders instead.";
        }
    }
}
