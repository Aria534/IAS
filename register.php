<?php
$conn = new mysqli("localhost", "root","", "menubar");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
if (session_status() === PHP_SESSION_NONE) session_start();

$menu  = mysqli_fetch_assoc($conn->query("SELECT MENU FROM menu WHERE ID = 1"));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $reg_user  = trim($_POST['reg_username']  ?? '');
    $reg_pass  = trim($_POST['reg_password']  ?? '');
    $reg_pass2 = trim($_POST['reg_password2'] ?? '');

    if (empty($reg_user) || empty($reg_pass)) {
        $error = "Please fill in all fields.";
    } elseif ($reg_pass !== $reg_pass2) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $reg_user);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error = "Username already taken.";
        } else {
            $hashed = md5($reg_pass);
            $ins = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $ins->bind_param("ss", $reg_user, $hashed);
            $ins->execute();
            $_SESSION['register_success'] = "Account created! You can now log in.";
            header("Location: login.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
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
        .btn {
            width: 100%; padding: 10px;
            background: #2980b9; color: #fff;
            border: none; border-radius: 5px;
            cursor: pointer; font-size: 1rem;
        }
        .btn:hover { background: #1a5f8a; }
        .error { color: red; font-size: 0.85rem; margin-bottom: 12px; }
        .login-link {
            text-align: center;
            margin-top: 16px;
            font-size: 0.9rem;
            color: #555;
        }
        .login-link a { color: #2980b9; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<?php echo $menu["MENU"]; ?>

<div class="box">
    <div class="form-header"><h2>Register</h2></div>
    <div class="form-body">
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="register">
            <input type="text" name="reg_username" placeholder="Username" required>
            <input type="password" name="reg_password" placeholder="Password" required>
            <input type="password" name="reg_password2" placeholder="Confirm Password" required>
            <button type="submit" class="btn">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Log in here</a>
        </div>
    </div>
</div>

</body>
</html>