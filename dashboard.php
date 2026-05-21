<?php
session_start();
require_once 'session.php';
checkSession();

$uname   = htmlspecialchars($_SESSION['username']);
$initial = strtoupper(substr($uname, 0, 1));
$timeout = $_SESSION['SESSION_TIMEOUT'];

// Calculate actual remaining time based on last activity
$lastActivity = $_SESSION['LAST_ACTIVITY'] ?? time();
$elapsed      = time() - $lastActivity;
$remaining    = max(0, $timeout - $elapsed);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #e8e8e8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        .card-header {
            border-bottom: 3px solid #2b7fc4;
            padding: 20px 30px 16px;
            text-align: center;
        }
        .card-header h1 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2b7fc4;
        }

        .card-body { padding: 24px 30px 28px; }

        /* Avatar row */
        .avatar-row {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        .avatar {
            width: 52px; height: 52px;
            border-radius: 12px;
            background: linear-gradient(135deg, #7c3aed, #db2777);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            font-weight: 600;
            color: #fff;
            flex-shrink: 0;
        }
        .avatar-info p.label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #999;
            margin-bottom: 2px;
        }
        .avatar-info p.username {
            font-size: 1rem;
            font-weight: 500;
            color: #222;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: #eee;
            margin-bottom: 20px;
        }

        /* Status */
        .status {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 20px;
        }
        .status-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 6px #4ade80;
            flex-shrink: 0;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 6px #4ade80; }
            50%       { box-shadow: 0 0 2px #4ade80; }
        }
        .status-text {
            font-size: 0.85rem;
            color: #555;
        }

        /* Session timer */
        .session-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .session-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #999;
        }
        .session-timer {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2b7fc4;
        }
        .session-timer.warning { color: #e53935; }

        /* Logout */
        .logout-btn {
            display: block;
            width: 100%;
            padding: 11px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            color: #555;
            font-size: 0.88rem;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
        }
        .logout-btn:hover {
            background: #fff0f0;
            border-color: #e53935;
            color: #e53935;
        }
    </style>
</head>
<body>

    <div class="card">
        <div class="card-header">
            <h1>Dashboard</h1>
        </div>

        <div class="card-body">

            <div class="avatar-row">
                <div class="avatar"><?= $initial ?></div>
                <div class="avatar-info">
                    <p class="label">Signed in as</p>
                    <p class="username"><?= $uname ?></p>
                </div>
            </div>

            <div class="divider"></div>

            <div class="status">
                <div class="status-dot"></div>
                <span class="status-text">Active session</span>
            </div>

            <div class="session-row">
                <span class="session-label">Session expires in</span>
                <span class="session-timer" id="timer">—</span>
            </div>

            <a href="login.php?logout=1" class="logout-btn">Sign Out</a>

        </div>
    </div>

<script>
// Start from server-calculated remaining time
var remaining = <?= $remaining ?>;
var idleLimit = <?= $timeout ?>;

// Reset remaining on any user activity
['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function(evt) {
    document.addEventListener(evt, function() {
        remaining = idleLimit; // reset to full timeout on activity
    }, true);
});

function fmt(s) {
    var m   = Math.floor(s / 60);
    var sec = s % 60;
    return (m > 0 ? m + 'm ' : '') + sec + 's';
}

function tick() {
    var el = document.getElementById('timer');

    if (remaining <= 0) {
        window.location = 'login.php?expired=1';
        return;
    }

    el.textContent = fmt(remaining);

    if (remaining <= 10) {
        el.classList.add('warning');
    } else {
        el.classList.remove('warning');
    }

    remaining--;
    setTimeout(tick, 1000);
}

// Start immediately
tick();
</script>
</body>
</html>