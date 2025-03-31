<?php
// --- survey.php ---
// Located in the project root directory

// Include necessary files (paths relative to root)
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // Contains escape_html()

// Get the unique survey slug from the URL parameter 's'
$slug = trim($_GET['s'] ?? '');

// Basic validation for the slug
if (empty($slug)) {
    // Optionally redirect to homepage or show a more specific error
    die("Invalid survey link provided.");
}

// Initialize variables
$survey = null;
$questions = [];
$error_message = null; // To store potential fetch errors

// --- Fetch Survey and Question Data ---
try {
    // Prepare to fetch the specific survey details, ensuring it's active
    $stmt_survey = $pdo->prepare("SELECT survey_id, title, description FROM surveys WHERE unique_slug = ? AND is_active = 1");
    $stmt_survey->execute([$slug]);
    $survey = $stmt_survey->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC

    // If the survey was found and is active, fetch its questions
    if ($survey) {
        $stmt_questions = $pdo->prepare("SELECT question_id, question_text, question_type, options, is_required FROM questions WHERE survey_id = ? ORDER BY question_id ASC");
        $stmt_questions->execute([$survey['survey_id']]);
        $questions = $stmt_questions->fetchAll(PDO::FETCH_ASSOC); // Use FETCH_ASSOC
    }

} catch (PDOException $e) {
    // Log the database error for admin/debugging purposes
    error_log("Error fetching survey data for slug '$slug': " . $e->getMessage());
    // Set a user-friendly error message
    $error_message = "An error occurred while trying to load the survey. Please try again later.";
}

// --- Start HTML Output ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survey: <?php echo escape_html($survey['title'] ?? 'Survey'); ?></title>
    <meta name="description" content="<?php echo escape_html($survey['description'] ?? 'Take this survey!'); ?>">
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Quick styles - better to put in style.css */
        .survey-container { max-width: 800px; margin: 20px auto; padding: 25px; background:#fff; border:1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .required-indicator { color: #dc3545; font-weight: bold; margin-left: 3px; display: inline-block; }
        .error-message-fetch { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .survey-description { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="survey-container">

        <?php
        // Handle case where survey wasn't found or wasn't active
        if (!$survey && !$error_message) {
             header("HTTP/1.0 404 Not Found"); // Set appropriate HTTP status
             echo "<h1>Survey Not Found</h1>";
             echo "<p>Sorry, the survey you are looking for might have been moved, deleted, or is no longer active.</p>";
             echo "<p><a href='index.php'>Return to Available Surveys</a></p>"; // Link back to root index
        }
        // Handle database fetch errors
        elseif ($error_message) {
             echo "<h1>Error</h1>";
             echo "<p class='error-message-fetch'>" . escape_html($error_message) . "</p>";
        }
        // Display survey if found
        elseif ($survey) { // Start main survey display block
        ?>
            <h1><?php echo escape_html($survey['title']); ?></h1>
            <?php if (!empty($survey['description'])) { ?>
                <p class="survey-description"><?php echo nl2br(escape_html($survey['description'])); ?></p>
            <?php } ?>

             <?php if (isset($_GET['success'])) { ?>
                <p class="message success"><?php echo escape_html(urldecode($_GET['success'])); ?></p>
                 <p><a href="index.php">Return to Available Surveys</a></p>
            <?php } elseif (isset($_GET['error'])) { ?>
                <p class="message error"><?php echo escape_html(urldecode($_GET['error'])); ?></p>
            <?php } ?>


            <?php
            // Only show the form if there are questions AND no success message is shown
            if (!empty($questions) && !isset($_GET['success'])) { // Start form display block
            ?>
                <form action="submit_survey.php" method="post" class="survey-form">
                    <input type="hidden" name="survey_id" value="<?php echo $survey['survey_id']; ?>">
                    <input type="hidden" name="survey_slug" value="<?php echo escape_html($slug); ?>">

                    <fieldset class="question-group">
                        <legend>Your Email <span class="required-indicator">*</span></legend>
                        <input type="email" name="respondent_email" required class="form-input" placeholder="Required for confirmation">
                    </fieldset>

                    <?php foreach ($questions as $q) { // Start foreach loop
                        // Prepare variables
                        $q_id = $q['question_id'];
                        $input_name = "response[$q_id]";
                        $input_name_cb = "response[{$q_id}][]";
                        $is_required = (bool)$q['is_required'];
                        $required_attr = $is_required ? 'required' : '';
                        $required_indicator = $is_required ? '<span class="required-indicator">*</span>' : '';
                    ?>
                        <fieldset class="question-group">
                            <legend><?php echo escape_html($q['question_text']); ?> <?php echo $required_indicator; ?></legend>

                            <?php
                            // --- Render input based on question type ---
                            switch ($q['question_type']) { // Start switch
                                case 'text':
                                    echo "<input type='text' name='$input_name' $required_attr class='form-input'>";
                                    break;

                                case 'textarea':
                                    echo "<textarea name='$input_name' rows='4' $required_attr class='form-textarea'></textarea>";
                                    break;

                                case 'radio':
                                case 'select':
                                    $options = (!empty($q['options'])) ? explode(',', trim($q['options'])) : [];
                                    if (empty($options)) {
                                        echo "<p class='message error' style='font-size: 0.9em;'>Configuration error: No options found.</p>";
                                        break; // Break from switch case
                                    }

                                    if ($q['question_type'] == 'select') {
                                        echo "<select name='$input_name' $required_attr class='form-select'>";
                                        echo "<option value=''>-- Please Select --</option>";
                                        foreach ($options as $opt_raw) {
                                             $opt = trim($opt_raw);
                                             $opt_escaped = escape_html($opt);
                                             echo "<option value='$opt_escaped'>$opt_escaped</option>";
                                        } // End foreach options
                                        echo "</select>";
                                    } else { // Radio buttons
                                        foreach ($options as $opt_raw) {
                                             $opt = trim($opt_raw);
                                             $opt_escaped = escape_html($opt);
                                             echo "<div class='radio-option'><label>";
                                             echo "<input type='radio' name='$input_name' value='$opt_escaped' $required_attr> ";
                                             echo $opt_escaped;
                                             echo "</label></div>";
                                        } // End foreach options
                                    }
                                    break; // End radio/select case

                                case 'checkbox':
                                    $options = (!empty($q['options'])) ? explode(',', trim($q['options'])) : [];
                                     if (empty($options)) {
                                         echo "<p class='message error' style='font-size: 0.9em;'>Configuration error: No options found.</p>";
                                         break; // Break from switch case
                                     }
                                     echo "<div class='checkbox-group'>";
                                     foreach ($options as $opt_raw) {
                                         $opt = trim($opt_raw);
                                         $opt_escaped = escape_html($opt);
                                         echo "<div class='checkbox-option'><label>";
                                         echo "<input type='checkbox' name='$input_name_cb' value='$opt_escaped'> ";
                                         echo $opt_escaped;
                                         echo "</label></div>";
                                     } // End foreach options
                                     if ($is_required) {
                                          echo "<small style='display:block; margin-top:5px; color:#555;'> (At least one selection required)</small>";
                                     }
                                     echo "</div>";
                                    break; // End checkbox case

                                default:
                                    echo "<p><em>(Unsupported question type: " . escape_html($q['question_type']) . ")</em></p>";
                                    break;
                            } // End switch
                            ?>
                        </fieldset>
                    <?php } // End foreach loop ?>

                    <button type="submit" class="submit-button">Submit Your Responses</button>

                </form>
            <?php
            } // End form display block
            elseif (empty($questions) && !isset($_GET['success'])) { // Start no questions block
            ?>
                <p>This survey currently has no questions defined.</p>
                <p><a href='index.php'>Return to Available Surveys</a></p>
            <?php
            } // End no questions block
            ?>
        <?php
        } // End main survey display block
        ?>

    </div>
    </div> <footer class="public-footer" style="text-align: center; margin-top: 40px; padding: 20px 0; border-top: 1px solid var(--color-border); font-size: 0.9em; color: var(--color-secondary);">
    <p style="margin: 0;">
            Powered by <a href="https://theiqhub.com" target="_blank" title ="THEIQHUB" style="color: var(--color-link);">THEIQHUB</a>
            <br>
            <span style="font-size: 0.9em;">Survey System &copy; <?php echo date('Y'); ?></span>
        </p>
    </footer>
</body>
</html>