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
    $hashed_pw      = trim($_POST['hashed_pw']     ?? '');
    $captcha_answer = strtoupper(trim($_POST['captcha_answer'] ?? ''));
    $expected       = $_SESSION['captcha_text'] ?? '';

    if ($captcha_answer !== $expected) {
        $error = "Wrong CAPTCHA answer. Please try again.";
        $captcha_error = true;
        $_SESSION['captcha_text'] = generateCaptchaText();
    } elseif (empty($username) || empty($hashed_pw)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $username, $hashed_pw);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            session_regenerate_id(true);
            startSession($user);
            $_SESSION['username'] = $user['username'];
            header("Location: dashboard.php");
            exit();
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
        .captcha-hint {
            font-size: 0.78rem;
            color: #888;
            margin-bottom: 8px;
        }
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

        <form method="POST" autocomplete="off" onsubmit="return hashPw()">
            <input type="hidden" name="action" value="login">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" id="pw" placeholder="Password" required>
            <input type="hidden" id="hashed_pw" name="hashed_pw">

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

<script>
function md5(d){var r=M(V(Y(X(d),8*d.length)));return r.toLowerCase()};function M(d){for(var _,m="0123456789ABCDEF",f="",r=0;r<d.length;r++)_=d.charCodeAt(r),f+=m.charAt(_>>>4&15)+m.charAt(15&_);return f}function X(d){for(var _=Array(d.length>>2),m=0;m<_.length;m++)_[m]=0;for(m=0;m<8*d.length;m+=8)_[m>>5]|=(255&d.charCodeAt(m/8))<<m%32;return _}function V(d){for(var _="",m=0;m<32*d.length;m+=8)_+=String.fromCharCode(d[m>>5]>>>m%32&255);return _}function Y(d,_){d[_>>5]|=128<<_%32,d[14+(_+64>>>9<<4)]=_;for(var m=1732584193,f=-271733879,r=-1732584194,i=271733878,n=0;n<d.length;n+=16){var h=m,t=f,g=r,e=i;f=md5_ii(f=md5_ii(f=md5_ii(f=md5_ii(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_ff(f=md5_ff(f=md5_ff(f=md5_ff(f,r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+0],7,-680876936),f,r,d[n+1],12,-389564586),m,f,d[n+2],17,606105819),i,m,d[n+3],22,-1044525330),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+4],7,-176418897),f,r,d[n+5],12,1200080426),m,f,d[n+6],17,-1473231341),i,m,d[n+7],22,-45705983),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+8],7,1770035416),f,r,d[n+9],12,-1958414417),m,f,d[n+10],17,-42063),i,m,d[n+11],22,-1990404162),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+12],7,1804603682),f,r,d[n+13],12,-40341101),m,f,d[n+14],17,-1502002290),i,m,d[n+15],22,1236535329),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+1],5,-165796510),f,r,d[n+6],9,-1069501632),m,f,d[n+11],14,643717713),i,m,d[n+0],20,-373897302),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+5],5,-701558691),f,r,d[n+10],9,38016083),m,f,d[n+15],14,-660478335),i,m,d[n+4],20,-405537848),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+9],5,568446438),f,r,d[n+14],9,-1019803690),m,f,d[n+3],14,-187363961),i,m,d[n+8],20,1163531501),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+13],5,-1444681467),f,r,d[n+2],9,-51403784),m,f,d[n+7],14,1735328473),i,m,d[n+12],20,-1926607734),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+5],4,-378558),f,r,d[n+8],11,-2022574463),m,f,d[n+11],16,1839030562),i,m,d[n+14],23,-35309556),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+1],4,-1530992060),f,r,d[n+4],11,1272893353),m,f,d[n+7],16,-155497632),i,m,d[n+10],23,-1094730640),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+13],4,681279174),f,r,d[n+0],11,-358537222),m,f,d[n+3],16,-722521979),i,m,d[n+6],23,76029189),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+9],4,-640364487),f,r,d[n+12],11,-421815835),m,f,d[n+15],16,530742520),i,m,d[n+2],23,-995338651),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+0],6,-198630844),f,r,d[n+7],10,1126891415),m,f,d[n+14],15,-1416354905),i,m,d[n+5],21,-57434055),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+12],6,1700485571),f,r,d[n+3],10,-1894986606),m,f,d[n+10],15,-1051523),i,m,d[n+1],21,-2054922799),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+8],6,1873313359),f,r,d[n+15],10,-30611744),m,f,d[n+6],15,-1560198380),i,m,d[n+13],21,1309151649),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+4],6,-145523070),f,r,d[n+11],10,-1120210379),m,f,d[n+2],15,718787259),i,m,d[n+9],21,-343485551),m=safe_add(m,h),f=safe_add(f,t),r=safe_add(r,g),i=safe_add(i,e)}return[m,f,r,i]}function md5_cmn(d,_,m,f,r,i){return safe_add(bit_rol(safe_add(safe_add(_,d),safe_add(f,i)),r),m)}function md5_ff(d,_,m,f,r,i,n){return md5_cmn(_&m|~_&f,d,_,r,i,n)}function md5_gg(d,_,m,f,r,i,n){return md5_cmn(_&f|m&~f,d,_,r,i,n)}function md5_hh(d,_,m,f,r,i,n){return md5_cmn(_^m^f,d,_,r,i,n)}function md5_ii(d,_,m,f,r,i,n){return md5_cmn(m^(_|~f),d,_,r,i,n)}function safe_add(d,_){var m=(65535&d)+(65535&_);return(d>>16)+(_>>16)+(m>>16)<<16|65535&m}function bit_rol(d,_){return d<<_|d>>>32-_}

function hashPw() {
    var plain = document.getElementById('pw').value;
    if (plain === '') return true;
    document.getElementById('hashed_pw').value = md5(plain);
    document.getElementById('pw').value = '';
    return true;
}
</script>
</body>
</html>