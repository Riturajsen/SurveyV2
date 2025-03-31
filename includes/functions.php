<?php
// Start session if not already started with secure settings
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']), // Secure only if HTTPS is enabled
        'cookie_samesite' => 'Strict'
    ]);
}


// --- Add these functions inside includes/functions.php ---

/**
 * Generates and stores a CSRF token in the session if one doesn't exist.
 * Returns the current token.
 *
 * @return string The CSRF token.
 */
function generate_csrf_token() {
    // Ensure session is started (should already be by the top of functions.php)
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Generate token if it doesn't exist or is empty
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            // Handle error if random_bytes fails (highly unlikely)
            error_log("Failed to generate CSRF token: " . $e->getMessage());
            // Fallback or die - critical failure
            $_SESSION['csrf_token'] = 'fallback_token_' . microtime(); // Basic fallback
        }
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a submitted CSRF token against the one stored in the session.
 * Uses hash_equals for timing-attack safe comparison.
 *
 * @param string|null $submitted_token The token received from the form submission.
 * @return bool True if the token is valid, false otherwise.
 */
function validate_csrf_token($submitted_token) {
    // Ensure session is started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Check if session token and submitted token exist and are strings
    if (empty($submitted_token) || !is_string($submitted_token) ||
        empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        error_log("CSRF Validation Failure: Tokens missing or not strings.");
        return false;
    }

    $valid = hash_equals($_SESSION['csrf_token'], $submitted_token);

    if (!$valid) {
        error_log("CSRF Validation Failure: Submitted token does not match session token.");
    }

    // Optional: Unset the token after successful validation to prevent reuse.
    // Be cautious if your application relies on multiple forms open simultaneously
    // or heavy use of the back button without page reloads.
    // if ($valid) {
    //    unset($_SESSION['csrf_token']);
    // }

    return $valid;
}

// --- Existing functions (require_admin_login, escape_html, etc.) below ---



/**
 * Checks if the admin user is currently logged in.
 */
function require_admin_login($redirectPath = 'login.php') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: $redirectPath?error=login_required");
        exit;
    }
    session_regenerate_id(true); // Prevent session fixation attacks
}

/**
 * Escapes HTML special characters for safe output.
 */
function escape_html($input) {
    if (is_array($input)) {
        return array_map('escape_html', $input); // Recursively escape arrays
    }
    return htmlspecialchars($input ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generates a URL-friendly slug from a string.
 */
function generate_slug($text, $maxLength = 100) {
    // Convert to lowercase
    $text = strtolower($text);
    
    // Remove accents / diacritics if the intl extension is available
    if (function_exists('transliterator_transliterate')) {
         $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
    }
    
    // Replace non-alphanumeric characters with hyphens
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    
    // Remove duplicate hyphens and trim
    $text = preg_replace('/-+/', '-', trim($text, '-'));
    
    // Limit length of slug
    $text = substr($text, 0, $maxLength);
    
    // Ensure non-empty slug fallback
    if (empty($text)) {
        return 'slug-' . uniqid();
    }

    return $text;
}

/**
 * Checks if a slug already exists in the surveys table.
 * Optionally excludes a specific survey ID (for updates).
 */
function slug_exists($pdo, $slug, $exclude_survey_id = null) {
    $sql = "SELECT EXISTS(SELECT 1 FROM surveys WHERE unique_slug = ? LIMIT 1)";
    $params = [$slug];
    
    if ($exclude_survey_id !== null) {
        $sql = "SELECT EXISTS(SELECT 1 FROM surveys WHERE unique_slug = ? AND survey_id != ? LIMIT 1)";
        $params[] = $exclude_survey_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}
?>
