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
 * Suggestion Generator
 * 
 * Generates secure query suggestions based on detected issues
 */
class SuggestionGenerator
{
    /**
     * Generate secure query suggestion
     * 
     * @param array $report The validation report
     * @return array|null Suggestion or null if none applicable
     */
    public static function generate(array $report): ?array
    {
        // Only generate suggestions in verbose mode
        if (!$report['verbose']) {
            return null;
        }
        
        // Check for non-prepared query
        if (!$report['is_prepared']) {
            return self::suggestPreparedStatement($report);
        }
        
        // Check for parameter count mismatch
        foreach ($report['erreurs'] as $error) {
            if (strpos($error, 'Placeholder count') !== false && strpos($error, 'differs from parameter count') !== false) {
                return self::suggestFixParameterCount($report);
            }
            
            if (strpos($error, 'Field count') !== false && strpos($error, 'differs') !== false) {
                return self::suggestFixFieldCount($report);
            }
            
            if (strpos($error, 'DELETE without WHERE') !== false) {
                return self::suggestAddWhereClause($report, 'DELETE');
            }
        }
        
        // Check for warnings
        foreach ($report['avertissements'] as $warning) {
            if (strpos($warning, 'UPDATE without WHERE') !== false) {
                return self::suggestAddWhereClause($report, 'UPDATE');
            }
            
            if (strpos($warning, 'SELECT *') !== false) {
                return self::suggestSpecificColumns($report);
            }
            
            if (strpos($warning, 'Hardcoded values') !== false) {
                return self::suggestUsePlaceholders($report);
            }
        }
        
        return null;
    }
    
    /**
     * Suggest converting to prepared statement
     */
    private static function suggestPreparedStatement(array $report): array
    {
        $query = $report['query'];
        $type = strtoupper(strtok($query, ' '));
        
        // Try to detect values in the query and suggest placeholders
        $suggestion = [
            'query' => null,
            'code' => null,
            'explanation' => "Convert to a prepared statement to prevent SQL injection. Replace values with placeholders (?) and pass them as parameters."
        ];
        
        // Example for common patterns
        if ($type === 'SELECT') {
            $suggestion['query'] = "SELECT * FROM table WHERE column = ?";
            $suggestion['code'] = '$stmt = $pdo->prepare("SELECT * FROM table WHERE column = ?");' . "\n" .
                                  '$stmt->execute([$value]);' . "\n" .
                                  '$result = $stmt->fetchAll();';
        } elseif ($type === 'INSERT') {
            $suggestion['query'] = "INSERT INTO table (col1, col2) VALUES (?, ?)";
            $suggestion['code'] = '$stmt = $pdo->prepare("INSERT INTO table (col1, col2) VALUES (?, ?)");' . "\n" .
                                  '$stmt->execute([$value1, $value2]);';
        } elseif ($type === 'UPDATE') {
            $suggestion['query'] = "UPDATE table SET column = ? WHERE id = ?";
            $suggestion['code'] = '$stmt = $pdo->prepare("UPDATE table SET column = ? WHERE id = ?");' . "\n" .
                                  '$stmt->execute([$newValue, $id]);';
        } elseif ($type === 'DELETE') {
            $suggestion['query'] = "DELETE FROM table WHERE id = ?";
            $suggestion['code'] = '$stmt = $pdo->prepare("DELETE FROM table WHERE id = ?");' . "\n" .
                                  '$stmt->execute([$id]);';
        }
        
        return $suggestion;
    }
    
    /**
     * Suggest fixing parameter count
     */
    private static function suggestFixParameterCount(array $report): array
    {
        $query = $report['query'];
        $params = $report['params'] ?? [];
        
        // Count placeholders
        $questionMarks = substr_count($query, '?');
        $namedPlaceholders = preg_match_all('/:\w+/', $query);
        $totalPlaceholders = $questionMarks + $namedPlaceholders;
        $paramCount = count($params);
        
        $suggestion = [
            'query' => $query,
            'code' => null,
            'explanation' => null
        ];
        
        if ($totalPlaceholders > $paramCount) {
            $missing = $totalPlaceholders - $paramCount;
            $suggestion['explanation'] = "You have $totalPlaceholders placeholders but only $paramCount parameters. Add $missing more parameter(s) to the array.";
            
            // Show example with correct number of params
            $exampleParams = array_merge($params, array_fill(0, $missing, 'missing_value'));
            $suggestion['code'] = '$query = [' . "\n" .
                                  '    \'sql\' => "' . addslashes($query) . '",' . "\n" .
                                  '    \'params\' => ' . var_export($exampleParams, true) . "\n" .
                                  '];';
        } else {
            $extra = $paramCount - $totalPlaceholders;
            $suggestion['explanation'] = "You have $paramCount parameters but only $totalPlaceholders placeholders. Remove $extra parameter(s) from the array or add more placeholders.";
            
            $correctParams = array_slice($params, 0, $totalPlaceholders);
            $suggestion['code'] = '$query = [' . "\n" .
                                  '    \'sql\' => "' . addslashes($query) . '",' . "\n" .
                                  '    \'params\' => ' . var_export($correctParams, true) . "\n" .
                                  '];';
        }
        
        return $suggestion;
    }
    
    /**
     * Suggest fixing field count in INSERT
     */
    private static function suggestFixFieldCount(array $report): array
    {
        $query = $report['query'];
        
        return [
            'query' => 'INSERT INTO table (field1, field2, field3) VALUES (?, ?, ?)',
            'code' => '// Ensure the number of fields matches the number of placeholders' . "\n" .
                      '$fields = [\'field1\', \'field2\', \'field3\'];' . "\n" .
                      '$placeholders = array_fill(0, count($fields), \'?\');' . "\n" .
                      '$sql = "INSERT INTO table (" . implode(\', \', $fields) . ") VALUES (" . implode(\', \', $placeholders) . ")";',
            'explanation' => 'The number of fields in your INSERT statement must match the number of VALUES placeholders. Count them carefully or use array functions to ensure they match.'
        ];
    }
    
    /**
     * Suggest adding WHERE clause
     */
    private static function suggestAddWhereClause(array $report, string $type): array
    {
        $query = $report['query'];
        
        return [
            'query' => "$type FROM table WHERE id = ?",
            'code' => '// ALWAYS use a WHERE clause with ' . $type . "\n" .
                      '$stmt = $pdo->prepare("' . $type . ' FROM table WHERE id = ?");' . "\n" .
                      '$stmt->execute([$id]);' . "\n\n" .
                      '// Or for multiple conditions:' . "\n" .
                      '$stmt = $pdo->prepare("' . $type . ' FROM table WHERE status = ? AND created_at < ?");' . "\n" .
                      '$stmt->execute([$status, $date]);',
            'explanation' => "CRITICAL: $type without WHERE clause will affect ALL rows in the table! Always specify which rows to $type using a WHERE clause with appropriate conditions."
        ];
    }
    
    /**
     * Suggest using specific columns instead of SELECT *
     */
    private static function suggestSpecificColumns(array $report): array
    {
        return [
            'query' => 'SELECT id, name, email, created_at FROM users WHERE active = ?',
            'code' => '// Specify only the columns you need' . "\n" .
                      '$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE active = ?");' . "\n" .
                      '$stmt->execute([1]);' . "\n\n" .
                      '// Benefits:' . "\n" .
                      '// 1. Better performance (less data transferred)' . "\n" .
                      '// 2. More maintainable (explicit dependencies)' . "\n" .
                      '// 3. Safer (won\'t break if table structure changes)',
            'explanation' => 'Avoid SELECT * in production code. Explicitly list the columns you need. This improves performance, makes your code more maintainable, and prevents issues when table structure changes.'
        ];
    }
    
    /**
     * Suggest using placeholders instead of hardcoded values
     */
    private static function suggestUsePlaceholders(array $report): array
    {
        return [
            'query' => 'INSERT INTO logs (message, level, user_id) VALUES (?, ?, ?)',
            'code' => '// WRONG: Hardcoded values in prepared statement' . "\n" .
                      '// $sql = "INSERT INTO logs (message, level) VALUES (?, \'ERROR\')";' . "\n\n" .
                      '// CORRECT: Use placeholders for all values' . "\n" .
                      '$sql = "INSERT INTO logs (message, level, user_id) VALUES (?, ?, ?)";' . "\n" .
                      '$stmt = $pdo->prepare($sql);' . "\n" .
                      '$stmt->execute([$message, $level, $userId]);',
            'explanation' => 'Even in prepared statements, avoid hardcoding values directly in the SQL. Use placeholders for all dynamic values. This makes your queries more flexible and consistent.'
        ];
    }
}
