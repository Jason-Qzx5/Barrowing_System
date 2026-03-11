<?php
session_start();
include(__DIR__ . '/../config/db.php');

if(isset($_SESSION['user_id'])){
    header("Location: dashboard.php");
    exit();
}

$message = "";
$type = "";

if(isset($_POST['login'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM users WHERE username='$username'");

    if($result->num_rows > 0){
        $user = $result->fetch_assoc();

        if(password_verify($password, $user['password'])){
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Invalid password.";
            $type = "error";
        }
    } else {
        $message = "User not found.";
        $type = "error";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DA Borrowing System - Authentication</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="auth-page">

<div class="auth-box">
<?php if($message != ""): ?>
    <div class="message-<?php echo $type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div id="loginForm">
    <h2>Login</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
</div>
</div>

</body>
</html>
