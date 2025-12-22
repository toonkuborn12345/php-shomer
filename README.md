# 🛡️ PHP Shomer (שומר)

<p align="center">
  <img src="https://img.shields.io/packagist/v/yd-shomer/php-shomer.svg" alt="Version">
  <img src="https://img.shields.io/packagist/dt/yd-shomer/php-shomer.svg" alt="Downloads">
  <img src="https://img.shields.io/packagist/l/yd-shomer/php-shomer.svg" alt="License">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-blue.svg" alt="PHP Version">
</p>

> **Shomer** (שומר) means "Guardian" in Hebrew

**Your SQL Query Guardian** - Runtime validation and security for PHP development. Catch SQL errors and security issues before they reach production.

## 🎯 Why "Shomer"?

French citation : "Il ne dort ni ne sommeille le Gardien ..."

Just as a guardian protects and watches over, **Shomer** vigilantly protects your application by validating SQL queries during development, catching errors and security issues before they reach production.

In the Hebrew tradition, a *Shomer* (שומר) is a vigilant guardian who watches and protects. PHP Shomer applies this same vigilance to your SQL queries, acting as a silent guardian during development, ready to alert you at the slightest danger.

## ✨ Features

- 🛡️ **Guards** against SQL injection patterns
- 👁️ **Watches** for syntax errors  
- ⚔️ **Protects** with prepared statement validation
- 📧 **Alerts** via email for critical issues
- 🎓 **Teaches** best practices
- 💡 **Suggests** secure query fixes (verbose mode)
- 🚀 **Zero performance impact** in production
- 💯 **100% compatible** with PDO and MySQLi
- 📍 **Auto-captures execution context** (file, line, URL, function) for instant debugging

## 📦 Installation

```bash
composer require yd-shomer/php-shomer
```

## 🚀 Quick Start

```php
<?php
require 'vendor/autoload.php';

use Shomer\QueryValidator;

// Enable Shomer in development
define('SHOMER_ENABLED', true);

// Validate a prepared statement
$report = QueryValidator::validate([
    'sql' => "INSERT INTO users (name, email) VALUES (?, ?)",
    'params' => ['John Doe', 'john@example.com']
], true, true);

if ($report['status'] === 'error') {
    echo "⚠️ Query validation failed!\n";
    print_r($report['erreurs']);
}
```

### Disable in Production

```php
// Simply set to false - zero overhead!
define('SHOMER_ENABLED', false);
```

## 📚 Usage Examples

### Example 1: Validate Prepared Statement (PDO Style)

```php
use Shomer\QueryValidator;

$query = [
    'sql' => "SELECT * FROM users WHERE email = :email AND status = :status",
    'params' => [
        'email' => 'user@example.com',
        'status' => 'active'
    ]
];

$report = QueryValidator::validate($query, true, true);
```

### Example 2: Detect Parameter Mismatch

```php
$query = [
    'sql' => "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
    'params' => ['John', 'john@example.com'] // ⚠️ Missing parameter!
];

$report = QueryValidator::validate($query, true);

// Shomer will detect: "CRITICAL ERROR: Placeholder count (3) differs from parameter count (2)"
```

### Example 3: Detect SQL Injection Attempts

```php
$query = [
    'sql' => "SELECT * FROM users WHERE username = ?",
    'params' => ["admin' OR '1'='1"] // ⚠️ Injection attempt
];

$report = QueryValidator::validate($query, true, true);

// Shomer will warn: "SQL keyword 'OR' detected in parameter"
```

### Example 4: Classic Query (Non-Prepared) - Not Recommended

```php
$unsafeQuery = "SELECT * FROM users WHERE id = " . $_GET['id'];

$report = QueryValidator::validate($unsafeQuery, true);

// Shomer will alert: "SECURITY WARNING: Non-prepared query detected"
```

### Example 5: Email Notifications

```php
// Send error reports via email
$report = QueryValidator::validate($query, true, true, 1);

// Or use custom email function
$report = QueryValidator::validate($query, true, true, 'my_custom_email_function');
```

## 🔧 Configuration

### Basic Configuration

```php
<?php
// config.php

// Enable/disable Shomer
define('SHOMER_ENABLED', true); // false in production

// Email configuration (optional)
define('SHOMER_EMAIL', 'dev@example.com');
define('SHOMER_EMAIL_FROM', 'noreply@example.com');
```

### Advanced Usage

```php
use Shomer\QueryValidator;
use Shomer\Reports\ValidationReport;

// Validate with full details
$report = QueryValidator::validate(
    $query,        // Query array or string
    true,          // Enable validation
    true,          // Verbose mode (all details)
    'send_alert'   // Custom email function
);

// Access report data
if ($report['nb_erreurs'] > 0) {
    foreach ($report['erreurs'] as $error) {
        error_log("Shomer Alert: $error");
    }
}

if ($report['nb_avertissements'] > 0) {
    foreach ($report['avertissements'] as $warning) {
        error_log("Shomer Warning: $warning");
    }
}
```

## 🎓 What Shomer Validates

### Prepared Statements
- ✅ Placeholder count matches parameter count
- ✅ No mixing of `?` and `:named` placeholders
- ✅ No hardcoded values in prepared queries
- ✅ No unescaped PHP variables in query string

### Syntax Validation
- ✅ Balanced parentheses
- ✅ Balanced quotes (single and double)
- ✅ Proper INSERT field/value count matching
- ✅ WHERE clause presence in UPDATE/DELETE

### Security Checks
- ✅ SQL injection pattern detection
- ✅ Dangerous SQL keywords in parameters
- ✅ Superglobal variables in queries
- ✅ Unescaped user input

### Best Practices
- ✅ Encourages prepared statements over raw queries
- ✅ Warns about `SELECT *` usage
- ✅ Detects missing WHERE in UPDATE/DELETE
- ✅ Educational error messages

## 📊 Validation Report Structure

```php
[
    'status' => 'error',           // 'success', 'error', or 'bypassed'
    'is_prepared' => true,         // Is it a prepared statement?
    'query' => 'SELECT ...',       // The SQL query
    'params' => [...],             // Parameters (if prepared)
    'erreurs' => [...],            // Array of errors
    'avertissements' => [...],     // Array of warnings
    'infos' => [...],              // Detailed information (if verbose)
    'nb_erreurs' => 2,             // Error count
    'nb_avertissements' => 1,      // Warning count
    'timestamp' => '2025-01-15 14:30:00',
    'context' => [                 // Execution context (NEW!)
        'file' => '/path/to/script.php',           // Absolute path
        'file_relative' => './src/UserService.php', // Relative path
        'line' => 42,                               // Line number
        'function' => 'validateUser',               // Function name
        'class' => 'App\\UserService',              // Class name (if method)
        'type' => '->',                             // Method type (-> or ::)
        'url' => 'https://example.com/login',       // URL (web context)
        'method' => 'POST',                         // HTTP method
        'script_name' => '/var/www/public/index.php' // Script path
    ]
]
```

### 📍 Execution Context (Auto-Captured)

Shomer automatically captures where the query validation was called:

- **File & Line**: Exact location in your code
- **Function/Method**: Which function or method called the validation
- **URL**: The request URL (in web context)
- **HTTP Method**: GET, POST, etc. (in web context)
- **Script**: The CLI script path (in CLI context)

This means **debugging is instant** - no need to search through your codebase! The error report comes "ready to use" with all the information you need.

**Example email alert:**
```
📍 EXECUTION CONTEXT:
───────────────────────────────────────────────────
📄 File: ./src/Services/UserService.php
📍 Line: 42
🔧 Method: App\Services\UserService->validateLogin()
🌐 URL: https://example.com/api/login
📝 Method: POST
───────────────────────────────────────────────────
```

### 💡 Secure Query Suggestions (Verbose Mode)

When verbose mode is enabled, Shomer doesn't just tell you what's wrong—it **shows you how to fix it**!

For each detected issue, Shomer provides:
- ✅ **Secure SQL** - The corrected query
- ✅ **PHP Code Example** - Ready-to-use implementation
- ✅ **Explanation** - Why this approach is better

**Example:**

```php
// Your problematic query
$query = "DELETE FROM users"; // Missing WHERE clause!

$report = QueryValidator::validate($query, true, true); // verbose = true

// Shomer provides a suggestion:
$report['suggestion'] = [
    'query' => 'DELETE FROM users WHERE id = ?',
    'code' => '$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);',
    'explanation' => 'CRITICAL: DELETE without WHERE clause will affect ALL rows...'
];
```

**Suggestions are provided for:**
- Non-prepared queries → Convert to prepared statements
- Parameter count mismatches → Fix parameter arrays
- Missing WHERE clauses → Add proper conditions
- SELECT * usage → Specify columns explicitly
- Hardcoded values → Use placeholders
- Field count errors → Match fields and values

This makes Shomer not just a validator, but a **teaching tool** that helps you learn secure SQL practices!

## 🔒 Security Note

**Shomer is a development tool**, not a replacement for proper security practices:

✅ **DO**: Use Shomer during development to catch issues early  
✅ **DO**: Always use prepared statements in production  
✅ **DO**: Disable Shomer in production (`SHOMER_ENABLED = false`)  
✅ **DO**: Validate and sanitize user input  

❌ **DON'T**: Rely solely on Shomer for production security  
❌ **DON'T**: Use raw SQL queries in production  
❌ **DON'T**: Trust user input without validation  

## 🚀 Performance

**Development Mode** (`SHOMER_ENABLED = true`):
- Full validation and analysis
- Detailed error reporting
- ~0.001-0.005 seconds per query

**Production Mode** (`SHOMER_ENABLED = false`):
- Instant bypass with single condition check
- ~0.0000002 seconds per query (negligible)
- Zero memory overhead

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Inspired by the Hebrew concept of *Shomer* (שומר) - Guardian
- Built with ❤️ for the PHP community
- Special thanks to all contributors

## 📞 Support

- 📖 [Documentation](https://github.com/yd-shomer/php-shomer/wiki)
- 🐛 [Issue Tracker](https://github.com/yd-shomer/php-shomer/issues)
- 💬 [Discussions](https://github.com/yd-shomer/php-shomer/discussions)

---

<p align="center">
  <strong>Shomer: Because your database deserves a guardian.</strong><br>
  שומר - Protecting your queries, one validation at a time.
</p>
