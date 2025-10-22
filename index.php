<?php include 'includes/header.php'; ?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gilde Skillsradar</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
</head>

<body class="login-page">

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1 class="typewriter">Verbeter de samenwerking in jouw projectgroep</h1>
            <p class="typewriter-desc">Ontwikkel efficiënte teams, verzamel anonieme feedback en visualiseer resultaten met onze radar charts.</p>
            <a href="login/register.php" class="btn-primary hero-btn">Creëer je vragenlijst</a>
        </div>
        <div class="hero-image">
            <img src="assets/img/teamwork.png" alt="Teamwork illustration">
        </div>

        <!-- Floating shapes -->
        <div class="floating-shape shape1"></div>
        <div class="floating-shape shape2"></div>
        <div class="floating-shape shape3"></div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <h2>Onze functies</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <img src="https://cdn-icons-png.flaticon.com/512/2910/2910763.png" alt="">
                <h3>Vragenlijsten maken</h3>
                <p>Docenten kunnen eenvoudig vragenlijsten aanmaken en studenten uitnodigen om feedback te geven.</p>
            </div>
            <div class="feature-card">
                <img src="https://cdn-icons-png.flaticon.com/512/2910/2910798.png" alt="">
                <h3>Radardiagram</h3>
                <p>Visualiseer de groepsresultaten per skill en bespreek verbeterpunten tijdens de meeting.</p>
            </div>
            <div class="feature-card">
                <img src="https://cdn-icons-png.flaticon.com/512/2910/2910741.png" alt="">
                <h3>Anonieme feedback</h3>
                <p>Studenten kunnen veilig hun mening geven zonder dat anderen hun antwoorden zien.</p>
            </div>
            <div class="feature-card">
                <img src="https://cdn-icons-png.flaticon.com/512/2910/2910769.png" alt="">
                <h3>Herbruikbare groepen</h3>
                <p>Sla projectgroepen op en hergebruik de samenstelling voor toekomstige projecten.</p>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works">
        <h2>Hoe werkt het?</h2>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <h3>Registreer of log in</h3>
                <p>Meld je aan als docent of student en maak direct een account aan om te starten.</p>
            </div>
            <div class="step-card">
                <div class="step-number">2</div>
                <h3>Maak een vragenlijst</h3>
                <p>Kies de skills en vragen die beoordeeld moeten worden en verstuur naar studenten.</p>
            </div>
            <div class="step-card">
                <div class="step-number">3</div>
                <h3>Studenten vullen in</h3>
                <p>Studenten vullen anoniem hun antwoorden in via hun eigen apparaten.</p>
            </div>
            <div class="step-card">
                <div class="step-number">4</div>
                <h3>Bekijk resultaten</h3>
                <p>Analyseer de resultaten in een radardiagram en verbeter de samenwerking in je projectgroep.</p>
            </div>
        </div>
    </section>




    <!-- Call to Action -->
    <section class="cta">
        <h2>Klaar om jouw projectgroepen te verbeteren?</h2>
        <a href="login/register.php" class="btn-primary">Start nu</a>

        <!-- Floating shapes (voor animatie) -->
        <div class="floating-shape shape1"></div>
        <div class="floating-shape shape2"></div>
        <div class="floating-shape shape3"></div>

        <!-- Extra kleine cirkels voor diepte -->
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
    </section>


    <?php include 'includes/footer.php'; ?>
</body>

</html>