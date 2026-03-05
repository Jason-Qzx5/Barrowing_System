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

if(isset($_POST['register'])){
    $full_name = $_POST['full_name'];
    $username  = $_POST['username'];
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->query("SELECT * FROM users WHERE username='$username'");

    if($check->num_rows > 0){
        $message = "Username already exists.";
        $type = "error";
    } else {
        $conn->query("INSERT INTO users(full_name,username,password)
                      VALUES('$full_name','$username','$password')");

        $message = "Registration successful. You can now login.";
        $type = "success";
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
    <button class="toggle-btn" onclick="showRegister()">No account? Register</button>
</div>

<div id="registerForm" class="hidden">
    <h2>Register</h2>
    <form method="POST">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="register">Register</button>
    </form>
    <button class="toggle-btn" onclick="showLogin()">Already have account? Login</button>
</div>
</div>

<script>
function showRegister(){
    document.getElementById("loginForm").classList.add("hidden");
    document.getElementById("registerForm").classList.remove("hidden");
}

function showLogin(){
    document.getElementById("registerForm").classList.add("hidden");
    document.getElementById("loginForm").classList.remove("hidden");
}
</script>

</body>
</html>
