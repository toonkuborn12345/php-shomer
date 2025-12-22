<?php
/**
 * PHP Shomer (שומר) - SQL Query Guardian
 * 
 * @package   Shomer
 * @author    Votre Nom
 * @license   MIT
 */

namespace Shomer\Reports;

/**
 * Validation Report Generator
 * 
 * Creates and manages validation reports
 */
class ValidationReport
{
    /**
     * Create a new validation report
     * 
     * @param mixed $query The query being validated
     * @param bool $verbose Include detailed info
     * @return array Initial report structure
     */
    public static function create($query, bool $verbose = false): array
    {
        // Capture execution context
        $context = self::captureExecutionContext();
        
        return [
            'status' => 'pending',
            'erreurs' => [],
            'avertissements' => [],
            'infos' => [],
            'query' => null,
            'params' => null,
            'is_prepared' => false,
            'nb_erreurs' => 0,
            'nb_avertissements' => 0,
            'timestamp' => date('Y-m-d H:i:s'),
            'verbose' => $verbose,
            'context' => $context,
            'suggestion' => null  // For secure query suggestions
        ];
    }
    
    /**
     * Capture execution context (file, line, URL, function)
     * 
     * @return array Execution context information
     */
    private static function captureExecutionContext(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        // Find the first call outside of Shomer namespace
        $caller = null;
        foreach ($backtrace as $trace) {
            $class = $trace['class'] ?? '';
            if (strpos($class, 'Shomer\\') !== 0) {
                $caller = $trace;
                break;
            }
        }
        
        // If no external caller found, use the last trace
        if (!$caller && !empty($backtrace)) {
            $caller = end($backtrace);
        }
        
        $context = [
            'file' => $caller['file'] ?? 'unknown',
            'line' => $caller['line'] ?? 0,
            'function' => $caller['function'] ?? 'unknown',
            'class' => $caller['class'] ?? null,
            'type' => $caller['type'] ?? null,
        ];
        
        // Add URL information if available (web context)
        if (isset($_SERVER['REQUEST_URI'])) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            
            $context['url'] = $protocol . '://' . $host . $uri;
            $context['method'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $context['script_name'] = $_SERVER['SCRIPT_NAME'] ?? null;
        } else {
            // CLI context
            $context['url'] = null;
            $context['method'] = 'CLI';
            $context['script_name'] = $_SERVER['SCRIPT_FILENAME'] ?? $caller['file'] ?? null;
        }
        
        // Add relative path (more readable)
        if ($context['file'] !== 'unknown') {
            $context['file_relative'] = self::getRelativePath($context['file']);
        }
        
        return $context;
    }
    
    /**
     * Get relative path from document root
     * 
     * @param string $absolutePath Absolute file path
     * @return string Relative path
     */
    private static function getRelativePath(string $absolutePath): string
    {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? getcwd();
        
        if (strpos($absolutePath, $docRoot) === 0) {
            return substr($absolutePath, strlen($docRoot));
        }
        
        // Try to get relative from current working directory
        $cwd = getcwd();
        if ($cwd && strpos($absolutePath, $cwd) === 0) {
            return '.' . substr($absolutePath, strlen($cwd));
        }
        
        return $absolutePath;
    }
    
    /**
     * Create a bypassed report (when validation is disabled)
     * 
     * @param mixed $query The query
     * @return array Bypassed report
     */
    public static function createBypassed($query): array
    {
        return [
            'status' => 'bypassed',
            'message' => 'Shomer validation disabled',
            'query' => is_array($query) ? ($query['sql'] ?? '') : $query,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Finalize the validation report
     * 
     * @param array $report The report to finalize
     * @param mixed $emailHandler Email handler (1, function name, or null)
     * @return array Finalized report
     */
    public static function finalize(array &$report, $emailHandler = null): array
    {
        // Count errors and warnings
        $report['nb_erreurs'] = count($report['erreurs']);
        $report['nb_avertissements'] = count($report['avertissements']);
        
        // Set final status
        $report['status'] = $report['nb_erreurs'] > 0 ? 'error' : 'success';
        
        // Send email notification if there are errors and handler is provided
        if ($emailHandler !== null && $report['nb_erreurs'] > 0) {
            self::sendEmailNotification($report, $emailHandler);
        }
        
        return $report;
    }
    
    /**
     * Send email notification for errors
     * 
     * @param array $report The validation report
     * @param mixed $emailHandler Email handler
     * @return bool Success status
     */
    private static function sendEmailNotification(array &$report, $emailHandler): bool
    {
        $emailBody = self::generateEmailBody($report);
        
        if ($emailHandler === 1) {
            // Use native mail() function
            $to = defined('SHOMER_EMAIL') ? SHOMER_EMAIL : 'dev@example.com';
            $subject = "[Shomer Alert] SQL Query Validation Errors";
            $headers = "From: " . (defined('SHOMER_EMAIL_FROM') ? SHOMER_EMAIL_FROM : 'noreply@shomer.local') . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            $success = mail($to, $subject, $emailBody, $headers);
            
            if ($success) {
                $report['infos'][] = "📧 Error report sent via email";
            } else {
                $report['avertissements'][] = "⚠️ Failed to send error report via email";
            }
            
            return $success;
        } elseif (is_string($emailHandler) && function_exists($emailHandler)) {
            // Use custom function
            try {
                call_user_func($emailHandler, $emailBody, $report);
                $report['infos'][] = "📧 Error report sent via email (function $emailHandler)";
                return true;
            } catch (\Exception $e) {
                $report['avertissements'][] = "⚠️ Failed to send via $emailHandler: " . $e->getMessage();
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Generate email body from report
     * 
     * @param array $report The validation report
     * @return string Email body
     */
    private static function generateEmailBody(array $report): string
    {
        $email = "╔════════════════════════════════════════════════════╗\n";
        $email .= "║  🛡️  SHOMER (שומר) - SQL QUERY GUARDIAN ALERT  ║\n";
        $email .= "╚════════════════════════════════════════════════════╝\n\n";
        
        $email .= "Date/Time: " . $report['timestamp'] . "\n";
        $email .= "Type: " . ($report['is_prepared'] ? "Requête préparée" : "Requête classique") . "\n";
        $email .= "Status: " . $report['status'] . "\n";
        $email .= "Errors: " . $report['nb_erreurs'] . "\n";
        $email .= "Warnings: " . $report['nb_avertissements'] . "\n\n";
        
        // Add execution context
        if (isset($report['context'])) {
            $ctx = $report['context'];
            $email .= "EXECUTION CONTEXT:\n";
            $email .= str_repeat("─", 50) . "\n";
            
            // File and line
            $email .= "📄 File: " . ($ctx['file_relative'] ?? $ctx['file']) . "\n";
            $email .= "📍 Line: " . $ctx['line'] . "\n";
            
            // Function/Method
            if ($ctx['class']) {
                $email .= "🔧 Method: " . $ctx['class'] . $ctx['type'] . $ctx['function'] . "()\n";
            } else {
                $email .= "🔧 Function: " . $ctx['function'] . "()\n";
            }
            
            // URL or Script
            if ($ctx['url']) {
                $email .= "🌐 URL: " . $ctx['url'] . "\n";
                $email .= "📝 Method: " . $ctx['method'] . "\n";
            } else {
                $email .= "💻 CLI Script: " . ($ctx['script_name'] ?? 'N/A') . "\n";
            }
            
            $email .= str_repeat("─", 50) . "\n\n";
        }
        
        $email .= "QUERY:\n";
        $email .= str_repeat("─", 50) . "\n";
        $email .= $report['query'] . "\n";
        $email .= str_repeat("─", 50) . "\n\n";
        
        if ($report['is_prepared'] && !empty($report['params'])) {
            $email .= "PARAMETERS:\n";
            foreach ($report['params'] as $key => $value) {
                $email .= "  [$key] = " . var_export($value, true) . "\n";
            }
            $email .= "\n";
        }
        
        if (!empty($report['erreurs'])) {
            $email .= "❌ ERRORS DETECTED:\n";
            foreach ($report['erreurs'] as $error) {
                $email .= "  • $error\n";
            }
            $email .= "\n";
        }
        
        if (!empty($report['avertissements'])) {
            $email .= "⚠️  WARNINGS:\n";
            foreach ($report['avertissements'] as $warning) {
                $email .= "  • $warning\n";
            }
            $email .= "\n";
        }
        
        if (!empty($report['infos'])) {
            $email .= "ℹ️  DETAILED INFORMATION:\n";
            foreach ($report['infos'] as $info) {
                $email .= "  • $info\n";
            }
        }
        
        // Add secure query suggestion if available
        if (!empty($report['suggestion'])) {
            $email .= "\n💡 SECURE QUERY SUGGESTION:\n";
            $email .= str_repeat("─", 50) . "\n";
            if (isset($report['suggestion']['query'])) {
                $email .= "SQL:\n" . $report['suggestion']['query'] . "\n\n";
            }
            if (isset($report['suggestion']['code'])) {
                $email .= "PHP Code Example:\n";
                $email .= $report['suggestion']['code'] . "\n";
            }
            if (isset($report['suggestion']['explanation'])) {
                $email .= "\nExplanation:\n" . $report['suggestion']['explanation'] . "\n";
            }
            $email .= str_repeat("─", 50) . "\n";
        }
        
        $email .= "\n" . str_repeat("═", 50) . "\n";
        $email .= "Shomer: Protecting your queries, one validation at a time.\n";
        $email .= "שומר - Your SQL Query Guardian\n";
        
        return $email;
    }
    
    /**
     * Display report in HTML format
     * 
     * @param array $report The validation report
     * @return string HTML output
     */
    public static function toHtml(array $report): string
    {
        $statusIcon = $report['status'] === 'success' ? '✅' : ($report['status'] === 'error' ? '❌' : '⏭️');
        $typeIcon = $report['is_prepared'] ?? false ? '🛡️' : '⚠️';
        
        $html = "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 8px;'>";
        $html .= "<h2>$statusIcon Shomer Validation Report (שומר)</h2>";
        $html .= "<p><strong>Type:</strong> $typeIcon " . ($report['is_prepared'] ?? false ? "Prepared Statement" : "Classic Query") . "</p>";
        $html .= "<p><strong>Status:</strong> " . strtoupper($report['status']) . "</p>";
        $html .= "<p><strong>Errors:</strong> " . ($report['nb_erreurs'] ?? 0) . " | <strong>Warnings:</strong> " . ($report['nb_avertissements'] ?? 0) . "</p>";
        
        // Add execution context
        if (isset($report['context'])) {
            $ctx = $report['context'];
            $html .= "<details open><summary><strong>📍 Execution Context</strong></summary>";
            $html .= "<ul style='margin: 10px 0;'>";
            $html .= "<li><strong>File:</strong> <code>" . htmlspecialchars($ctx['file_relative'] ?? $ctx['file']) . "</code></li>";
            $html .= "<li><strong>Line:</strong> " . $ctx['line'] . "</li>";
            
            if ($ctx['class']) {
                $html .= "<li><strong>Method:</strong> <code>" . htmlspecialchars($ctx['class'] . $ctx['type'] . $ctx['function']) . "()</code></li>";
            } else {
                $html .= "<li><strong>Function:</strong> <code>" . htmlspecialchars($ctx['function']) . "()</code></li>";
            }
            
            if ($ctx['url']) {
                $html .= "<li><strong>URL:</strong> <a href='" . htmlspecialchars($ctx['url']) . "'>" . htmlspecialchars($ctx['url']) . "</a></li>";
                $html .= "<li><strong>Method:</strong> " . htmlspecialchars($ctx['method']) . "</li>";
            } else {
                $html .= "<li><strong>CLI Script:</strong> <code>" . htmlspecialchars($ctx['script_name'] ?? 'N/A') . "</code></li>";
            }
            
            $html .= "</ul></details>";
        }
        
        if (!empty($report['erreurs'])) {
            $html .= "<h3>❌ Errors:</h3><ul>";
            foreach ($report['erreurs'] as $error) {
                $html .= "<li style='color: #d32f2f;'>" . htmlspecialchars($error) . "</li>";
            }
            $html .= "</ul>";
        }
        
        if (!empty($report['avertissements'])) {
            $html .= "<h3>⚠️ Warnings:</h3><ul>";
            foreach ($report['avertissements'] as $warning) {
                $html .= "<li style='color: #f57c00;'>" . htmlspecialchars($warning) . "</li>";
            }
            $html .= "</ul>";
        }
        
        if (!empty($report['infos'])) {
            $html .= "<details><summary>ℹ️ Detailed Information</summary><ul>";
            foreach ($report['infos'] as $info) {
                $html .= "<li>" . htmlspecialchars($info) . "</li>";
            }
            $html .= "</ul></details>";
        }
        
        // Add secure query suggestion
        if (!empty($report['suggestion'])) {
            $html .= "<details open><summary><strong>💡 Secure Query Suggestion</strong></summary>";
            $html .= "<div style='background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50; margin: 10px 0;'>";
            
            if (isset($report['suggestion']['query'])) {
                $html .= "<p><strong>SQL:</strong></p>";
                $html .= "<pre style='background: white; padding: 10px; overflow-x: auto;'><code>" . htmlspecialchars($report['suggestion']['query']) . "</code></pre>";
            }
            
            if (isset($report['suggestion']['code'])) {
                $html .= "<p><strong>PHP Code Example:</strong></p>";
                $html .= "<pre style='background: white; padding: 10px; overflow-x: auto;'><code>" . htmlspecialchars($report['suggestion']['code']) . "</code></pre>";
            }
            
            if (isset($report['suggestion']['explanation'])) {
                $html .= "<p><strong>Explanation:</strong></p>";
                $html .= "<p>" . nl2br(htmlspecialchars($report['suggestion']['explanation'])) . "</p>";
            }
            
            $html .= "</div></details>";
        }
        
        $html .= "</div>";
        
        return $html;
    }
}
