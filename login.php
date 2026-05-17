<?php
$conn = new mysqli("localhost", "root", "", "menubar");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'session.php';

// LOGOUT
function logout() {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

if (isset($_GET['logout'])) {
    logout();
}

// Generate Text CAPTCHA
function generateCaptchaText($length = 6) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $text = '';
    for ($i = 0; $i < $length; $i++) {
        $text .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $text;
}

if (empty($_SESSION['captcha_text'])) {
    $_SESSION['captcha_text'] = generateCaptchaText();
}

// RESEND CAPTCHA
if (isset($_GET['refresh_captcha'])) {
    $_SESSION['captcha_text'] = generateCaptchaText();
    header("Location: login.php");
    exit();
}

$menu    = mysqli_fetch_assoc($conn->query("SELECT MENU FROM menu WHERE ID = 1"));
$error   = $_SESSION['login_error']      ?? '';
$success = $_SESSION['register_success'] ?? '';
$captcha_error = false;
unset($_SESSION['login_error'], $_SESSION['register_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username       = trim($_POST['username']      ?? '');
    $plainPassword  = trim($_POST['password']      ?? '');
    $captcha_answer = strtoupper(trim($_POST['captcha_answer'] ?? ''));
    $expected       = $_SESSION['captcha_text'] ?? '';

    // ✅ CAPTCHA disabled for Postman testing — re-enable after testing!
    $skip_captcha = false;

    if (!$skip_captcha && $captcha_answer !== $expected) {
        $error = "Wrong CAPTCHA answer. Please try again.";
        $captcha_error = true;
        $_SESSION['captcha_text'] = generateCaptchaText();
    } elseif (empty($username) || empty($plainPassword)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (md5($plainPassword) === $user['password']) {
                session_regenerate_id(true);
                startSession($user);
                $_SESSION['username'] = $user['username'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password.";
                $_SESSION['captcha_text'] = generateCaptchaText();
            }
        } else {
            $error = "Invalid username or password.";
            $_SESSION['captcha_text'] = generateCaptchaText();
        }
    }
}

// GENERATE TEXT CAPTCHA IMAGE
function makeCaptcha($text) {
    $w = 160; $h = 50;
    $img = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);
    for ($i = 0; $i < 800; $i++) {
        $c = imagecolorallocate($img, rand(150, 230), rand(150, 230), rand(150, 230));
        imagesetpixel($img, rand(0, $w), rand(0, $h), $c);
    }
    for ($i = 0; $i < 6; $i++) {
        $c = imagecolorallocate($img, rand(150, 200), rand(150, 200), rand(150, 200));
        imageline($img, rand(0, $w), rand(0, $h), rand(0, $w), rand(0, $h), $c);
    }
    $x = 10;
    foreach (str_split($text) as $char) {
        $c = imagecolorallocate($img, rand(0, 80), rand(0, 100), rand(100, 200));
        imagestring($img, 5, $x, rand(8, 18), $char, $c);
        $x += rand(20, 26);
    }
    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    return base64_encode($data);
}

$captchaImg = makeCaptcha($_SESSION['captcha_text']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f0f0; }
        .box {
            width: 360px; margin: 60px auto;
            background: #fff; border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .form-header {
            padding: 18px 28px 0;
            border-bottom: 3px solid #2980b9;
        }
        .form-header h2 {
            font-size: 1.2rem;
            color: #2980b9;
            padding-bottom: 14px;
            text-align: center;
        }
        .form-body { padding: 28px; }
        input[type=text], input[type=password] {
            width: 100%; padding: 9px 12px;
            margin-bottom: 14px;
            border: 1px solid #ccc; border-radius: 5px;
            font-size: 0.95rem;
        }
        input:focus { border-color: #2980b9; outline: none; }
        input.captcha-error-input {
            border-color: red;
            background: #fff5f5;
        }
        .btn {
            width: 100%; padding: 10px; background: #2980b9;
            color: #fff; border: none; border-radius: 5px;
            cursor: pointer; font-size: 1rem;
            text-align: center;
        }
        .btn:hover { background: #1a5f8a; }
        .btn-resend {
            width: 100%;
            padding: 7px;
            background: #ecf0f1;
            color: #2980b9;
            border: 1px solid #2980b9;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        .btn-resend:hover {
            background: #2980b9;
            color: #fff;
        }
        .error {
            color: #fff;
            background: #e74c3c;
            font-size: 0.85rem;
            margin-bottom: 12px;
            padding: 8px 12px;
            border-radius: 5px;
            text-align: center;
        }
        .success {
            color: #fff;
            background: #27ae60;
            font-size: 0.85rem;
            margin-bottom: 12px;
            padding: 8px 12px;
            border-radius: 5px;
            text-align: center;
        }
        .captcha-wrap {
            margin-bottom: 4px;
            border: 1px solid #ccc;
            border-radius: 5px;
            overflow: hidden;
            line-height: 0;
        }
        .captcha-wrap.captcha-error-border {
            border-color: red;
        }
        .captcha-wrap img { width: 100%; display: block; }
        .captcha-warning {
            color: red;
            font-size: 0.82rem;
            margin-bottom: 6px;
            font-weight: bold;
        }
        .register-link {
            text-align: center;
            margin-top: 16px;
            font-size: 0.9rem;
            color: #555;
        }
        .register-link a { color: #2980b9; text-decoration: none; }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<?php echo $menu["MENU"]; ?>

<div class="box">
    <div class="form-header"><h2>Login</h2></div>
    <div class="form-body">
        <?php if ($error):   ?><div class="error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="login">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" id="pw" name="password" placeholder="Password" required>

            <div class="captcha-wrap <?= $captcha_error ? 'captcha-error-border' : '' ?>">
                <img src="data:image/png;base64,<?= $captchaImg ?>" alt="CAPTCHA">
            </div>

            <?php if ($captcha_error): ?>
            <div class="captcha-warning">⚠️ Wrong CAPTCHA! A new one has been generated.</div>
            <?php endif; ?>

            <button type="button" class="btn-resend" onclick="window.location='?refresh_captcha=1'">Resend</button>

            <input type="text" name="captcha_answer" placeholder="Enter CAPTCHA"
                class="<?= $captcha_error ? 'captcha-error-input' : '' ?>" required>

            <button type="submit" class="btn">Log In</button>
        </form>

        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</div>

</body>
</html>