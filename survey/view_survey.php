<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$role = 'student';
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $role = $row['role'];
}
$stmt->close();

$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$feedbackNotice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'teacher') {
    $postSurveyId = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : 0;
    $responseId = isset($_POST['response_id']) ? intval($_POST['response_id']) : 0;
    $action = $_POST['feedback_action'] ?? 'save';

    if ($postSurveyId !== $survey_id || $responseId <= 0) {
        $feedbackNotice = ['type' => 'error', 'message' => 'Ongeldige feedbackaanvraag.'];
    } else {
        $feedbackText = isset($_POST['teacher_feedback']) ? trim($_POST['teacher_feedback']) : '';

        if ($action === 'save') {
            if ($feedbackText === '') {
                $stmt = $conn->prepare("UPDATE responses SET teacher_feedback = NULL, feedback_by = NULL, feedback_at = NULL WHERE id = ? AND survey_id = ?");
                $stmt->bind_param("ii", $responseId, $survey_id);
            } else {
                $stmt = $conn->prepare("UPDATE responses SET teacher_feedback = ?, feedback_by = ?, feedback_at = NOW() WHERE id = ? AND survey_id = ?");
                $stmt->bind_param("siii", $feedbackText, $user_id, $responseId, $survey_id);
            }

            if ($stmt && $stmt->execute()) {
                $anchor = isset($_POST['redirect_anchor']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['redirect_anchor']) : '';
                $_SESSION['feedback_notice'] = [
                    'type' => $feedbackText === '' ? 'info' : 'success',
                    'message' => $feedbackText === '' ? 'Feedback verwijderd.' : 'Feedback opgeslagen.'
                ];
                $stmt->close();
                $redirectUrl = 'view_survey.php?id=' . $survey_id;
                if ($anchor !== '') {
                    $redirectUrl .= '#' . $anchor;
                }
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                $feedbackNotice = ['type' => 'error', 'message' => 'Opslaan van feedback is mislukt. Probeer het opnieuw.'];
            }

            if ($stmt) {
                $stmt->close();
            }
        }
    }
}

if ($feedbackNotice === null && isset($_SESSION['feedback_notice'])) {
    $feedbackNotice = $_SESSION['feedback_notice'];
    unset($_SESSION['feedback_notice']);
}

?>

<style>
/* Added comprehensive styling matching blue theme from groups.php */
body{background:#f5f7fb;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}
.results-container{max-width:1400px;margin:2em auto;padding:0 2em;}
.page-header{background:#fff;padding:2em;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);margin-bottom:2em;}
.page-header h2{color:#0f63d4;margin:0 0 0.5em 0;font-size:2em;}
.page-header p{color:#6b7280;margin:0;}

/* Survey selection grid */
.survey-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5em;margin-top:2em;}
.survey-card{background:#fff;padding:1.5em;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);transition:all 0.2s;cursor:pointer;border:2px solid transparent;}
.survey-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(15,99,212,0.15);border-color:#0f63d4;}
.survey-card h3{color:#1f2937;margin:0 0 0.5em 0;font-size:1.2em;}
.survey-card .survey-meta{color:#6b7280;font-size:0.9em;display:flex;align-items:center;gap:1em;}
.survey-card .survey-icon{width:48px;height:48px;background:linear-gradient(135deg,#0f63d4,#0a4ba0);border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:1em;}

/* Results view */
.results-header{background:#fff;padding:2em;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);margin-bottom:2em;display:flex;justify-content:space-between;align-items:center;}
.results-header h3{color:#0f63d4;margin:0;font-size:1.8em;}
.back-btn{background:#f3f4f6;color:#1f2937;padding:0.6em 1.2em;border-radius:8px;text-decoration:none;font-weight:500;transition:all 0.2s;}
.back-btn:hover{background:#e5e7eb;}

/* Statistics cards */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5em;margin-bottom:2em;}
.stat-card{background:#fff;padding:1.5em;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);text-align:center;}
.stat-card .stat-value{font-size:2.5em;font-weight:700;color:#0f63d4;margin:0;}
.stat-card .stat-label{color:#6b7280;font-size:0.9em;margin-top:0.5em;}

/* Chart container */
.chart-container{background:#fff;padding:2em;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);margin-bottom:2em;}
.chart-container h4{color:#1f2937;margin:0 0 1.5em 0;font-size:1.3em;}
.chart-wrapper{max-width:600px;margin:0 auto;}
.chart-wrapper canvas{width:100%;min-height:420px;}
.chart-mode-controls{margin-bottom:1.5em;}
.mode-buttons{display:flex;flex-wrap:wrap;gap:0.75em;margin-bottom:1.2em;align-items:center;}
.mode-btn{display:flex;align-items:center;gap:0.5em;background:#f8fafc;color:#111827;border:1px solid #d1d5db;border-radius:14px;padding:0.55em 1.4em;font-weight:600;font-size:0.95em;cursor:pointer;transition:all 0.2s ease;box-shadow:0 1px 2px rgba(15,23,42,0.04);}
.mode-btn:hover{background:#eef2ff;border-color:#c7d2fe;color:#0f172a;box-shadow:0 6px 18px rgba(99,102,241,0.18);}
.mode-btn.active{background:#0f63d4;border-color:#0f63d4;color:#fff;box-shadow:0 12px 32px rgba(15,99,212,0.35);}
.mode-panel{background:#f4f7ff;border-radius:12px;padding:1.1em 1.3em;display:block;border:1px solid #d9e3ff;box-shadow:inset 0 1px 0 rgba(255,255,255,0.8);}
.mode-panel.hidden{display:none;}
.panel-hint{margin:0 0 0.9em 0;color:#475569;font-size:0.92em;line-height:1.5;}
.student-actions{display:flex;flex-wrap:wrap;gap:0.65em;margin-bottom:0.85em;}
.chip-btn{background:#fff;border:1px solid #cbd5f5;border-radius:12px;padding:0.4em 1.1em;font-size:0.88em;font-weight:600;color:#1d4ed8;cursor:pointer;transition:all 0.2s ease;box-shadow:0 1px 2px rgba(59,130,246,0.12);}
.chip-btn:hover{background:#e0e7ff;border-color:#93c5fd;color:#1e3a8a;box-shadow:0 8px 22px rgba(59,130,246,0.22);}
.chip-btn.primary{background:#0f63d4;color:#fff;border-color:#0f63d4;}
.chip-btn.primary:hover{background:#0d4fb0;color:#fff;border-color:#0d4fb0;}
.student-checkbox-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:0.65em;}
.student-checkbox-list label{display:flex;align-items:center;gap:0.55em;padding:0.65em 0.85em;background:#fff;border-radius:10px;border:1px solid #d7def0;cursor:pointer;transition:all 0.2s ease;box-shadow:0 1px 2px rgba(15,23,42,0.04);}
.student-checkbox-list label:hover{border-color:#0f63d4;box-shadow:0 2px 6px rgba(15,99,212,0.1);}
.student-checkbox-list input{margin:0;}
.student-select-input{padding:0.52em 0.9em;border-radius:10px;border:1px solid #cbd5f5;background:#fff;min-width:240px;color:#1f2937;font-size:0.95em;box-shadow:0 1px 2px rgba(148,163,184,0.14);transition:border-color 0.2s, box-shadow 0.2s;}
.student-select-input:focus{outline:none;border-color:#0f63d4;box-shadow:0 0 0 3px rgba(15,99,212,0.16);}
.chart-empty{margin-top:1.5em;padding:1.4em;border-radius:12px;background:#eef2ff;border:1px dashed #c7d2fe;color:#1e293b;text-align:center;font-size:0.95em;font-weight:500;}
.chart-legend{margin-top:1.2em;display:flex;flex-wrap:wrap;gap:0.8em;padding:0.9em 1.1em;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;box-shadow:0 1px 2px rgba(15,23,42,0.04);}
.chart-legend.hidden{display:none;}
.legend-item{display:flex;align-items:center;gap:0.65em;padding:0.4em 0.6em;border-radius:10px;background:#fff;border:1px solid #e2e8f0;box-shadow:0 1px 1px rgba(15,23,42,0.04);}
.legend-color{width:12px;height:12px;border-radius:999px;box-shadow:0 0 0 3px rgba(255,255,255,0.7);}
.legend-text{display:flex;flex-direction:column;gap:0.2em;}
.legend-label{font-size:0.92em;font-weight:600;color:#1f2937;}
.legend-meta{font-size:0.78em;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;}
.color-dot{width:10px;height:10px;border-radius:999px;background:#0f63d4;box-shadow:0 0 0 3px rgba(15,99,212,0.12);flex-shrink:0;}
.alert{padding:0.9em 1.2em;border-radius:10px;font-size:0.92em;font-weight:500;margin-bottom:1.4em;display:flex;gap:0.75em;align-items:center;box-shadow:0 6px 18px rgba(15,99,212,0.08);}
.alert-success{background:#ecfdf5;border:1px solid #34d399;color:#065f46;}
.alert-error{background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;}
.alert-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;}
.alert svg{width:18px;height:18px;flex-shrink:0;}
.feedback-form{display:flex;flex-direction:column;gap:0.65em;}
.feedback-textarea{width:100%;min-height:90px;border:1px solid #dbe4ff;border-radius:10px;padding:0.65em 0.85em;font-size:0.92em;line-height:1.5;color:#1f2937;resize:vertical;box-shadow:0 1px 2px rgba(148,163,184,0.14);transition:border-color 0.2s, box-shadow 0.2s;}
.feedback-textarea:focus{outline:none;border-color:#0f63d4;box-shadow:0 0 0 3px rgba(15,99,212,0.18);}
.feedback-actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.85em;}
.feedback-meta{font-size:0.78em;color:#6b7280;display:flex;gap:0.65em;align-items:center;}
.feedback-meta strong{color:#0f172a;font-weight:600;}
.feedback-meta time{color:#1d4ed8;font-weight:600;}
.feedback-meta.muted{color:#9ca3af;}
.feedback-save-btn{background:#0f63d4;color:#fff;border:none;border-radius:10px;padding:0.45em 1.2em;font-size:0.9em;font-weight:600;cursor:pointer;box-shadow:0 8px 24px rgba(15,99,212,0.28);transition:all 0.2s ease;}
.feedback-save-btn:hover{background:#0d4fb0;box-shadow:0 12px 32px rgba(15,79,176,0.35);}
.feedback-save-btn:disabled{background:#bfdbfe;color:#1e3a8a;cursor:not-allowed;box-shadow:none;}
.feedback-card{margin-top:0.85em;padding:0.9em 1em;border-left:4px solid #0f63d4;background:#f1f5ff;border-radius:10px;box-shadow:0 4px 16px rgba(15,99,212,0.12);}
.feedback-card .feedback-author{font-size:0.82em;font-weight:600;color:#1d4ed8;margin-bottom:0.4em;display:flex;align-items:center;gap:0.4em;}
.feedback-card .feedback-author span{font-size:0.78em;color:#64748b;font-weight:500;}
.feedback-card .feedback-body{color:#1f2937;font-size:0.95em;line-height:1.55;white-space:pre-wrap;}
.feedback-card .feedback-date{margin-top:0.55em;font-size:0.78em;color:#6b7280;display:flex;gap:0.45em;align-items:center;}
.hidden{display:none!important;}

/* Question cards */
.question-card{background:#fff;padding:1.5em;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);margin-bottom:1.5em;}
.question-card .question-header{display:flex;align-items:center;gap:1em;margin-bottom:1em;padding-bottom:1em;border-bottom:2px solid #f3f4f6;}
.question-number{width:40px;height:40px;background:linear-gradient(135deg,#0f63d4,#0a4ba0);color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1em;}
.question-text{flex:1;color:#1f2937;font-size:1.1em;font-weight:600;}
.question-stats{display:flex;gap:2em;margin-bottom:1em;padding:1em;background:#f8fbff;border-radius:8px;}
.question-stats .stat{text-align:center;}
.question-stats .stat-num{font-size:1.8em;font-weight:700;color:#0f63d4;}
.question-stats .stat-text{color:#6b7280;font-size:0.85em;margin-top:0.3em;}

/* Responses table */
.responses-table{width:100%;border-collapse:collapse;margin-top:1em;}
.responses-table th{background:#f8fbff;color:#1f2937;font-weight:600;padding:1em;text-align:left;border-bottom:2px solid #e6eefc;}
.responses-table td{padding:1em;border-bottom:1px solid #f3f4f6;color:#4b5563;vertical-align:top;}
.responses-table tr:hover{background:#f8fbff;}
.score-badge{display:inline-block;padding:0.4em 0.8em;border-radius:6px;font-weight:600;font-size:0.9em;}
.score-high{background:#d1fae5;color:#065f46;}
.score-medium{background:#fef3c7;color:#92400e;}
.score-low{background:#fee2e2;color:#991b1b;}

/* Student view */
.student-answer{background:#f8fbff;padding:1.5em;border-radius:8px;border-left:4px solid #0f63d4;}
.student-answer .answer-label{color:#6b7280;font-size:0.9em;margin-bottom:0.5em;}
.student-answer .answer-value{color:#1f2937;font-size:1.3em;font-weight:600;}
.student-answer .answer-date{color:#9ca3af;font-size:0.85em;margin-top:0.5em;}

/* Empty state */
.empty-state{text-align:center;padding:3em;color:#6b7280;}
.empty-state svg{width:80px;height:80px;margin-bottom:1em;opacity:0.5;}
</style>

<main>
    <div class="results-container">
        <?php if ($feedbackNotice): ?>
            <?php
            $alertType = $feedbackNotice['type'] ?? 'info';
            $alertClass = $alertType === 'success' ? 'alert-success' : ($alertType === 'error' ? 'alert-error' : 'alert-info');
            ?>
            <div class="alert <?= $alertClass ?>">
                <?php if ($alertType === 'success'): ?>
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                <?php elseif ($alertType === 'error'): ?>
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-2.707-9.707a1 1 0 011.414 0L10 9.586l1.293-1.293a1 1 0 111.414 1.414L11.414 11l1.293 1.293a1 1 0 01-1.414 1.414L10 12.414l-1.293 1.293a1 1 0 01-1.414-1.414L8.586 11l-1.293-1.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                <?php else: ?>
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0 1 1 0 002 0zm-1 2a1 1 0 00-1 1v4a1 1 0 102 0v-4a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                <?php endif; ?>
                <span><?= htmlspecialchars($feedbackNotice['message']) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!$survey_id): ?>
            <!-- Survey selection view with grid layout -->
            <div class="page-header">
                <h2>Resultaten Vragenlijsten</h2>
                <p>Selecteer een vragenlijst om de resultaten te bekijken</p>
            </div>

            <div class="survey-grid">
                <?php
                $stmt = $conn->prepare("SELECT s.id, s.title, s.created_at, g.name as group_name, COUNT(DISTINCT r.user_id) as response_count 
                                       FROM surveys s 
                                       LEFT JOIN groups g ON s.group_id = g.id 
                                       LEFT JOIN responses r ON s.id = r.survey_id 
                                       GROUP BY s.id 
                                       ORDER BY s.created_at DESC");
                $stmt->execute();
                $surveys = $stmt->get_result();
                
                while ($survey = $surveys->fetch_assoc()):
                ?>
                    <a href="?id=<?= $survey['id'] ?>" style="text-decoration:none;">
                        <div class="survey-card">
                            <div class="survey-icon">
                                <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                    <path d="M9 2h6a2 2 0 012 2v16a2 2 0 01-2 2H9a2 2 0 01-2-2V4a2 2 0 012-2zm0 2v16h6V4H9zm2 2h2v2h-2V6zm0 4h2v8h-2v-8zm0 4h2v12h-2V7zm4-4h2v12h-2V7zm4-2h2v14h-2V5z"/>
                                </svg>
                            </div>
                            <h3><?= htmlspecialchars($survey['title']) ?></h3>
                            <div class="survey-meta">
                                <span style="display:flex;align-items:center;gap:0.4em;">
                                    <svg width="16" height="16" fill="#0f63d4" viewBox="0 0 24 24">
                                        <path d="M3 3v18h18v-2H5V3H3zm4 12h2v4H7v-4zm4-4h2v8h-2v-8zm4-4h2v12h-2V7zm4-2h2v14h-2V5z"/>
                                    </svg>
                                    <?= $survey['response_count'] ?> antwoorden
                                </span>
                                <span style="display:flex;align-items:center;gap:0.4em;">
                                    <svg width="16" height="16" fill="#0f63d4" viewBox="0 0 24 24">
                                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                    </svg>
                                    <?= htmlspecialchars($survey['group_name'] ?? 'Algemeen') ?>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>

        <?php else: ?>
            <!-- Results view with detailed statistics and charts -->
            <?php
            // Get survey details
            $stmt = $conn->prepare("SELECT s.title, g.name as group_name FROM surveys s LEFT JOIN groups g ON s.group_id = g.id WHERE s.id = ?");
            $stmt->bind_param("i", $survey_id);
            $stmt->execute();
            $survey_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Get questions for this survey
            $stmt = $conn->prepare("SELECT q.id, q.question_text, q.question_type, q.question_options FROM questions q 
                                   JOIN survey_questions sq ON q.id = sq.question_id 
                                   WHERE sq.survey_id = ? ORDER BY sq.id");
            $stmt->bind_param("i", $survey_id);
            $stmt->execute();
            $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            ?>

            <div class="results-header">
                <h3><?= htmlspecialchars($survey_info['title']) ?></h3>
                <a href="view_survey.php" class="back-btn">← Terug naar overzicht</a>
            </div>

            <?php if ($role === 'teacher'): ?>
                <!-- Teacher view with statistics, charts, and individual responses -->
                <?php
                // Calculate overall statistics
                $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as total_responses, 
                                       COUNT(*) as total_answers, 
                                       AVG(score) as avg_score 
                                       FROM responses WHERE survey_id = ?");
                $stmt->bind_param("i", $survey_id);
                $stmt->execute();
                $stats = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                ?>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['total_responses'] ?></div>
                        <div class="stat-label">Totaal Respondenten</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['total_answers'] ?></div>
                        <div class="stat-label">Totaal Antwoorden</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($stats['avg_score'], 1) ?></div>
                        <div class="stat-label">Gemiddelde Score</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= count($questions) ?></div>
                        <div class="stat-label">Aantal Vragen</div>
                    </div>
                </div>

                <?php
                $stmt = $conn->prepare("SELECT r.user_id, u.name, r.question_id, r.score FROM responses r JOIN users u ON r.user_id = u.id WHERE r.survey_id = ?");
                $stmt->bind_param("i", $survey_id);
                $stmt->execute();
                $responseResults = $stmt->get_result();

                $studentsForChart = [];
                $questionTotals = [];
                $questionCounts = [];

                while ($row = $responseResults->fetch_assoc()) {
                    $uid = (int) $row['user_id'];
                    if (!isset($studentsForChart[$uid])) {
                        $studentsForChart[$uid] = [
                            'id' => $uid,
                            'name' => $row['name'],
                            'scores' => []
                        ];
                    }

                    $score = $row['score'];
                    $scoreValue = null;
                    if ($score !== null && $score !== '') {
                        $scoreValue = is_numeric($score) ? (float) $score : null;
                    }

                    $questionId = (int) $row['question_id'];
                    $studentsForChart[$uid]['scores'][$questionId] = $scoreValue;

                    if ($scoreValue !== null) {
                        if (!isset($questionTotals[$questionId])) {
                            $questionTotals[$questionId] = 0;
                            $questionCounts[$questionId] = 0;
                        }
                        $questionTotals[$questionId] += $scoreValue;
                        $questionCounts[$questionId] += 1;
                    }
                }
                $stmt->close();

                $chartStudents = array_values($studentsForChart);
                usort($chartStudents, function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });

                $questionLabels = [];
                $questionTexts = [];
                $questionIds = [];
                $averageScores = [];

                foreach ($questions as $index => $question) {
                    $questionLabels[] = 'Vraag ' . ($index + 1);
                    $questionTexts[] = $question['question_text'];
                    $questionIds[] = (int) $question['id'];
                    $qid = (int) $question['id'];
                    if (isset($questionTotals[$qid]) && !empty($questionCounts[$qid])) {
                        $averageScores[] = round($questionTotals[$qid] / $questionCounts[$qid], 2);
                    } else {
                        $averageScores[] = null;
                    }
                }

                $chartPalette = ['#0f63d4', '#6366f1', '#10b981', '#ec4899', '#f59e0b', '#3b82f6', '#f97316'];
                ?>

                <!-- Radar chart for average scores per question -->
                <div class="chart-container">
                    <h4>Skilldiagram</h4>
                    <?php if (!empty($chartStudents)): ?>
                        <div class="chart-mode-controls">
                            <div class="mode-buttons">
                                <button type="button" class="mode-btn active" data-mode="group">Groepsgemiddelde</button>
                                <button type="button" class="mode-btn" data-mode="individual">Individueel per student</button>
                                <button type="button" class="mode-btn" data-mode="comparison">Vergelijking</button>
                            </div>

                            <div class="mode-panel" data-panel="group">
                                <p class="panel-hint">Gebruik de selectie om het groepsgemiddelde dynamisch te herberekenen zonder de uitgesloten studenten.</p>
                                <div class="student-actions">
                                    <button type="button" class="chip-btn primary" data-action="select-all">Alle studenten</button>
                                    <button type="button" class="chip-btn" data-action="clear-all">Alles deselecteren</button>
                                </div>
                                <div class="student-checkbox-list">
                                    <?php foreach ($chartStudents as $student): ?>
                                        <label>
                                            <input type="checkbox" class="group-student-checkbox" value="<?= $student['id'] ?>" checked>
                                            <?= htmlspecialchars($student['name']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mode-panel hidden" data-panel="individual">
                                <p class="panel-hint">Kies één student om diens individuele vaardighedenprofiel te tonen.</p>
                                <select id="individualStudentSelect" class="student-select-input">
                                    <option value="">Selecteer een student</option>
                                    <?php foreach ($chartStudents as $student): ?>
                                        <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mode-panel hidden" data-panel="comparison">
                                <p class="panel-hint">Selecteer meerdere studenten om hun vaardighedenprofielen kleur-gecodeerd te vergelijken.</p>
                                <div class="student-actions">
                                    <button type="button" class="chip-btn primary" data-action="comparison-select-all">Alle studenten</button>
                                    <button type="button" class="chip-btn" data-action="comparison-clear-all">Alles deselecteren</button>
                                </div>
                                <div class="student-checkbox-list comparison-list">
                                    <?php foreach ($chartStudents as $index => $student): ?>
                                        <?php $colorHex = $chartPalette[$index % count($chartPalette)]; ?>
                                        <label>
                                            <input type="checkbox" class="comparison-student-checkbox" value="<?= $student['id'] ?>" data-color-index="<?= $index % count($chartPalette) ?>"<?= $index < 2 ? ' checked' : '' ?>>
                                            <span class="color-dot" style="background: <?= $colorHex ?>;"></span>
                                            <span><?= htmlspecialchars($student['name']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="chart-wrapper">
                        <canvas id="radarChart"></canvas>
                    </div>
                    <div id="chartEmptyState" class="chart-empty hidden">Selecteer ten minste één student om gegevens te tonen.</div>
                    <div id="chartSelectionDetails" class="chart-legend hidden"></div>
                </div>

                <!-- Detailed question results with individual responses -->
                <h4 style="color:#1f2937;margin:2em 0 1em 0;font-size:1.5em;">Gedetailleerde Resultaten per Vraag</h4>
                
                <?php foreach ($questions as $index => $question): ?>
                    <?php
                    // Get statistics for this question
                    $stmt = $conn->prepare("SELECT COUNT(*) as count, AVG(score) as avg, MIN(score) as min, MAX(score) as max 
                                           FROM responses WHERE question_id = ? AND survey_id = ?");
                    $stmt->bind_param("ii", $question['id'], $survey_id);
                    $stmt->execute();
                    $q_stats = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    // Get individual responses with user names
                    $stmt = $conn->prepare("SELECT r.id, u.name, r.score, r.text_answer, r.created_at, r.teacher_feedback, r.feedback_at, fb.name AS feedback_teacher 
                                           FROM responses r 
                                           JOIN users u ON r.user_id = u.id 
                                           LEFT JOIN users fb ON r.feedback_by = fb.id 
                                           WHERE r.question_id = ? AND r.survey_id = ? 
                                           ORDER BY r.created_at DESC");
                    $stmt->bind_param("ii", $question['id'], $survey_id);
                    $stmt->execute();
                    $responses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    ?>

                    <div class="question-card">
                        <div class="question-header">
                            <div class="question-number"><?= $index + 1 ?></div>
                            <div class="question-text"><?= htmlspecialchars($question['question_text']) ?></div>
                        </div>

                        <div class="question-stats">
                            <div class="stat">
                                <div class="stat-num"><?= $q_stats['count'] ?></div>
                                <div class="stat-text">Antwoorden</div>
                            </div>
                            <?php if ($question['question_type'] === 'scale'): ?>
                            <div class="stat">
                                <div class="stat-num"><?= number_format($q_stats['avg'], 1) ?></div>
                                <div class="stat-text">Gemiddelde</div>
                            </div>
                            <div class="stat">
                                <div class="stat-num"><?= $q_stats['min'] ?></div>
                                <div class="stat-text">Minimum</div>
                            </div>
                            <div class="stat">
                                <div class="stat-num"><?= $q_stats['max'] ?></div>
                                <div class="stat-text">Maximum</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (count($responses) > 0): ?>
                            <table class="responses-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Antwoord</th>
                                        <th>Datum</th>
                                        <th>Docentfeedback</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($responses as $resp): ?>
                                        <?php
                                        $qtype = $question['question_type'];
                                        $display_answer = '';
                                        
                                        if ($qtype === 'text') {
                                            $display_answer = htmlspecialchars($resp['text_answer'] ?? 'Geen antwoord');
                                        } elseif ($qtype === 'choice') {
                                            $options = !empty($question['question_options']) ? explode(',', $question['question_options']) : [];
                                            $option_index = intval($resp['score']) - 1;
                                            $display_answer = isset($options[$option_index]) ? htmlspecialchars(trim($options[$option_index])) : 'Optie ' . $resp['score'];
                                        } elseif ($qtype === 'boolean') {
                                            $display_answer = intval($resp['score']) === 1 ? 'Ja' : 'Nee';
                                        } else {
                                            // scale
                                            $score = intval($resp['score']);
                                            $badge_class = $score >= 4 ? 'score-high' : ($score >= 3 ? 'score-medium' : 'score-low');
                                            $display_answer = '<span class="score-badge ' . $badge_class . '">' . $score . '</span>';
                                        }
                                        ?>
                                        <?php
                                        $teacherFeedback = $resp['teacher_feedback'] ?? '';
                                        $hasFeedback = trim((string) $teacherFeedback) !== '';
                                        $feedbackAt = $resp['feedback_at'] ?? null;
                                        $feedbackBy = $resp['feedback_teacher'] ?? 'Docent';
                                        ?>
                                        <tr id="response-<?= $resp['id'] ?>">
                                            <td><?= htmlspecialchars($resp['name']) ?></td>
                                            <td><?= $display_answer ?></td>
                                            <td><?= date('d-m-Y H:i', strtotime($resp['created_at'])) ?></td>
                                            <td>
                                                <form method="post" class="feedback-form">
                                                    <input type="hidden" name="feedback_action" value="save">
                                                    <input type="hidden" name="survey_id" value="<?= $survey_id ?>">
                                                    <input type="hidden" name="response_id" value="<?= $resp['id'] ?>">
                                                    <input type="hidden" name="redirect_anchor" value="response-<?= $resp['id'] ?>">
                                                    <textarea name="teacher_feedback" class="feedback-textarea" placeholder="Schrijf feedback voor <?= htmlspecialchars($resp['name']) ?>" aria-label="Feedback voor <?= htmlspecialchars($resp['name']) ?>"><?= htmlspecialchars($teacherFeedback) ?></textarea>
                                                    <div class="feedback-actions">
                                                        <?php if ($hasFeedback): ?>
                                                            <div class="feedback-meta">
                                                                <strong><?= htmlspecialchars($feedbackBy) ?></strong>
                                                                <?php if ($feedbackAt): ?>
                                                                    <span>•</span>
                                                                    <time datetime="<?= date('c', strtotime($feedbackAt)) ?>"><?= date('d-m-Y H:i', strtotime($feedbackAt)) ?></time>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="feedback-meta muted">Nog geen feedback toegevoegd</div>
                                                        <?php endif; ?>
                                                        <button type="submit" class="feedback-save-btn">Opslaan</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>Nog geen antwoorden ontvangen voor deze vraag</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <!-- Student view with their own answers and radar chart -->
                <h4 style="color:#1f2937;margin:2em 0 1em 0;font-size:1.5em;">Jouw Antwoorden</h4>

                <?php foreach ($questions as $index => $question): ?>
                    <?php
                    $stmt = $conn->prepare("SELECT r.score, r.text_answer, r.created_at, r.teacher_feedback, r.feedback_at, fb.name AS feedback_teacher 
                                           FROM responses r 
                                           LEFT JOIN users fb ON r.feedback_by = fb.id 
                                           WHERE r.question_id = ? AND r.survey_id = ? AND r.user_id = ? 
                                           LIMIT 1");
                    $stmt->bind_param("iii", $question['id'], $survey_id, $user_id);
                    $stmt->execute();
                    $answer = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    ?>

                    <div class="question-card">
                        <div class="question-header">
                            <div class="question-number"><?= $index + 1 ?></div>
                            <div class="question-text"><?= htmlspecialchars($question['question_text']) ?></div>
                        </div>

                        <?php if ($answer): ?>
                            <?php
                            $qtype = $question['question_type'];
                            $display_answer = '';
                            
                            if ($qtype === 'text') {
                                $display_answer = htmlspecialchars($answer['text_answer'] ?? 'Geen antwoord');
                            } elseif ($qtype === 'choice') {
                                $options = !empty($question['question_options']) ? explode(',', $question['question_options']) : [];
                                $option_index = intval($answer['score']) - 1;
                                $display_answer = isset($options[$option_index]) ? htmlspecialchars(trim($options[$option_index])) : 'Optie ' . $answer['score'];
                            } elseif ($qtype === 'boolean') {
                                $display_answer = intval($answer['score']) === 1 ? 'Ja' : 'Nee';
                            } else {
                                // scale
                                $display_answer = intval($answer['score']) . ' / 5';
                            }
                            ?>
                            <div class="student-answer">
                                <div class="answer-label">Jouw antwoord:</div>
                                <div class="answer-value"><?= $display_answer ?></div>
                                <div class="answer-date">Ingediend op <?= date('d-m-Y H:i', strtotime($answer['created_at'])) ?></div>
                            </div>
                            <?php
                            $studentFeedback = isset($answer['teacher_feedback']) ? trim((string) $answer['teacher_feedback']) : '';
                            if ($studentFeedback !== ''):
                                $studentFeedbackAuthor = $answer['feedback_teacher'] ?? 'Docent';
                                $studentFeedbackAt = $answer['feedback_at'] ?? null;
                            ?>
                                <div class="feedback-card">
                                    <div class="feedback-author">
                                        <?= htmlspecialchars($studentFeedbackAuthor) ?>
                                        <span>Docentfeedback</span>
                                    </div>
                                    <div class="feedback-body"><?= htmlspecialchars($studentFeedback) ?></div>
                                    <?php if ($studentFeedbackAt): ?>
                                        <div class="feedback-date">Laatste update <?= date('d-m-Y H:i', strtotime($studentFeedbackAt)) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>Je hebt deze vraag nog niet beantwoord</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<!-- Chart.js library for radar charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?php if ($survey_id && $role === 'teacher'): ?>
<script>
const chartMeta = <?= json_encode([
    'questionLabels' => $questionLabels,
    'questionTexts' => $questionTexts,
    'questionIds' => $questionIds,
    'students' => $chartStudents,
    'averageScores' => $averageScores,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

(function() {
    const chartCanvas = document.getElementById('radarChart');
    const emptyStateEl = document.getElementById('chartEmptyState');
    const legendContainer = document.getElementById('chartSelectionDetails');

    if (!chartCanvas || !chartMeta || !Array.isArray(chartMeta.questionIds) || !chartMeta.questionIds.length) {
        if (emptyStateEl) {
            emptyStateEl.classList.remove('hidden');
            emptyStateEl.textContent = 'Geen vragen beschikbaar voor dit diagram.';
        }
        if (chartCanvas) {
            chartCanvas.classList.add('hidden');
        }
        return;
    }

    const hasStudents = Array.isArray(chartMeta.students) && chartMeta.students.length > 0;
    if (!hasStudents) {
        if (emptyStateEl) {
            emptyStateEl.classList.remove('hidden');
            emptyStateEl.textContent = 'Nog geen resultaten beschikbaar om te visualiseren.';
        }
        chartCanvas.classList.add('hidden');
        return;
    }

    const questionLabels = chartMeta.questionLabels;
    const questionTexts = chartMeta.questionTexts;
    const questionIds = chartMeta.questionIds;
    const studentMap = new Map(chartMeta.students.map(student => [String(student.id), student]));
    const defaultAverages = chartMeta.averageScores || [];

    const palette = [
        'rgb(15, 99, 212)',
        'rgb(99, 102, 241)',
        'rgb(16, 185, 129)',
        'rgb(236, 72, 153)',
        'rgb(245, 158, 11)',
        'rgb(59, 130, 246)',
        'rgb(249, 115, 22)'
    ];
    function getPaletteColorByIndex(index) {
        return palette[index % palette.length];
    }

    function withAlpha(color, alpha) {
        return color.replace('rgb', 'rgba').replace(')', `, ${alpha})`);
    }

    const radarChart = new Chart(chartCanvas, {
        type: 'radar',
        data: {
            labels: questionLabels,
            datasets: []
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            elements: {
                line: {
                    borderWidth: 3
                }
            },
            scales: {
                r: {
                    angleLines: {
                        display: true
                    },
                    suggestedMin: 0,
                    suggestedMax: 5,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        boxWidth: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            if (!context.length) return '';
                            const index = context[0].dataIndex;
                            return questionTexts[index] || questionLabels[index];
                        },
                        label: function(context) {
                            const value = typeof context.parsed.r === 'number' ? context.parsed.r.toFixed(2) : 'Geen data';
                            return `${context.dataset.label}: ${value}`;
                        }
                    }
                }
            }
        }
    });

    const modeButtons = document.querySelectorAll('.mode-btn');
    const modePanels = document.querySelectorAll('.mode-panel');
    const groupCheckboxes = Array.from(document.querySelectorAll('.group-student-checkbox'));
    const selectAllBtn = document.querySelector('[data-action="select-all"]');
    const clearAllBtn = document.querySelector('[data-action="clear-all"]');
    const individualSelect = document.getElementById('individualStudentSelect');
    const comparisonCheckboxes = Array.from(document.querySelectorAll('.comparison-student-checkbox'));
    const comparisonSelectAllBtn = document.querySelector('[data-action="comparison-select-all"]');
    const comparisonClearAllBtn = document.querySelector('[data-action="comparison-clear-all"]');

    modeButtons.forEach(button => {
        button.addEventListener('click', () => {
            activateMode(button.dataset.mode);
        });
    });

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', () => {
            groupCheckboxes.forEach(cb => {
                cb.checked = true;
            });
            renderGroupAverage();
        });
    }

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', () => {
            groupCheckboxes.forEach(cb => {
                cb.checked = false;
            });
            renderGroupAverage();
        });
    }

    groupCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            renderGroupAverage();
        });
    });

    if (individualSelect) {
        individualSelect.addEventListener('change', event => {
            renderIndividual(event.target.value);
        });
    }

    comparisonCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            const selectedIds = comparisonCheckboxes.filter(box => box.checked).map(box => box.value);
            renderComparison(selectedIds);
        });
    });

    if (comparisonSelectAllBtn) {
        comparisonSelectAllBtn.addEventListener('click', () => {
            comparisonCheckboxes.forEach(cb => {
                cb.checked = true;
            });
            renderComparison(comparisonCheckboxes.map(cb => cb.value));
        });
    }

    if (comparisonClearAllBtn) {
        comparisonClearAllBtn.addEventListener('click', () => {
            comparisonCheckboxes.forEach(cb => {
                cb.checked = false;
            });
            renderComparison([]);
        });
    }

    function computeAverage(studentIds) {
        const totals = new Array(questionIds.length).fill(0);
        const counts = new Array(questionIds.length).fill(0);

        studentIds.forEach(id => {
            const student = studentMap.get(String(id));
            if (!student || !student.scores) {
                return;
            }
            questionIds.forEach((qid, index) => {
                const value = Object.prototype.hasOwnProperty.call(student.scores, qid) ? student.scores[qid] : null;
                if (value !== null && value !== undefined) {
                    totals[index] += Number(value);
                    counts[index] += 1;
                }
            });
        });

        return totals.map((total, index) => {
            if (!counts[index]) {
                return null;
            }
            return Number((total / counts[index]).toFixed(2));
        });
    }

    function buildDataset(label, data, color) {
        return {
            label: label,
            data: data,
            fill: true,
            backgroundColor: withAlpha(color, 0.18),
            borderColor: color,
            pointBackgroundColor: color,
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: color,
            spanGaps: true
        };
    }

    function renderLegend(items) {
        if (!legendContainer) {
            return;
        }
        if (!items || !items.length) {
            legendContainer.classList.add('hidden');
            legendContainer.innerHTML = '';
            return;
        }
        legendContainer.innerHTML = '';
        items.forEach(item => {
            const colorValue = item.color || 'rgb(15, 99, 212)';
            const legendItem = document.createElement('div');
            legendItem.className = 'legend-item';

            const colorDot = document.createElement('span');
            colorDot.className = 'legend-color';
            colorDot.style.background = colorValue;

            const textWrap = document.createElement('div');
            textWrap.className = 'legend-text';

            const labelEl = document.createElement('span');
            labelEl.className = 'legend-label';
            labelEl.textContent = item.label;
            textWrap.appendChild(labelEl);

            if (item.meta) {
                const metaEl = document.createElement('span');
                metaEl.className = 'legend-meta';
                metaEl.textContent = item.meta;
                textWrap.appendChild(metaEl);
            }

            legendItem.appendChild(colorDot);
            legendItem.appendChild(textWrap);
            legendContainer.appendChild(legendItem);
        });
        legendContainer.classList.remove('hidden');
    }

    function updateChart(datasets, legendItems = []) {
        const hasValues = datasets.some(ds => Array.isArray(ds.data) && ds.data.some(value => typeof value === 'number'));
        if (emptyStateEl) {
            emptyStateEl.classList.toggle('hidden', hasValues);
        }
        chartCanvas.classList.toggle('hidden', !hasValues);
        radarChart.data.datasets = datasets;
        radarChart.options.plugins.legend.display = datasets.length > 1 || (datasets.length === 1 && !!datasets[0].label);
        radarChart.update();
        if (!hasValues) {
            renderLegend([]);
        } else {
            renderLegend(legendItems);
        }
    }

    function ensureComparisonSelection() {
        if (!comparisonCheckboxes.length) {
            return [];
        }
        const selected = comparisonCheckboxes.filter(cb => cb.checked);
        if (selected.length) {
            return selected.map(cb => cb.value);
        }
        const defaults = comparisonCheckboxes.slice(0, Math.min(2, comparisonCheckboxes.length));
        defaults.forEach(cb => {
            cb.checked = true;
        });
        return defaults.map(cb => cb.value);
    }

    function activateMode(mode) {
        modeButtons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === mode);
        });
        modePanels.forEach(panel => {
            panel.classList.toggle('hidden', panel.dataset.panel !== mode);
        });

        if (mode === 'individual') {
            renderIndividual(individualSelect ? individualSelect.value : null);
        } else if (mode === 'comparison') {
            const selectedIds = ensureComparisonSelection();
            renderComparison(selectedIds);
        } else {
            renderGroupAverage();
        }
    }

    function renderGroupAverage() {
        if (!groupCheckboxes.length) {
            updateChart([buildDataset('Groepsgemiddelde', defaultAverages, 'rgb(15, 99, 212)')], [{ label: 'Groepsgemiddelde', color: 'rgb(15, 99, 212)' }]);
            return;
        }

        const selectedIds = groupCheckboxes.filter(cb => cb.checked).map(cb => cb.value);
        if (!selectedIds.length) {
            updateChart([], []);
            return;
        }

        const averages = computeAverage(selectedIds);
        const label = selectedIds.length === chartMeta.students.length ? 'Groepsgemiddelde' : 'Groepsgemiddelde (gefilterd)';
        updateChart([
            buildDataset(label, averages, 'rgb(15, 99, 212)')
        ], [{
            label: label,
            color: 'rgb(15, 99, 212)',
            meta: `${selectedIds.length} / ${chartMeta.students.length} studenten`
        }]);
    }

    function renderIndividual(studentId) {
        if (!studentId) {
            updateChart([], []);
            return;
        }

        const student = studentMap.get(String(studentId));
        if (!student) {
            updateChart([], []);
            return;
        }

        const data = questionIds.map(qid => {
            const value = student.scores && Object.prototype.hasOwnProperty.call(student.scores, qid) ? student.scores[qid] : null;
            return value !== null && value !== undefined ? Number(value) : null;
        });

        updateChart([
            buildDataset(student.name, data, 'rgb(15, 99, 212)')
        ], [{
            label: student.name,
            color: 'rgb(15, 99, 212)',
            meta: 'Individueel profiel'
        }]);
    }

    function renderComparison(studentIds) {
        if (!studentIds || !studentIds.length) {
            updateChart([], []);
            return;
        }

        const datasets = [];
        const legendItems = [];

        studentIds.forEach(id => {
            const student = studentMap.get(String(id));
            if (!student) {
                return;
            }
            const checkbox = comparisonCheckboxes.find(cb => cb.value === String(id));
            const colorIndex = checkbox ? Number(checkbox.dataset.colorIndex || 0) : 0;
            const color = getPaletteColorByIndex(colorIndex);
            const data = questionIds.map(qid => {
                const value = student.scores && Object.prototype.hasOwnProperty.call(student.scores, qid) ? student.scores[qid] : null;
                return value !== null && value !== undefined ? Number(value) : null;
            });
            datasets.push(buildDataset(student.name, data, color));
            legendItems.push({ label: student.name, color: color, meta: 'Vergelijkingslijn' });
        });

        updateChart(datasets, legendItems);
    }

    activateMode('group');
})();
</script>
<?php endif; ?>

