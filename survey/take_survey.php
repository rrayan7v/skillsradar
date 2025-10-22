<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Toon lijst van beschikbare enquêtes (optie: via ?id= voor direct openen)
$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$is_completed = false;
if ($survey_id) {
    $check_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM responses WHERE survey_id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check_stmt, 'ii', $survey_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_row = mysqli_fetch_assoc($check_result);
    $is_completed = $check_row['count'] > 0;
    mysqli_stmt_close($check_stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_completed) {
    $survey_id = intval($_POST['survey_id'] ?? 0);
    // For each question -> score
    $answers = $_POST['answer'] ?? [];

    // Haal survey info
    $sq = mysqli_query($conn, "SELECT anonymous FROM surveys WHERE id = " . $survey_id . " LIMIT 1");
    $an = 1;
    if ($sq && mysqli_num_rows($sq) > 0) {
        $sr = mysqli_fetch_assoc($sq);
        $an = intval($sr['anonymous']);
    }

    $question_types = [];
    $qres = mysqli_query($conn, "SELECT q.id, q.question_type FROM questions q JOIN survey_questions sq ON q.id = sq.question_id WHERE sq.survey_id = " . $survey_id);
    while ($qr = mysqli_fetch_assoc($qres)) {
        $question_types[$qr['id']] = $qr['question_type'];
    }

    foreach ($answers as $question_id => $answer) {
        $q_id = intval($question_id);
        $qtype = $question_types[$q_id] ?? 'scale';
        
        if ($qtype === 'text') {
            // For text questions, store 0 in score and the text in text_answer
            $text_answer = mysqli_real_escape_string($conn, $answer);
            $stmt = mysqli_prepare($conn, "INSERT INTO responses (survey_id, question_id, user_id, score, text_answer) VALUES (?, ?, ?, 0, ?)");
            mysqli_stmt_bind_param($stmt, 'iiis', $survey_id, $q_id, $user_id, $text_answer);
        } else {
            // For scale, choice, boolean: store numeric value in score
            $s = is_numeric($answer) ? intval($answer) : 0;
            $stmt = mysqli_prepare($conn, "INSERT INTO responses (survey_id, question_id, user_id, score) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iiii', $survey_id, $q_id, $user_id, $s);
        }
        
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    echo '<div style="max-width:1200px;margin:2em auto;padding:1.5em;background:linear-gradient(135deg,#d4edda 0%,#c3e6cb 100%);border-radius:12px;border:1px solid #b1dfbb;box-shadow:0 2px 8px rgba(0,0,0,0.08)">
            <div style="display:flex;align-items:center;gap:1em">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#155724" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <div>
                    <h3 style="margin:0;color:#155724;font-size:1.1rem">Bedankt voor je antwoorden!</h3>
                    <p style="margin:0.5em 0 0 0;color:#155724">Je antwoorden zijn succesvol opgeslagen.</p>
                </div>
            </div>
          </div>';
    $is_completed = true;
}

// Als survey_id gegeven, laad vragen gekoppeld aan deze survey
$questions = [];
$survey_title = '';
if ($survey_id) {
    $sres = mysqli_query($conn, "SELECT title FROM surveys WHERE id = " . $survey_id . " LIMIT 1");
    if ($sres && mysqli_num_rows($sres) > 0) {
        $survey_title = mysqli_fetch_assoc($sres)['title'];
    }
    
    $qres = mysqli_query($conn, "SELECT q.id, q.question_text, q.question_type, q.question_options FROM questions q JOIN survey_questions sq ON q.id = sq.question_id WHERE sq.survey_id = " . $survey_id . " ORDER BY sq.id");
    if ($qres) {
        while ($qr = mysqli_fetch_assoc($qres)) $questions[] = $qr;
    }
}

?>

<style>
body{background:#f5f7fb;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
.page-container{max-width:1200px;margin:0 auto;padding:2em}
.page-header{background:#fff;border-radius:16px;padding:3em 2em;text-align:center;margin-bottom:2.5em;border:2px solid #e6eefc;box-shadow:0 2px 12px rgba(0,0,0,0.08);position:relative;overflow:hidden}
.page-header::before{content:'';position:absolute;top:-50%;right:-10%;width:300px;height:300px;background:radial-gradient(circle,rgba(15,99,212,0.08) 0%,transparent 70%);border-radius:50%;pointer-events:none}
.page-header::after{content:'';position:absolute;bottom:-30%;left:-5%;width:250px;height:250px;background:radial-gradient(circle,rgba(15,99,212,0.06) 0%,transparent 70%);border-radius:50%;pointer-events:none}
.page-header-content{position:relative;z-index:1}
.page-header-icon{width:64px;height:64px;background:linear-gradient(135deg,#0f63d4 0%,#0a4ba3 100%);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:1.5em;box-shadow:0 4px 12px rgba(15,99,212,0.3)}
.page-header h1{color:#1f2937;font-size:2.2rem;margin:0 0 0.5em 0;font-weight:700;letter-spacing:-0.02em}
.page-header p{color:#6b7280;font-size:1.15rem;margin:0;line-height:1.6;max-width:600px;margin:0 auto}
.survey-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5em;margin-top:2em}
.survey-card{background:#fff;border-radius:12px;padding:1.5em;border:2px solid #e6eefc;transition:all 0.2s;cursor:pointer;text-decoration:none;display:block;box-shadow:0 1px 3px rgba(0,0,0,0.1);position:relative}
.survey-card:hover{border-color:#0f63d4;transform:translateY(-2px);box-shadow:0 4px 12px rgba(15,99,212,0.15)}
.survey-card.completed{opacity:0.7;border-color:#10b981}
.survey-card.completed:hover{border-color:#10b981;transform:translateY(-2px);box-shadow:0 4px 12px rgba(16,185,129,0.15)}
.survey-card-badge{position:absolute;top:1em;right:1em;background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:#fff;padding:0.4em 0.8em;border-radius:6px;font-size:0.8rem;font-weight:600;display:flex;align-items:center;gap:0.4em}
.survey-card-header{display:flex;align-items:center;gap:1em;margin-bottom:1em}
.survey-card-icon{width:48px;height:48px;background:linear-gradient(135deg,#0f63d4 0%,#0a4ba3 100%);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.survey-card.completed .survey-card-icon{background:linear-gradient(135deg,#10b981 0%,#059669 100%)}
.survey-card-title{color:#1f2937;font-size:1.2rem;font-weight:600;margin:0}
.survey-card-meta{display:flex;align-items:center;gap:1.5em;color:#6b7280;font-size:0.9rem}
.survey-card-meta svg{width:16px;height:16px;flex-shrink:0}
.question-form{max-width:900px;margin:2em auto}
.form-header{background:#fff;border-radius:12px;padding:2em;margin-bottom:2em;border:2px solid #e6eefc;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
.form-header h2{color:#1f2937;margin:0 0 0.5em 0;font-size:1.8rem}
.form-header p{color:#6b7280;margin:0}
.completed-message{background:#fff;border-radius:16px;padding:3em 2em;text-align:center;border:2px solid #10b981;box-shadow:0 2px 12px rgba(16,185,129,0.15);max-width:600px;margin:2em auto}
.completed-icon{width:80px;height:80px;background:linear-gradient(135deg,#10b981 0%,#059669 100%);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:1.5em;box-shadow:0 4px 16px rgba(16,185,129,0.3)}
.completed-message h2{color:#1f2937;font-size:1.8rem;margin:0 0 0.5em 0}
.completed-message p{color:#6b7280;font-size:1.1rem;margin:0 0 2em 0;line-height:1.6}
.question-card{background:#fff;border-radius:12px;padding:2em;margin-bottom:1.5em;border:2px solid #e6eefc;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
.question-number{display:inline-block;background:linear-gradient(135deg,#0f63d4 0%,#0a4ba3 100%);color:#fff;padding:0.3em 0.8em;border-radius:6px;font-size:0.9rem;font-weight:600;margin-bottom:1em}
.question-text{color:#1f2937;font-size:1.1rem;font-weight:600;margin:0 0 1.5em 0;line-height:1.5}
.scale-options{display:flex;gap:1em;flex-wrap:wrap}
.scale-option{flex:1;min-width:80px}
.scale-option input[type="radio"]{display:none}
.scale-option label{display:block;text-align:center;padding:1em;background:#f8fbff;border:2px solid #e6eefc;border-radius:10px;cursor:pointer;transition:all 0.2s;font-weight:600;color:#6b7280}
.scale-option input[type="radio"]:checked + label{background:linear-gradient(135deg,#0f63d4 0%,#0a4ba3 100%);border-color:#0f63d4;color:#fff;transform:scale(1.05)}
.scale-option label:hover{border-color:#0f63d4;background:#eef6ff}
.scale-option input[type="radio"]:checked + label:hover{background:linear-gradient(135deg,#0f63d4 0%,#0a4ba3 100%)}
.scale-labels{display:flex;justify-content:space-between;margin-top:0.5em;color:#6b7280;font-size:0.85rem}
.text-answer{width:100%;min-height:120px;padding:1em;border:2px solid #e6eefc;border-radius:10px;font-family:inherit;font-size:1rem;color:#1f2937;resize:vertical;transition:all 0.2s}
.text-answer:focus{outline:none;border-color:#0f63d4;box-shadow:0 0 0 3px rgba(15,99,212,0.1)}
.choice-options{display:flex;flex-direction:column;gap:0.8em}
.choice-option{position:relative}
.choice-option input[type="radio"]{display:none}
.choice-option label{display:block;padding:1em 1.2em;background:#f8fbff;border:2px solid #e6eefc;border-radius:10px;cursor:pointer;transition:all 0.2s;color:#1f2937;font-weight:500}
.choice-option input[type="radio"]:checked + label{background:linear-gradient(135deg,#0f63d4 0%,#0a4ba3 100%);border-color:#0f63d4;color:#fff;font-weight:600}
.choice-option label:hover{border-color:#0f63d4;background:#eef6ff}
.choice-option input[type="radio"]:checked + label:hover{background:linear-gradient(135deg,#0f63d4 0%,#0a4ba3 100%)}
.boolean-options{display:flex;gap:1em}
.boolean-option{flex:1}
.boolean-option input[type="radio"]{display:none}
.boolean-option label{display:block;text-align:center;padding:1.2em;background:#f8fbff;border:2px solid #e6eefc;border-radius:10px;cursor:pointer;transition:all 0.2s;font-weight:600;color:#6b7280;font-size:1.1rem}
.boolean-option input[type="radio"]:checked + label{background:linear-gradient(135deg,#0f63d4 0%,#0a4ba3 100%);border-color:#0f63d4;color:#fff;transform:scale(1.05)}
.boolean-option label:hover{border-color:#0f63d4;background:#eef6ff}
.boolean-option input[type="radio"]:checked + label:hover{background:linear-gradient(135deg,#0f63d4 0%,#0a4ba3 100%)}
.submit-section{background:#fff;border-radius:12px;padding:2em;border:2px solid #e6eefc;box-shadow:0 1px 3px rgba(0,0,0,0.1);text-align:center}
.btn-submit{background:linear-gradient(135deg,#0f63d4 0%,#0a4ba3 100%);color:#fff;border:none;padding:1em 3em;border-radius:10px;font-size:1.1rem;font-weight:600;cursor:pointer;transition:all 0.2s;box-shadow:0 2px 8px rgba(15,99,212,0.3)}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(15,99,212,0.4)}
.btn-submit:active{transform:translateY(0);box-shadow:0 2px 8px rgba(15,99,212,0.3)}
.back-link{display:inline-flex;align-items:center;gap:0.5em;color:#0f63d4;text-decoration:none;font-weight:600;margin-bottom:1.5em;transition:all 0.2s}
.back-link:hover{gap:0.7em}
.progress-bar{background:#e6eefc;height:6px;border-radius:3px;margin-bottom:2em;overflow:hidden}
.progress-fill{background:linear-gradient(90deg,#0f63d4 0%,#0a4ba3 100%);height:100%;transition:width 0.3s}
</style>

<div class="page-container">
    <?php if (!$survey_id): ?>
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-header-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </div>
                <h1>Vragenlijsten invullen</h1>
                <p>Kies een vragenlijst om in te vullen en deel je feedback</p>
            </div>
        </div>

        <div class="survey-grid">
            <?php
            $sr = mysqli_query($conn, "SELECT s.id, s.title, g.name AS group_name, COUNT(DISTINCT r.user_id) as response_count, 
                                       (SELECT COUNT(*) FROM responses WHERE survey_id = s.id AND user_id = $user_id) as user_completed
                                       FROM surveys s 
                                       LEFT JOIN `groups` g ON s.group_id = g.id 
                                       LEFT JOIN responses r ON s.id = r.survey_id 
                                       GROUP BY s.id 
                                       ORDER BY s.created_at DESC");
            while ($s = mysqli_fetch_assoc($sr)):
                $completed = $s['user_completed'] > 0;
            ?>
                <a href="?id=<?= $s['id'] ?>" class="survey-card <?= $completed ? 'completed' : '' ?>">
                    <?php if ($completed): ?>
                        <div class="survey-card-badge">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Voltooid
                        </div>
                    <?php endif; ?>
                    <div class="survey-card-header">
                        <div class="survey-card-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <?php if ($completed): ?>
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                <?php else: ?>
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                <?php endif; ?>
                            </svg>
                        </div>
                        <h3 class="survey-card-title"><?= htmlspecialchars($s['title']) ?></h3>
                    </div>
                    <div class="survey-card-meta">
                        <span style="display:flex;align-items:center;gap:0.5em">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <?= htmlspecialchars($s['group_name'] ?? 'Algemeen') ?>
                        </span>
                        <span style="display:flex;align-items:center;gap:0.5em">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="20" x2="18" y2="10"></line>
                                <line x1="12" y1="20" x2="12" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="14"></line>
                            </svg>
                            <?= $s['response_count'] ?> antwoorden
                        </span>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>

    <?php elseif ($is_completed): ?>
        <!-- Show completed message if survey is already filled in -->
        <a href="?" class="back-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Terug naar overzicht
        </a>

        <div class="completed-message">
            <div class="completed-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h2>Vragenlijst voltooid</h2>
            <p>Je hebt deze vragenlijst al ingevuld. Bedankt voor je feedback!</p>
            <a href="?" class="btn-submit" style="display:inline-block;text-decoration:none">
                Terug naar overzicht
            </a>
        </div>

    <?php elseif (!empty($questions)): ?>
        <a href="?" class="back-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Terug naar overzicht
        </a>

        <form method="post" class="question-form" id="surveyForm">
            <input type="hidden" name="survey_id" value="<?= $survey_id ?>">
            
            <div class="form-header">
                <h2><?= htmlspecialchars($survey_title) ?></h2>
                <p>Beantwoord alle vragen om je feedback in te dienen</p>
            </div>

            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width:0%"></div>
            </div>

            <?php foreach ($questions as $idx => $q): ?>
                <div class="question-card">
                    <span class="question-number">Vraag <?= $idx + 1 ?> van <?= count($questions) ?></span>
                    <p class="question-text"><?= htmlspecialchars($q['question_text']) ?></p>
                    
                    <?php 
                    $qtype = $q['question_type'] ?? 'scale';
                    
                    if ($qtype === 'scale'): ?>
                        <div class="scale-options">
                            <?php for ($i=1;$i<=5;$i++): ?>
                                <div class="scale-option">
                                    <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= $i ?>" id="q<?= $q['id'] ?>_<?= $i ?>" required onchange="updateProgress()">
                                    <label for="q<?= $q['id'] ?>_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="scale-labels">
                            <span>Helemaal mee oneens</span>
                            <span>Helemaal mee eens</span>
                        </div>
                    
                    <?php elseif ($qtype === 'text'): ?>
                        <textarea name="answer[<?= $q['id'] ?>]" class="text-answer" placeholder="Typ hier je antwoord..." required onchange="updateProgress()"></textarea>
                    
                    <?php elseif ($qtype === 'choice'): ?>
                        <div class="choice-options">
                            <?php 
                            $options = !empty($q['question_options']) ? explode(',', $q['question_options']) : ['Optie 1', 'Optie 2', 'Optie 3'];
                            foreach ($options as $optIdx => $option): 
                                $option = trim($option);
                            ?>
                                <div class="choice-option">
                                    <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= $optIdx + 1 ?>" id="q<?= $q['id'] ?>_opt<?= $optIdx ?>" required onchange="updateProgress()">
                                    <label for="q<?= $q['id'] ?>_opt<?= $optIdx ?>"><?= htmlspecialchars($option) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    
                    <?php elseif ($qtype === 'boolean'): ?>
                        <div class="boolean-options">
                            <div class="boolean-option">
                                <input type="radio" name="answer[<?= $q['id'] ?>]" value="1" id="q<?= $q['id'] ?>_yes" required onchange="updateProgress()">
                                <label for="q<?= $q['id'] ?>_yes">Ja</label>
                            </div>
                            <div class="boolean-option">
                                <input type="radio" name="answer[<?= $q['id'] ?>]" value="0" id="q<?= $q['id'] ?>_no" required onchange="updateProgress()">
                                <label for="q<?= $q['id'] ?>_no">Nee</label>
                            </div>
                        </div>
                    
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="submit-section">
                <button type="submit" class="btn-submit">Verzend antwoorden</button>
            </div>
        </form>

        <script>
        function updateProgress() {
            const form = document.getElementById('surveyForm');
            const totalQuestions = <?= count($questions) ?>;
            let answeredQuestions = 0;
            
            const questionGroups = {};
            form.querySelectorAll('input[type="radio"]').forEach(radio => {
                const name = radio.name;
                if (!questionGroups[name]) questionGroups[name] = false;
                if (radio.checked) questionGroups[name] = true;
            });
            
            form.querySelectorAll('textarea').forEach(textarea => {
                const name = textarea.name;
                if (textarea.value.trim() !== '') {
                    questionGroups[name] = true;
                } else if (!questionGroups[name]) {
                    questionGroups[name] = false;
                }
            });
            
            answeredQuestions = Object.values(questionGroups).filter(v => v).length;
            const progress = (answeredQuestions / totalQuestions) * 100;
            document.getElementById('progressFill').style.width = progress + '%';
        }
        </script>

    <?php else: ?>
        <div class="form-header">
            <h2>Geen vragen gevonden</h2>
            <p>Deze vragenlijst bevat geen vragen.</p>
            <a href="?" class="back-link" style="margin-top:1em">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Terug naar overzicht
            </a>
        </div>
    <?php endif; ?>
</div>