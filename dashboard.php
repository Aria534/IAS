<?php
session_start();
require_once 'session.php';
checkSession();

$uname   = htmlspecialchars($_SESSION['username']);
$initial = strtoupper(substr($uname, 0, 1));

$timeout = $_SESSION['SESSION_TIMEOUT'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'DM Sans', sans-serif; }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }

        .shimmer::before {
            content:''; position:absolute; top:0; left:10%; right:10%;
            height:1px;
            background:linear-gradient(90deg,transparent,#7c6af7,#c084fc,transparent);
        }
    </style>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        surface: '#ffffff',
                        accent: '#7c6af7',
                        accent2: '#c084fc'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="relative bg-white border border-gray-200 rounded-3xl p-12 w-[420px] overflow-hidden shadow-lg shimmer fade-up">

        <!-- Profile -->
        <div class="flex items-center gap-4 mb-8 fade-up delay-1">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-accent to-accent2 flex items-center justify-center text-white text-2xl font-bold shadow-[0_8px_24px_rgba(124,106,247,0.35)] shrink-0">
                <?= $initial ?>
            </div>

            <div>
                <p class="text-[0.7rem] font-medium tracking-widest uppercase text-gray-400 mb-1">
                    Signed in as
                </p>
                <p class="text-gray-800 font-semibold"><?= $uname ?></p>
            </div>
        </div>

        <hr class="border-gray-200 mb-7">

        <!-- Welcome -->
        <p class="text-sm text-gray-500 mb-6 fade-up delay-2">
            You're logged in. Welcome back! Your session is active and secure.
        </p>

        <!-- Logout -->
        <a href="login.php?logout=1"
           class="block w-full py-3 text-center text-sm font-medium text-gray-500 border border-gray-200 rounded-xl hover:bg-red-50 hover:border-red-300 hover:text-red-500 transition-all duration-200 fade-up delay-2">
            Log Out
        </a>

    </div>

<script>
var idleLimit   = <?= $timeout ?>;
var idleSeconds = 0;

// Reset timer when user is active
['mousemove','mousedown','keydown','touchstart','scroll','click'].forEach(function(evt) {
    document.addEventListener(evt, function() {
        idleSeconds = 0;
    }, true);
});

// Check idle time
function checkIdle() {
    idleSeconds++;

    var remaining = idleLimit - idleSeconds;

    if (remaining <= 0) {
        window.location = 'login.php?expired=1';
        return;
    }

    setTimeout(checkIdle, 1000);
}

checkIdle();
</script>

</body>
</html>