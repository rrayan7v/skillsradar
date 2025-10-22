<?php
// survey/get_survey_data.php
include '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    http_response_code(403);
    exit;
}

$user_id = $_SESSION['user_id'];
$survey_id = intval($_GET['id']);

// Get survey data
$stmt = $conn->prepare("SELECT title, group_id FROM surveys WHERE id = ? AND created_by = ?");
$stmt->bind_param('ii', $survey_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$survey = $result->fetch_assoc();

if (!$survey) {
    http_response_code(404);
    exit;
}

$stmt = $conn->prepare("
    SELECT q.question_text, q.question_type 
    FROM survey_questions sq 
    JOIN questions q ON sq.question_id = q.id 
    WHERE sq.survey_id = ? 
    ORDER BY sq.id
");
$stmt->bind_param('i', $survey_id);
$stmt->execute();
$result = $stmt->get_result();
$questions = [];
while ($q = $result->fetch_assoc()) {
    $questions[] = $q;
}

$survey['questions'] = $questions;

header('Content-Type: application/json');
echo json_encode($survey);
?>
