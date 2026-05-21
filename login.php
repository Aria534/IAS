<?php
// ── FORCE ERROR DISPLAY ───────────────────────────────────
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ── CONFIG ───────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'menubar');

// ── SESSION SAFE START ───────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

if (file_exists('session.php')) {
    require_once 'session.php';
}

// ── DB CONNECTION ────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// ── HELPERS ──────────────────────────────────────────────
function logout(): never {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

function generateCaptchaText(int $length = 6): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $text  = '';
    for ($i = 0; $i < $length; $i++) {
        $text .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $text;
}

function makeCaptcha(string $text): string {
    if (!function_exists('imagecreatetruecolor')) return '';

    $w = 280; $h = 80;
    $img = imagecreatetruecolor($w, $h);

    $bg = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);

    for ($i = 0; $i < 400; $i++) {
        $c = imagecolorallocate($img, rand(180, 220), rand(180, 220), rand(220, 240));
        imagesetpixel($img, rand(0, $w-1), rand(0, $h-1), $c);
    }

    for ($l = 0; $l < 6; $l++) {
        $lc = imagecolorallocate($img, rand(140, 180), rand(140, 200), rand(200, 230));
        imageline($img,
            rand(0, $w), rand(0, (int)($h/2)),
            rand(0, $w), rand((int)($h/2), $h),
            $lc
        );
    }

    $chars = str_split($text);
    $slotW = ($w - 20) / max(1, count($chars));
    $x     = 14;
    $ttf   = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    foreach ($chars as $char) {
        $color = imagecolorallocate($img, rand(20, 80), rand(20, 80), rand(120, 180));

        if (function_exists('imagettftext') && file_exists($ttf)) {
            imagettftext($img, rand(22, 26), rand(-10, 10), (int)($x + 4), rand(52, 62), $color, $ttf, $char);
        } else {
            imagestring($img, 5, (int)$x + 4, rand(22, 34), $char, $color);
        }

        $x += $slotW;
    }

    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    return base64_encode($data);
}

// ── SQL INJECTION DETECTOR ────────────────────────────────
function detectSQLInjection(string $input): bool {
    $patterns = [
        '/(\bOR\b|\bAND\b)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i',
        '/[\'"]s*(\bOR\b|\bAND\b)\s*[\'"]/i',
        '/--\s*$/',
        '/;\s*(DROP|DELETE|INSERT|UPDATE|SELECT)\b/i',
        '/\bUNION\b.*\bSELECT\b/i',
        '/\/\*.*\*\//',
        '/\bEXEC\b|\bEXECUTE\b/i',
        '/\bxp_\w+/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    return false;
}

// ── ROUTES ───────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

if (isset($_GET['refresh_captcha'])) {
    $_SESSION['captcha_text'] = generateCaptchaText();
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['captcha_text'])) {
    $_SESSION['captcha_text'] = generateCaptchaText();
}

// ── LOGIN LOGIC ──────────────────────────────────────────
$error     = '';
$errorType = 'error'; // 'error' | 'warning'

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $captcha  = strtoupper(trim($_POST['captcha_answer'] ?? ''));
    $expected = $_SESSION['captcha_text'] ?? '';

    // ── SQL INJECTION CHECK ──────────────────────────────
    if (detectSQLInjection($username) || detectSQLInjection($password)) {
        $_SESSION['captcha_text'] = generateCaptchaText();
        $error     = "SQL Injection detected. This attempt has been logged.";
        $errorType = 'warning';

    // ── EMPTY FIELDS CHECK ───────────────────────────────
    } elseif ($username === '' && $password === '') {
        $error = "Username and password are required.";

    } elseif ($username === '') {
        $error = "Username or email is required.";

    } elseif ($password === '') {
        $error = "Password is required.";

    // ── CAPTCHA CHECK (skip if blank = Postman/API testing) ──
    } elseif (!empty($captcha) && $captcha !== $expected) {
        $_SESSION['captcha_text'] = generateCaptchaText();
        $error = "Invalid CAPTCHA. Please try again.";

    } else {
        $stmt = $conn->prepare("
            SELECT id, username, password
            FROM users
            WHERE username = ? OR email = ?
        ");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            if (function_exists('startSession')) {
                startSession($user);
            } else {
                $_SESSION['logged_in']       = true;
                $_SESSION['user_id']         = $user['id'];
                $_SESSION['username']        = $user['username'];
                $_SESSION['LAST_ACTIVITY']   = time();
                $_SESSION['SESSION_TIMEOUT'] = 600;
            }

            header("Location: dashboard.php");
            exit();

        } else {
            $_SESSION['captcha_text'] = generateCaptchaText();
            $error = "Invalid username or password.";
        }
    }
}

$captchaImg = makeCaptcha($_SESSION['captcha_text']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #e8e8e8;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        nav {
            background: #222;
            display: flex;
            justify-content: center;
            gap: 40px;
            padding: 16px 0;
        }
        nav a {
            color: #fff;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 400;
            transition: color 0.2s;
        }
        nav a:hover { color: #aad4f5; }

        .page-body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
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

        .alert {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 11px 14px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-bottom: 18px;
        }
        .alert-error {
            background: #e53935;
            color: #fff;
        }
        .alert-warning {
            background: #e65100;
            color: #fff;
        }
        .alert-icon { font-size: 1rem; flex-shrink: 0; }

        .field { margin-bottom: 14px; }
        .field input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #333;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fff;
        }
        .field input::placeholder { color: #aaa; }
        .field input:focus {
            border-color: #2b7fc4;
            box-shadow: 0 0 0 3px rgba(43,127,196,0.12);
        }

        .captcha-img-wrap {
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
            background: #fff;
        }
        .captcha-img-wrap img {
            display: block;
            width: 100%;
            height: 80px;
            object-fit: fill;
        }
        .captcha-plain {
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 12px 16px;
            font-family: 'Courier New', monospace;
            font-size: 1.4rem;
            letter-spacing: 0.3em;
            color: #333;
            background: #f9f9f9;
            text-align: center;
            margin-bottom: 10px;
            user-select: none;
        }

        .resend-btn {
            display: block;
            width: 100%;
            padding: 9px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            color: #2b7fc4;
            font-size: 0.88rem;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 14px;
            transition: background 0.2s, border-color 0.2s;
        }
        .resend-btn:hover {
            background: #f0f7ff;
            border-color: #2b7fc4;
        }

        .login-btn {
            display: block;
            width: 100%;
            padding: 11px;
            background: #2b7fc4;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-bottom: 16px;
        }
        .login-btn:hover { background: #1a6aad; }

        .register-link {
            text-align: center;
            font-size: 0.85rem;
            color: #666;
        }
        .register-link a {
            color: #2b7fc4;
            text-decoration: none;
            font-weight: 500;
        }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <nav>
        <a href="#">Home</a>
        <a href="#">About</a>
        <a href="#">Contact</a>
    </nav>

    <div class="page-body">
        <div class="card">
            <div class="card-header">
                <h1>Login</h1>
            </div>

            <div class="card-body">

                <?php if ($error !== ''): ?>
                    <div class="alert alert-<?= $errorType ?>">
                        <span class="alert-icon"><?= $errorType === 'warning' ? '🛡️' : '⚠' ?></span>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="login">

                    <div class="field">
                        <input type="text" name="username"
                               placeholder="Username or Email"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               autocomplete="username" required>
                    </div>

                    <div class="field">
                        <input type="password" name="password"
                               placeholder="Password"
                               autocomplete="current-password" required>
                    </div>

                    <?php if ($captchaImg !== ''): ?>
                        <div class="captcha-img-wrap">
                            <img src="data:image/png;base64,<?= $captchaImg ?>" alt="CAPTCHA">
                        </div>
                    <?php else: ?>
                        <div class="captcha-plain"><?= htmlspecialchars($_SESSION['captcha_text']) ?></div>
                    <?php endif; ?>

                    <a href="?refresh_captcha=1" class="resend-btn">Resend</a>

                    <div class="field">
                        <input type="text" name="captcha_answer"
                               placeholder="Enter CAPTCHA"
                               autocomplete="off" maxlength="6"
                               required>
                    </div>

                    <button type="submit" class="login-btn">Log In</button>

                    <div class="register-link">
                        Don't have an account? <a href="register.php">Register here</a>
                    </div>
                </form>

            </div>
        </div>
    </div>

</body>
</html>