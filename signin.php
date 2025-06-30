 <?php
session_start(); 
// DB接続設定
$dsn = 'mysql:dbname=データベース名;host=localhost';
$user = 'ユーザー名';
$password = 'パスワード';
$pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));

$message = "";

// ログイン処理
if (!empty($_POST["username"]) && !empty($_POST["password"])) {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {
        // セッション保存
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        header("Location: tasklist.php");
        exit();
    } else {
        $message = "ユーザー名またはパスワードが間違っています。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>買い物リストログインページ</title>
    
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
    
</head>
<body>
    
<div class="container">
    <h2>買い物リスト-ログイン</h2>
    <?php if ($message): ?>
        <p style="color:red"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <form action="signin.php" method="post">
        <input type="text" name="username" placeholder="ユーザー名" required><br>
        <input type="password" name="password" placeholder="パスワード" required><br>
        <input type="submit" value="ログイン">
    </form>
    <br>
    <a href="signup.php">登録がまだの方はこちら</a>
    <p><a href="adminlogin.php">管理者ページはこちら</a></p>
</body>
</html>