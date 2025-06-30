 <?php
    session_start(); 
// DB接続設定
$dsn = 'mysql:dbname=データベース名;host=localhost';
$user = 'ユーザー名';
$password = 'パスワード';
$pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));

$message = "";

// 登録処理
if (!empty($_POST["username"]) && !empty($_POST["password"])) {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    // ユーザー名の重複チェック
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $existing = $stmt->fetch();

    if ($existing) {
        $message = "このユーザー名は既に使われています。";
    } else {
        // パスワードをハッシュ化して保存
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $hashed_password]);
        $message = "登録が完了しました。ログインしてください。";

        // ログインページへ移動
        header("Location: signin.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>買い物リスト新規登録ページ</title>
    
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
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #1976D2;
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
    <h2>買い物リスト-新規登録</h2>
    <?php if ($message): ?>
        <p style="color:red"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <form action="signup.php" method="post">
         <input type="text" name="username" placeholder="ユーザー名を設定" required><br>
         <input type="password" name="password" placeholder="パスワードを設定" required><br>
        <input type="submit" value="登録">
    </form>
    <br>
    <a href="signin.php">ログインはこちら</a>
</body>
</html>