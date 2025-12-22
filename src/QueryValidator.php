<?php
/**
 * PHP Shomer (שומר) - SQL Query Guardian
 * 
 * @package   Shomer
 * @author    Votre Nom
 * @license   MIT
 * @link      https://github.com/votre-username/php-shomer
 */

namespace Shomer;

use Shomer\Validators\SyntaxValidator;
use Shomer\Validators\SecurityValidator;
use Shomer\Validators\PreparedStatementValidator;
use Shomer\Validators\SuggestionGenerator;
use Shomer\Reports\ValidationReport;

/**
 * Main Query Validator Class
 * 
 * The guardian (שומר) that watches over your SQL queries
 */
class QueryValidator
{
    /**
     * Global enable/disable flag
     * Set to false in production for zero overhead
     */
    const ENABLED = true; // Override with define('SHOMER_ENABLED', false);
    
    /**
     * Validate a SQL query
     * 
     * @param string|array $query The SQL query (string) or prepared statement (array with 'sql' and 'params')
     * @param bool $enabled Enable validation (false = instant bypass)
     * @param bool $verbose Verbose mode - return all details, not just errors
     * @param mixed $emailHandler Email handler (1 = mail(), string = custom function name, null = no email)
     * @return array Validation report
     */
    public static function validate($query, bool $enabled = true, bool $verbose = false, $emailHandler = null): array
    {
        // Check global enable flag first
        $globalEnabled = defined('SHOMER_ENABLED') ? SHOMER_ENABLED : self::ENABLED;
        
        // Instant bypass if disabled - zero overhead
        if (!$globalEnabled || !$enabled) {
            return ValidationReport::createBypassed($query);
        }
        
        // Initialize report
        $report = ValidationReport::create($query, $verbose);
        
        // Detect query type (prepared vs classic)
        if (is_array($query)) {
            $report['is_prepared'] = true;
            $report['query'] = $query['sql'] ?? $query['query'] ?? '';
            $report['params'] = $query['params'] ?? [];
            
            if ($verbose) {
                $report['infos'][] = "✓ Type detected: Prepared statement (RECOMMENDED)";
                $report['infos'][] = "Parameter count: " . count($report['params']);
            }
        } else {
            $report['is_prepared'] = false;
            $report['query'] = $query;
            $report['avertissements'][] = "⚠️ SECURITY WARNING: Non-prepared query detected. Use prepared statements to prevent SQL injection!";
        }
        
        $queryString = trim($report['query']);
        
        // Validate query is not empty
        if (empty($queryString)) {
            $report['erreurs'][] = "❌ CRITICAL ERROR: Empty query";
            return ValidationReport::finalize($report, $emailHandler);
        }
        
        // Phase 1: Syntax Validation
        SyntaxValidator::validate($queryString, $report, $verbose);
        
        // Phase 2: Prepared Statement Validation (if applicable)
        if ($report['is_prepared']) {
            PreparedStatementValidator::validate($queryString, $report, $verbose);
        }
        
        // Phase 3: Security Validation
        SecurityValidator::validate($queryString, $report, $verbose);
        
        // Phase 4: Generate Secure Query Suggestion (in verbose mode)
        if ($verbose) {
            $suggestion = SuggestionGenerator::generate($report);
            if ($suggestion) {
                $report['suggestion'] = $suggestion;
            }
        }
        
        // Finalize and send email if errors found
        return ValidationReport::finalize($report, $emailHandler);
    }
    
    /**
     * Quick validation without detailed report
     * 
     * @param string|array $query The SQL query
     * @param bool $enabled Enable validation
     * @return bool True if valid, false if errors found
     */
    public static function isValid($query, bool $enabled = true): bool
    {
        $report = self::validate($query, $enabled, false);
        return $report['status'] === 'success' || $report['status'] === 'bypassed';
    }
    
    /**
     * Get version information
     * 
     * @return string Version number
     */
    public static function version(): string
    {
        return '1.0.0';
    }
}
