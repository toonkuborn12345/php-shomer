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
 * Syntax Validator
 * 
 * Validates SQL syntax and structure
 */
class SyntaxValidator
{
    /**
     * Validate SQL syntax
     * 
     * @param string $query The SQL query
     * @param array $report The validation report
     * @param bool $verbose Verbose mode
     * @return void
     */
    public static function validate(string $query, array &$report, bool $verbose = false): void
    {
        if ($verbose) {
            $report['infos'][] = "🔍 Syntax analysis: " . substr($query, 0, 100) . "...";
        }
        
        // Detect query type
        $type = strtoupper(strtok($query, ' '));
        if ($verbose) {
            $report['infos'][] = "Query type detected: $type";
        }
        
        // Check parentheses balance
        self::validateParentheses($query, $report, $verbose);
        
        // Check quotes balance (for non-prepared queries)
        if (!$report['is_prepared']) {
            self::validateQuotes($query, $report, $verbose);
        }
        
        // Check semicolon termination
        if (!str_ends_with(trim($query), ';')) {
            $report['avertissements'][] = "⚠️ Query does not end with semicolon ';'";
        }
        
        // Query-specific validation
        switch ($type) {
            case 'INSERT':
                self::validateInsert($query, $report, $verbose);
                break;
            case 'UPDATE':
                self::validateUpdate($query, $report, $verbose);
                break;
            case 'SELECT':
                self::validateSelect($query, $report, $verbose);
                break;
            case 'DELETE':
                self::validateDelete($query, $report, $verbose);
                break;
        }
    }
    
    /**
     * Validate parentheses balance
     */
    private static function validateParentheses(string $query, array &$report, bool $verbose): void
    {
        $opening = substr_count($query, '(');
        $closing = substr_count($query, ')');
        
        if ($opening !== $closing) {
            $report['erreurs'][] = "❌ ERROR: Unbalanced parentheses (opening: $opening, closing: $closing)";
        } elseif ($verbose) {
            $report['infos'][] = "✓ Balanced parentheses: $opening pairs";
        }
    }
    
    /**
     * Validate quotes balance
     */
    private static function validateQuotes(string $query, array &$report, bool $verbose): void
    {
        $singleQuotes = substr_count($query, "'");
        if ($singleQuotes % 2 !== 0) {
            $report['erreurs'][] = "❌ ERROR: Unpaired single quotes (') (count: $singleQuotes)";
        } elseif ($verbose && $singleQuotes > 0) {
            $report['infos'][] = "✓ Single quotes paired: " . ($singleQuotes / 2) . " pairs";
        }
        
        $doubleQuotes = substr_count($query, '"');
        if ($doubleQuotes % 2 !== 0) {
            $report['erreurs'][] = "❌ ERROR: Unpaired double quotes (\") (count: $doubleQuotes)";
        } elseif ($verbose && $doubleQuotes > 0) {
            $report['infos'][] = "✓ Double quotes paired: " . ($doubleQuotes / 2) . " pairs";
        }
    }
    
    /**
     * Validate INSERT statement
     */
    private static function validateInsert(string $query, array &$report, bool $verbose): void
    {
        if ($report['is_prepared']) {
            // For prepared statements, check field count vs placeholder count
            if (preg_match('/INSERT\s+INTO\s+(\w+)\s*\(([^)]+)\)\s*VALUES\s*\(([^)]+)\)/i', $query, $matches)) {
                $table = $matches[1];
                $fields = array_map('trim', explode(',', $matches[2]));
                $placeholderStr = $matches[3];
                
                $placeholderCount = substr_count($placeholderStr, '?') + preg_match_all('/:\w+/', $placeholderStr);
                $fieldCount = count($fields);
                
                if ($fieldCount !== $placeholderCount) {
                    $report['erreurs'][] = "❌ INSERT ERROR: Field count ($fieldCount) differs from placeholder count ($placeholderCount)";
                } elseif ($verbose) {
                    $report['infos'][] = "✓ INSERT: $fieldCount fields match $placeholderCount placeholders";
                    $report['infos'][] = "Target table: $table";
                }
            }
        } else {
            // For classic queries, check field count vs value count
            if (preg_match('/INSERT\s+INTO\s+\w+\s*\(([^)]+)\)\s*VALUES\s*\(([^)]+)\)/i', $query, $matches)) {
                $fields = array_map('trim', explode(',', $matches[1]));
                $values = array_map('trim', explode(',', $matches[2]));
                
                if (count($fields) !== count($values)) {
                    $report['erreurs'][] = "❌ INSERT ERROR: Field count (" . count($fields) . ") differs from value count (" . count($values) . ")";
                } elseif ($verbose) {
                    $report['infos'][] = "✓ INSERT : " . count($fields) . " fields match " . count($values) . " values";
                }
            }
        }
    }
    
    /**
     * Validate UPDATE statement
     */
    private static function validateUpdate(string $query, array &$report, bool $verbose): void
    {
        if (stripos($query, 'WHERE') === false) {
            $report['avertissements'][] = "⚠️ CRITICAL WARNING: UPDATE without WHERE clause (risk of modifying all rows)";
        } elseif ($verbose) {
            $report['infos'][] = "✓ UPDATE : WHERE clause present";
        }
        
        $setCount = substr_count(strtoupper($query), 'SET');
        if ($setCount > 1) {
            $report['avertissements'][] = "⚠️ Multiple SET clauses detected ($setCount)";
        }
    }
    
    /**
     * Validate SELECT statement
     */
    private static function validateSelect(string $query, array &$report, bool $verbose): void
    {
        if (preg_match('/SELECT\s+\*/i', $query)) {
            $report['avertissements'][] = "⚠️ Use of SELECT * (not recommended in production)";
        }
        
        if ($verbose) {
            $joinCount = preg_match_all('/\bJOIN\b/i', $query);
            if ($joinCount > 0) {
                $report['infos'][] = "SELECT : $joinCount JOIN(s) detected";
            }
        }
    }
    
    /**
     * Validate DELETE statement
     */
    private static function validateDelete(string $query, array &$report, bool $verbose): void
    {
        if (stripos($query, 'WHERE') === false) {
            $report['erreurs'][] = "❌ CRITICAL ERROR: DELETE without WHERE clause (risk of deleting all rows)";
        } elseif ($verbose) {
            $report['infos'][] = "✓ DELETE : WHERE clause present";
        }
    }
}
