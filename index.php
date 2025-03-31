<?php
// Path Change: ../includes -> includes
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

try {
    $stmt = $pdo->query("SELECT survey_id, title, description, unique_slug FROM surveys WHERE is_active = 1 ORDER BY created_at DESC");
    $surveys = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching active surveys: " . $e->getMessage());
    $surveys = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Surveys</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <style> /* Card styles included for brevity, move to style.css */
        .survey-card-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
        .survey-card { border: 1px solid #ddd; padding: 20px; border-radius: 5px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
        .survey-card h3 { margin-top: 0; margin-bottom: 10px; font-size: 1.2em; color: #333; }
        .survey-card p { font-size: 0.95em; color: #555; flex-grow: 1; margin-bottom: 15px; }
        .survey-card a.button-take-survey { display: inline-block; margin-top: auto; padding: 10px 18px; background-color: #007bff; color: white; text-align: center; text-decoration: none; border-radius: 4px; transition: background-color 0.2s ease; }
        .survey-card a.button-take-survey:hover { background-color: #0056b3; color: white; }
        .no-surveys { text-align: center; color: #777; padding: 40px 20px; font-size: 1.1em; background-color: #f9f9f9; border: 1px dashed #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <div style="max-width: 1100px; margin: 20px auto; padding: 20px;">
        <h1>Available Surveys</h1>

        <?php if (!empty($surveys)): ?>
            <div class="survey-card-list">
                <?php foreach ($surveys as $survey): ?>
                    <div class="survey-card">
                        <h3><?php echo escape_html($survey['title']); ?></h3>
                        <?php if (!empty($survey['description'])): ?>
                            <p><?php echo nl2br(escape_html($survey['description'])); ?></p>
                        <?php endif; ?>
                        <a href="survey.php?s=<?php echo urlencode($survey['unique_slug']); ?>" class="button-take-survey">Take Survey</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="no-surveys">There are currently no active surveys available. Please check back later.</p>
        <?php endif; ?>
    </div>
</body>
</html>