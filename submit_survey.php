<?php
// --- submit_survey.php ---
// Located in the project root directory

// Start session if not already started (used for respondent_id)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files (paths relative to root)
require_once 'includes/db_connect.php'; // Needs $pdo
require_once 'includes/functions.php'; // Needs escape_html() for potential error messages

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); // Redirect to homepage if not POST
    exit;
}

// --- Get Submitted Data ---
$survey_id = filter_input(INPUT_POST, 'survey_id', FILTER_VALIDATE_INT);
$survey_slug = trim($_POST['survey_slug'] ?? ''); // For redirecting back
$respondent_email = filter_input(INPUT_POST, 'respondent_email', FILTER_VALIDATE_EMAIL); // Collect email
$responses = $_POST['response'] ?? []; // Array of responses keyed by question_id
$respondent_id = session_id(); // Optional: track session

// --- Basic Validation ---
if (!$survey_id || !$survey_slug) {
    // Should not happen with hidden fields, but check anyway
    die("Error: Invalid survey submission data.");
}
if (!$respondent_email) {
    // Email was required on the form, redirect if invalid/missing
    header('Location: survey.php?s=' . urlencode($survey_slug) . '&error=' . urlencode('A valid email address is required.'));
    exit;
}
if (empty($responses)) {
    // Handle case where form submitted with no answers (e.g., if JS validation failed)
    header('Location: survey.php?s=' . urlencode($survey_slug) . '&error=' . urlencode('Please answer at least one question.'));
    exit;
}

// --- Required Field Validation ---
try {
    // Fetch IDs and required status for all questions in this survey
    $stmt_req = $pdo->prepare("SELECT question_id, is_required FROM questions WHERE survey_id = ?");
    $stmt_req->execute([$survey_id]);
    $all_questions_status = $stmt_req->fetchAll(PDO::FETCH_KEY_PAIR); // [qid => is_required]

    // Check only those marked as required
    foreach ($all_questions_status as $qid => $is_required) {
        if ($is_required == 1) { // Check only if the question is actually required
            $is_answered = false;
            if (isset($responses[$qid])) {
                // Checkbox: needs at least one non-empty value in its array
                if (is_array($responses[$qid])) {
                    foreach ($responses[$qid] as $cb_val) {
                        if (trim($cb_val) !== '') {
                            $is_answered = true;
                            break; // Found one answered checkbox value
                        }
                    }
                }
                // Other input types: needs a non-empty trimmed value
                elseif (trim($responses[$qid]) !== '') {
                    $is_answered = true;
                }
            }

            // If a required question wasn't answered, get its text and redirect
            if (!$is_answered) {
                 $stmt_qtext = $pdo->prepare("SELECT question_text FROM questions WHERE question_id = ?");
                 $stmt_qtext->execute([$qid]);
                 $q_text = $stmt_qtext->fetchColumn();
                 $error_msg = "Required question is not answered: \"" . escape_html($q_text ?: 'Question #'.$qid) . "\"";
                 // Redirect back to the specific survey page with an error
                 header('Location: survey.php?s=' . urlencode($survey_slug) . '&error=' . urlencode($error_msg));
                 exit;
            }
        } // end if is_required
    } // end foreach loop checking required questions

} catch (PDOException $e) {
    error_log("Error checking required questions for survey $survey_id: " . $e->getMessage());
    header('Location: survey.php?s=' . urlencode($survey_slug) . '&error=' . urlencode('Error processing submission. Please try again.'));
    exit;
}


// --- Save Responses to Database ---
$pdo->beginTransaction();
try {
    // SQL includes the respondent_email column now
    $sql_response = "INSERT INTO responses (survey_id, question_id, response_value, respondent_id, respondent_email) VALUES (?, ?, ?, ?, ?)";
    $stmt_response = $pdo->prepare($sql_response);

    // Get list of valid question IDs *for this specific survey* to prevent data mismatch
    $stmt_valid_q = $pdo->prepare("SELECT question_id FROM questions WHERE survey_id = ?");
    $stmt_valid_q->execute([$survey_id]);
    $valid_qids = $stmt_valid_q->fetchAll(PDO::FETCH_COLUMN);

    // Loop through submitted responses
    foreach ($responses as $question_id => $value) {
        $qid_int = filter_var($question_id, FILTER_VALIDATE_INT);

        // Security/Integrity Check: Ensure question belongs to this survey
        if ($qid_int === false || !in_array($qid_int, $valid_qids)) {
            error_log("Warning: Attempted to submit response for invalid/mismatched question ID ($question_id) on survey $survey_id.");
            continue; // Skip this response
        }

        // Process response value (handle checkbox array vs single string)
        if (is_array($value)) {
            // Filter out empty checkbox values and join the rest
            $sanitized_values = array_filter(array_map('trim', $value), function($v){ return $v !== ''; });
            if (!empty($sanitized_values)) {
                 $response_value_str = implode(', ', $sanitized_values); // Store multiple checkbox answers comma-separated
            } else {
                 // If required validation passed, this shouldn't happen often.
                 // If optional and empty, skip inserting.
                 if (!isset($all_questions_status[$qid_int]) || $all_questions_status[$qid_int] == 0) {
                     continue;
                 } else {
                     // Should have been caught by required validation, but insert empty string if needed
                     $response_value_str = '';
                 }
            }
        } else {
            // Trim single string responses
            $response_value_str = trim($value);
            // Skip empty optional answers
             if ($response_value_str === '' && isset($all_questions_status[$qid_int]) && $all_questions_status[$qid_int] == 0) {
                 continue;
             }
        }

        // Execute the prepared statement for this response
        // Includes respondent_email on every row associated with this submission
        $stmt_response->execute([
            $survey_id,
            $qid_int,
            $response_value_str,
            $respondent_id,
            $respondent_email // Save the collected email
        ]);
    } // End loop through responses

    // If all response inserts succeeded, commit the transaction
    $pdo->commit();

    // --- NO Email Sending / Queueing ---
    // The logic previously here for mail() or email_queue insert is removed.

    // --- Redirect User on Success---
    // Redirect back to the survey page they came from with a success message.
    header('Location: survey.php?s=' . urlencode($survey_slug) . '&success=' . urlencode('Thank you! Your responses have been submitted.'));
    exit;

} catch (PDOException $e) { // Catch errors from the response saving block
    // If any database error occurred during the transaction, roll back
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Log the error for admin
    error_log("Error saving responses for survey $survey_id: " . $e->getMessage());
    // Redirect user back with a generic error
    header('Location: survey.php?s=' . urlencode($survey_slug) . '&error=' . urlencode('An error occurred while saving your responses. Please try again.'));
    exit;
}

?> // End of PHP script