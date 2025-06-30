<?php
session_start();

// DB接続設定
$dsn = 'mysql:dbname=tb270158db;host=localhost';
$user = 'tb-270158';
$password = '4H2XxPAbH5';
$pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));

$message = "";

// 管理者アカウント
$admin_user = '管理者';
$admin_pass = 'qwer';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["user"] ?? '';
    $pass = $_POST["pass"] ?? '';
    if ($username === $admin_user && $pass === $admin_pass) {
        $_SESSION["is_admin"] = true;
        header("Location: admin.php");
        exit();
    } else {
        $error = "ログイン情報が間違っています";
    }
}
?>

<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>管理者ログイン</title></head>
<body>
    <style>
        body {
            background-color: #f7f7f7;
            font-family: sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 350px;
            margin: 100px auto;
            padding: 30px;
            background-color: #ffffff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            text-align: center;
            border-radius: 10px;
        }
        h2 {
            margin-bottom: 20px;
        }
        input[type="text"], input[type="password"] {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
        }
        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
        a {
            display: block;
            margin-top: 20px;
            color: #3366cc;
            text-decoration: none;
        }
    </style>

<div class="container">    
<h2>買い物リスト-管理者ログイン</h2>
<form method="post" action="">
  <input type="text" name="user" placeholder="管理者名" required><br>
  <input type="password" name="pass" placeholder="パスワード" required><br>
  <button type="submit" name="login" value="ログイン">ログイン</button>
</form>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
</body>
</html>

<br>
<p><a href="signin.php">買い物リスト-ログイン画面に戻る</a></p>