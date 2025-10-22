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
.responses-table td{padding:1em;border-bottom:1px solid #f3f4f6;color:#4b5563;}
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

                <!-- Radar chart for average scores per question -->
                <div class="chart-container">
                    <h4>Gemiddelde Scores per Vraag</h4>
                    <div class="chart-wrapper">
                        <canvas id="radarChart"></canvas>
                    </div>
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
                    $stmt = $conn->prepare("SELECT u.name, r.score, r.text_answer, r.created_at 
                                           FROM responses r 
                                           JOIN users u ON r.user_id = u.id 
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
                                        <tr>
                                            <td><?= htmlspecialchars($resp['name']) ?></td>
                                            <td><?= $display_answer ?></td>
                                            <td><?= date('d-m-Y H:i', strtotime($resp['created_at'])) ?></td>
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

                <?php
                // Get student's responses
                $has_responses = false;
                ?>

                <?php foreach ($questions as $index => $question): ?>
                    <?php
                    $stmt = $conn->prepare("SELECT score, text_answer, created_at FROM responses 
                                           WHERE question_id = ? AND survey_id = ? AND user_id = ? LIMIT 1");
                    $stmt->bind_param("iii", $question['id'], $survey_id, $user_id);
                    $stmt->execute();
                    $answer = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if ($answer) $has_responses = true;
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
                        <?php else: ?>
                            <div class="empty-state">
                                <p>Je hebt deze vraag nog niet beantwoord</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($has_responses): ?>
                    <!-- Student radar chart showing their own scores -->
                    <div class="chart-container">
                        <h4>Jouw Scores Visualisatie</h4>
                        <div class="chart-wrapper">
                            <canvas id="studentRadarChart"></canvas>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<!-- Chart.js library for radar charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?php if ($survey_id && $role === 'teacher'): ?>
<script>
const ctx = document.getElementById('radarChart');
const questions = <?= json_encode(array_map(function($q) { return $q['question_text']; }, $questions)) ?>;
const questionIds = <?= json_encode(array_map(function($q) { return $q['id']; }, $questions)) ?>;

// Fetch average scores for each question
const avgScores = [];
<?php foreach ($questions as $q): ?>
    <?php
    $stmt = $conn->prepare("SELECT AVG(score) as avg FROM responses WHERE question_id = ? AND survey_id = ?");
    $stmt->bind_param("ii", $q['id'], $survey_id);
    $stmt->execute();
    $avg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    ?>
    avgScores.push(<?= $avg['avg'] ?? 0 ?>);
<?php endforeach; ?>

new Chart(ctx, {
    type: 'radar',
    data: {
        labels: questions.map((q, i) => `Vraag ${i + 1}`),
        datasets: [{
            label: 'Gemiddelde Score',
            data: avgScores,
            fill: true,
            backgroundColor: 'rgba(15, 99, 212, 0.2)',
            borderColor: 'rgb(15, 99, 212)',
            pointBackgroundColor: 'rgb(15, 99, 212)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgb(15, 99, 212)'
        }]
    },
    options: {
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
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Score: ' + context.parsed.r.toFixed(1);
                    }
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php if ($survey_id && $role === 'student' && $has_responses): ?>
<script>
const studentCtx = document.getElementById('studentRadarChart');
const studentQuestions = <?= json_encode(array_map(function($q) { return $q['question_text']; }, $questions)) ?>;

const studentScores = [];
<?php foreach ($questions as $q): ?>
    <?php
    $stmt = $conn->prepare("SELECT score FROM responses WHERE question_id = ? AND survey_id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("iii", $q['id'], $survey_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    ?>
    studentScores.push(<?= $result['score'] ?? 0 ?>);
<?php endforeach; ?>

new Chart(studentCtx, {
    type: 'radar',
    data: {
        labels: studentQuestions.map((q, i) => `Vraag ${i + 1}`),
        datasets: [{
            label: 'Jouw Score',
            data: studentScores,
            fill: true,
            backgroundColor: 'rgba(15, 99, 212, 0.2)',
            borderColor: 'rgb(15, 99, 212)',
            pointBackgroundColor: 'rgb(15, 99, 212)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgb(15, 99, 212)'
        }]
    },
    options: {
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
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Score: ' + context.parsed.r;
                    }
                }
            }
        }
    }
});
</script>
<?php endif; ?>

