<?php
// --- admin/view_responses.php ---

require_once '../includes/functions.php'; // Includes session_start() and escape_html()
require_admin_login(); // Check if admin is logged in
require_once '../includes/db_connect.php'; // Needs $pdo

// --- Get Surveys for Dropdown ---
$available_surveys = [];
$fetch_error = null; // To store errors during data fetching
try {
    // Fetch only survey ID and title for the dropdown
    $stmt_surveys = $pdo->query("SELECT survey_id, title FROM surveys ORDER BY title ASC");
    $available_surveys = $stmt_surveys->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching surveys list for report: " . $e->getMessage());
    $available_surveys = []; // Ensure it's an array even on error
    $fetch_error = "Could not load the list of available surveys.";
}

// --- Get Selected Survey ID and Filter Dates from GET parameters ---
$selected_survey_id = filter_input(INPUT_GET, 'survey_id', FILTER_VALIDATE_INT);
// Get date filters and escape them for safe echoing back into form values
$start_date_val = isset($_GET['start_date']) ? escape_html(trim($_GET['start_date'])) : '';
$end_date_val = isset($_GET['end_date']) ? escape_html(trim($_GET['end_date'])) : '';

// --- Initialize Variables for report data ---
$survey_title = "Select a Survey";
$survey_title_raw = null; // Store unescaped title for potential use
$chart_data = []; // Data formatted for Chart.js
$chart_data_json = '[]'; // Default to empty JSON array
$raw_responses_by_question = []; // Array to hold text/textarea responses grouped by question
$all_survey_responses_raw = []; // Array to hold all raw response rows for the table display
$survey_questions_grouped = []; // Holds question details keyed by question_id


// --- Process if a Survey is Selected ---
if ($selected_survey_id) {
    try {
        // Get the title of the selected survey
        $stmt_title = $pdo->prepare("SELECT title FROM surveys WHERE survey_id = ?");
        $stmt_title->execute([$selected_survey_id]);
        $survey_title_raw = $stmt_title->fetchColumn();

        if (!$survey_title_raw) {
             $fetch_error = "Selected survey (ID: $selected_survey_id) not found.";
             $selected_survey_id = null; // Reset selection as it's invalid
        } else {
             $survey_title = "Results for: " . escape_html($survey_title_raw); // Title for display

            // --- Build the SQL Query for fetching responses, including date filters ---
            // Base query selects necessary columns from responses table
            $sql_responses = "SELECT r.question_id, r.response_value, r.respondent_email, r.submitted_at
                              FROM responses r
                              WHERE r.survey_id = ? "; // Always filter by the selected survey_id

            $params = [$selected_survey_id]; // Parameters for prepared statement start with survey_id

            $date_filter_applied = false; // Flag to check if filtering is active

            // Add start date condition if provided and valid
            if (!empty($start_date_val)) {
                // Validate YYYY-MM-DD format
                if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $start_date_val)) {
                    $sql_responses .= " AND r.submitted_at >= ? ";
                    $params[] = $start_date_val . ' 00:00:00'; // Include the whole start day
                    $date_filter_applied = true;
                } else {
                    $fetch_error = ($fetch_error ? $fetch_error . ' ' : '') . "Invalid start date format (YYYY-MM-DD).";
                    // Optionally clear the invalid date value for the form
                    // $start_date_val = '';
                }
            }

            // Add end date condition if provided and valid
            if (!empty($end_date_val)) {
                 if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $end_date_val)) {
                    $sql_responses .= " AND r.submitted_at <= ? ";
                    $params[] = $end_date_val . ' 23:59:59'; // Include the whole end day
                    $date_filter_applied = true;
                } else {
                     $fetch_error = ($fetch_error ? $fetch_error . ' ' : '') . "Invalid end date format (YYYY-MM-DD).";
                     // $end_date_val = '';
                }
            }

            // Add ordering
            $sql_responses .= " ORDER BY r.submitted_at DESC"; // Order responses, adjust as needed

            // --- Execute Query and Fetch Data (only if no date format errors) ---
            if (strpos($fetch_error ?? '', 'Invalid date format') === false) {

                $stmt_r = $pdo->prepare($sql_responses);
                $stmt_r->execute($params);
                $all_survey_responses_raw = $stmt_r->fetchAll(PDO::FETCH_ASSOC); // Get all (filtered) raw responses

                // --- Fetch Questions for this Survey (Needed for Aggregation/Display) ---
                $stmt_q = $pdo->prepare("SELECT question_id, question_text, question_type FROM questions WHERE survey_id = ? ORDER BY question_id ASC");
                $stmt_q->execute([$selected_survey_id]);
                // Store question details keyed by question_id for easy lookup
                while ($row = $stmt_q->fetch(PDO::FETCH_ASSOC)) {
                    $survey_questions_grouped[$row['question_id']] = $row;
                }

                // --- Aggregate Data for Charts & Text Responses ---
                if (!empty($survey_questions_grouped)) {
                     foreach ($survey_questions_grouped as $qid => $q_details) {
                         $q_type = $q_details['question_type'];
                         $q_text = $q_details['question_text'];

                         // Aggregate chartable types (radio, select, checkbox)
                         if (in_array($q_type, ['radio', 'select', 'checkbox'])) {
                             $counts = []; // Counts for options of this question
                             foreach ($all_survey_responses_raw as $resp) { // Use filtered responses
                                 if ($resp['question_id'] == $qid) {
                                     // Handle potential comma-separated values from checkboxes
                                     $answers = explode(',', $resp['response_value']);
                                     foreach ($answers as $ans) {
                                         $ans_trimmed = trim($ans);
                                         if (!empty($ans_trimmed)) {
                                             $counts[$ans_trimmed] = ($counts[$ans_trimmed] ?? 0) + 1;
                                         }
                                     }
                                 }
                             }
                             // If we found counts, prepare data structure for Chart.js
                             if (!empty($counts)) {
                                 arsort($counts); // Sort options by count, descending
                                 $chart_data[$qid] = [
                                     'question_text' => $q_text,
                                     'type' => ($q_type === 'checkbox' || count($counts) > 7) ? 'bar' : 'pie', // Heuristic
                                     'labels' => array_keys($counts),
                                     'data' => array_values($counts)
                                 ];
                             }
                         } else { // Handle text/textarea responses (store raw)
                             $raw_responses_by_question[$qid]['question_text'] = $q_text;
                             $raw_responses_by_question[$qid]['responses'] = []; // Initialize
                             foreach ($all_survey_responses_raw as $resp) { // Use filtered responses
                                  if ($resp['question_id'] == $qid) {
                                       $raw_responses_by_question[$qid]['responses'][] = $resp['response_value'];
                                  }
                             }
                         } // End if/else for question type
                     } // End looping through questions
                } // End if survey_questions_grouped not empty

                // Encode the final chart data into JSON for JavaScript
                $chart_data_json = json_encode($chart_data);

            } // end if no date format errors
        } // end if survey title found
    } catch (PDOException $e) {
        error_log("Error fetching report data for survey ID $selected_survey_id: " . $e->getMessage());
        $fetch_error = "Could not retrieve report data due to a database error.";
    }
} // end if selected_survey_id

// --- Start HTML Output ---
include 'partials/header.php'; // Include header partial
?>

<h2>View Survey Responses</h2>

<form method="GET" action="view_responses.php" class="filter-form" style="padding: 15px; background:#f8f9fa; border:1px solid #eee; margin-bottom:20px; display:flex; align-items:flex-end; gap: 15px; flex-wrap:wrap;">
    <div>
        <label for="survey_id_select" style="display:block; margin-bottom:5px;">Select Survey:</label>
        <select name="survey_id" id="survey_id_select" required>
            <option value="">-- Choose a Survey --</option>
            <?php foreach ($available_surveys as $s): ?>
                <option value="<?php echo $s['survey_id']; ?>" <?php echo ($selected_survey_id == $s['survey_id']) ? 'selected' : ''; ?>>
                    <?php echo escape_html($s['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="start_date" style="display:block; margin-bottom:5px;">Start Date:</label>
        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date_val; ?>" style="padding: 5px;" max="<?php echo date('Y-m-d'); // Prevent future dates ?>">
    </div>

    <div>
        <label for="end_date" style="display:block; margin-bottom:5px;">End Date:</label>
        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date_val; ?>" style="padding: 5px;" max="<?php echo date('Y-m-d'); // Prevent future dates ?>">
    </div>

    <div>
        <button type="submit" class="button filter-button" style="padding: 7px 15px;">Apply Filters</button>
        <?php if ($selected_survey_id): // Show clear dates link only if a survey is selected ?>
             <a href="view_responses.php?survey_id=<?php echo $selected_survey_id; ?>" class="button cancel" style="padding: 7px 15px;">Clear Dates</a>
        <?php endif; ?>
    </div>
</form>
<hr>

<?php if (isset($fetch_error)): ?>
    <p class="message error"><?php echo $fetch_error; ?></p>
<?php endif; ?>


<?php if ($selected_survey_id && !$fetch_error): // Only show results if a valid survey is selected and no critical fetch error occurred ?>
    <h3><?php echo $survey_title; // Already escaped ?></h3>
    <?php if ($date_filter_applied): // Show indicator if dates were used ?>
        <p style="font-style: italic; color: #555;">
            Showing responses submitted
            <?php if (!empty($start_date_val)) echo " from " . $start_date_val; ?>
            <?php if (!empty($end_date_val)) echo (!empty($start_date_val) ? " through " : " up to ") . $end_date_val; ?>.
        </p>
    <?php endif; ?>


    <h4>Summary Charts</h4>
    <?php if (!empty($chart_data)): ?>
        <div id="charts-container">
            <?php foreach ($chart_data as $qid => $data): ?>
                <div class="chart-block">
                    <h5><?php echo escape_html($data['question_text']); ?></h5>
                    <canvas id="chart-<?php echo $qid; ?>" style="max-width: 600px; max-height: 350px; width: 100%;"></canvas>
                </div>
                 <hr class="chart-divider">
            <?php endforeach; ?>
        </div>
    <?php else: ?>
         <p>No chartable responses found<?php echo ($date_filter_applied) ? ' for the selected date range' : ''; ?>.</p>
    <?php endif; ?>


    <?php if (!empty($raw_responses_by_question)): ?>
         <h4 style="margin-top: 30px;">Text Responses</h4>
        <?php foreach ($raw_responses_by_question as $qid => $data): ?>
             <div class="raw-response-block">
                  <h5><?php echo escape_html($data['question_text']); ?></h5>
                  <?php if(!empty($data['responses'])): ?>
                  <ul>
                       <?php foreach ($data['responses'] as $response): ?>
                           <li><?php echo nl2br(escape_html($response)); ?></li>
                       <?php endforeach; ?>
                  </ul>
                  <?php else: ?>
                  <p><small>No text responses submitted for this question<?php echo ($date_filter_applied) ? ' in the selected date range' : ''; ?>.</small></p>
                  <?php endif; ?>
             </div>
             <hr>
        <?php endforeach; ?>
    <?php endif; ?>


    <h4 style="margin-top: 30px;">All Submitted Responses<?php echo ($date_filter_applied) ? ' (Filtered)' : ''; ?></h4>
    <?php if (!empty($all_survey_responses_raw)):
        // Prepare question texts map if needed (should be available from $survey_questions_grouped)
        $question_texts_map = [];
        if (!empty($survey_questions_grouped)) {
             foreach($survey_questions_grouped as $qid => $details) { $question_texts_map[$qid] = $details['question_text']; }
        }
    ?>
        <div style="overflow-x:auto;"> <table class="admin-table raw-responses">
                 <thead>
                     <tr>
                         <th>Question</th>
                         <th>Response</th>
                         <th>Respondent Email</th>
                         <th>Submitted At</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php foreach($all_survey_responses_raw as $raw_resp): ?>
                         <tr>
                             <td><?php echo escape_html($question_texts_map[$raw_resp['question_id']] ?? 'Unknown QID: '.$raw_resp['question_id']); ?></td>
                             <td><?php echo nl2br(escape_html($raw_resp['response_value'])); ?></td>
                             <td><?php echo escape_html($raw_resp['respondent_email'] ?? 'N/A'); ?></td>
                             <td><?php echo escape_html($raw_resp['submitted_at']); // Consider formatting date nicer ?></td>
                         </tr>
                     <?php endforeach; ?>
                 </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No responses have been submitted for this survey<?php echo ($date_filter_applied) ? ' in the selected date range' : ''; ?>.</p>
    <?php endif; ?>

<?php elseif(!$selected_survey_id && empty($fetch_error)): // Prompt to select survey ?>
    <p>Please select a survey from the dropdown above to view its results.</p>
<?php endif; // End main results display block ?>


<script>
    // Get the chart data prepared by PHP
    const chartJsonData = <?php echo $chart_data_json; // Ensure this is outputting correctly ?>;

    // Function to generate a basic color palette
    function generateColors(count) {
        const colors = [ // Predefined palette
            'rgba(54, 162, 235, 0.7)','rgba(255, 99, 132, 0.7)','rgba(75, 192, 192, 0.7)',
            'rgba(255, 206, 86, 0.7)','rgba(153, 102, 255, 0.7)','rgba(255, 159, 64, 0.7)',
            'rgba(100, 149, 237, 0.7)','rgba(218, 112, 214, 0.7)','rgba(60, 179, 113, 0.7)',
            'rgba(255, 127, 80, 0.7)'
        ];
        const result = [];
        for (let i = 0; i < count; i++) { result.push(colors[i % colors.length]); }
        return result;
    }

    // Wait for the DOM to be ready
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Chart === 'undefined') {
             console.error("Chart.js library not loaded!");
             return;
        }
        //console.log("Chart data received:", chartJsonData); // Uncomment for debugging

        for (const qid in chartJsonData) {
            if (chartJsonData.hasOwnProperty(qid)) {
                const question = chartJsonData[qid];
                const canvasElement = document.getElementById(`chart-${qid}`);
                if (!canvasElement) {
                    console.error(`Canvas element with ID 'chart-${qid}' not found.`);
                    continue;
                }
                const ctx = canvasElement.getContext('2d');
                const backgroundColors = generateColors(question.data.length);
                const borderColors = backgroundColors.map(color => color.replace('0.7', '1.0'));

                try {
                    new Chart(ctx, {
                        type: question.type,
                        data: {
                            labels: question.labels,
                            datasets: [{
                                label: '# of Responses',
                                data: question.data,
                                backgroundColor: backgroundColors,
                                borderColor: borderColors,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'top', display: question.type === 'pie' || question.labels.length <= 10 },
                                title: { display: false }
                            },
                            scales: question.type === 'bar' ? {
                                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
                            } : {},
                        }
                    });
                 } catch (error) {
                     console.error(`Error creating chart for QID ${qid}:`, error);
                     if(canvasElement.parentNode) { // Display error near canvas
                        const errorP = document.createElement('p');
                        errorP.textContent = 'Error rendering chart.';
                        errorP.style.color = 'red';
                        canvasElement.parentNode.insertBefore(errorP, canvasElement.nextSibling);
                     }
                 }
            }
        }
    });
</script>

<?php
// --- End HTML Output ---
include 'partials/footer.php'; // Include footer partial
?>