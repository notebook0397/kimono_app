<?php
session_start();

// DB接続設定
$dsn = 'mysql:dbname=tb270158db;host=localhost';
$user = 'tb-270158';
$password = '4H2XxPAbH5';

try {
    $pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
} catch (PDOException $e) {
    error_log("DB接続エラー(completed_items.php): " . $e->getMessage());
    $_SESSION['error_message'] = "サービスに接続できません。";
    header("Location: signin.php");
    exit();
}

// ログインチェック
if (!isset($_SESSION["user_id"])) {
    header("Location: signin.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// 購入済みアイテム一覧取得
try {
    $stmt = $pdo->prepare("SELECT * FROM completed_items WHERE user_id = :uid ORDER BY created_at DESC");
    $stmt->execute([':uid' => $user_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("購入済み取得エラー: " . $e->getMessage());
    $_SESSION['error_message'] = "購入済みリストの取得に失敗しました。";
    $items = [];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>購入済み一覧</title>
</head>
<body>
<h1>購入済み一覧</h1>

<?php if (isset($_SESSION['message'])): ?>
<p style="color:green;"><?= htmlspecialchars($_SESSION['message']) ?></p>
<?php unset($_SESSION['message']); endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<p style="color:red;"><?= htmlspecialchars($_SESSION['error_message']) ?></p>
<?php unset($_SESSION['error_message']); endif; ?>

<?php if (empty($items)): ?>
<p>まだ購入済みアイテムはありません。</p>
<?php else: ?>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>商品名</th>
            <th>詳細</th>
            <th>優先度</th>
            <th>カテゴリ</th>
            <th>備考</th>
            <th>画像</th>
            <th>購入日時</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['title']) ?></td>
            <td><?= nl2br(htmlspecialchars($item['details'])) ?></td>
            <td><?= htmlspecialchars($item['priority']) ?></td>
            <td><?= htmlspecialchars($item['category']) ?></td>
            <td><?= nl2br(htmlspecialchars($item['plus'])) ?></td>
            <td>
                <?php if (!empty($item['image_path'])): ?>
                    <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="商品画像" style="max-width:100px;">
                <?php else: ?>
                    なし
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($item['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<p><a href="tasklist.php">買い物リストへ戻る</a></p>

</body>
</html>
