<?php include('../includes/header.php'); ?>

<div class="card register-card">
<h3 class="register-title">User Registration</h3>

<?php
if(isset($_POST['register'])){

    $full_name = $_POST['full_name'];
    $username  = $_POST['username'];
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->query("SELECT * FROM users WHERE username='$username'");

    if($check->num_rows > 0){
        echo "<p class='error-message'>Username already exists!</p>";
    } else {

        $conn->query("INSERT INTO users(full_name,username,password)
                      VALUES('$full_name','$username','$password')");

        header("Location: login.php?registered=success");
        exit();
    }
}
?>

<form method="POST" class="register-form">

<div class="form-group">
<label>Full Name</label>
<input type="text" name="full_name" required>
</div>

<div class="form-group">
<label>Username</label>
<input type="text" name="username" required>
</div>

<div class="form-group">
<label>Password</label>
<input type="password" name="password" required>
</div>

<button type="submit" name="register" class="btn-primary">
Register
</button>

</form>

</div>

<?php include('../includes/footer.php'); ?>