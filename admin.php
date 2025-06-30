<?php
session_start();

// 管理者としてログインしているか確認
if (!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    header("Location: adminlogin.php");
    exit();
}

// データベース接続設定
$dsn = 'mysql:dbname=tb270158db;host=localhost';
$user = 'tb-270158';
$password = '4H2XxPAbH5';

try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    error_log("データベース接続エラー (admin.php): " . $e->getMessage());
    echo "現在、データベースに接続できません。時間をおいてお試しください。";
    exit();
}

$message = "";

// users テーブル作成
$sql_create_users = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE, 
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
try {
    $pdo->exec($sql_create_users);
} catch (PDOException $e) {
    error_log("usersテーブル作成エラー: " . $e->getMessage());
    $message .= "usersテーブル作成中にエラーが発生しました。<br>";
}

// todos テーブル作成
$sql_create_todos = "
CREATE TABLE IF NOT EXISTS todos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    details TEXT, 
    due_date DATE,
    category VARCHAR(50), 
    plus TEXT, 
    priority VARCHAR(10) DEFAULT '★★',
    is_completed BOOLEAN DEFAULT FALSE, 
    image_path VARCHAR(255) DEFAULT NULL, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
try {
    $pdo->exec($sql_create_todos);
} catch (PDOException $e) {
    error_log("todosテーブル作成エラー: " . $e->getMessage());
    $message .= "todosテーブル作成中にエラーが発生しました。<br>";
}

// カラム存在確認関数
function columnExists(PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM {$tableName} LIKE '{$columnName}'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

try {
    if (columnExists($pdo, 'todos', 'id')) {
        if (!columnExists($pdo, 'todos', 'is_completed')) {
            if (columnExists($pdo, 'todos', 'is_done')) {
                $pdo->exec("ALTER TABLE todos CHANGE COLUMN is_done is_completed BOOLEAN DEFAULT FALSE");
                $message .= "'is_done'カラムを'is_completed'に変更しました。<br>";
            } else {
                $pdo->exec("ALTER TABLE todos ADD COLUMN is_completed BOOLEAN DEFAULT FALSE");
                $message .= "'is_completed'カラムを追加しました。<br>";
            }
        }

        if (!columnExists($pdo, 'todos', 'details')) {
            $pdo->exec("ALTER TABLE todos ADD COLUMN details TEXT");
            $message .= "'details'カラムを追加しました。<br>";
        }

        if (!columnExists($pdo, 'todos', 'category')) {
            $pdo->exec("ALTER TABLE todos ADD COLUMN category VARCHAR(50)");
            $message .= "'category'カラムを追加しました。<br>";
        }

        if (!columnExists($pdo, 'todos', 'image_path')) {
            $pdo->exec("ALTER TABLE todos ADD COLUMN image_path VARCHAR(255) DEFAULT NULL");
            $message .= "'image_path'カラムを追加しました。<br>";
        }

        if (!columnExists($pdo, 'todos', 'plus')) {
            $pdo->exec("ALTER TABLE todos ADD COLUMN plus TEXT DEFAULT NULL");
            $message .= "'plus'カラムを追加しました。<br>";
        }

        if (!columnExists($pdo, 'todos', 'priority')) {
            $pdo->exec("ALTER TABLE todos ADD COLUMN priority VARCHAR(10) DEFAULT '★★'");
            $message .= "'priority'カラムを追加しました。<br>";
        }

        if (!columnExists($pdo, 'users', 'created_at')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            $message .= "usersテーブルに'created_at'カラムを追加しました。<br>";
        }
    } else {
        $message .= "todosテーブルが見つかりません。<br>";
    }
} catch (PDOException $e) {
    error_log("カラム追加エラー: " . $e->getMessage());
    $message .= "カラム追加中にエラーが発生しました: " . $e->getMessage() . "<br>";
}

// ユーザー削除
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :delete_id");
        $stmt->execute([':delete_id' => $delete_id]);
        $message .= "ユーザーID {$delete_id} を削除しました。<br>";
    } catch (PDOException $e) {
        error_log("ユーザー削除エラー: " . $e->getMessage());
        $message .= "ユーザー削除中にエラーが発生しました: " . $e->getMessage() . "<br>";
    }
    header("Location: admin.php");
    exit();
}

// ユーザー・ToDo取得
try {
    $users = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $message .= "ユーザー取得エラー: " . $e->getMessage() . "<br>";
}

try {
    $sql = "SELECT t.*, u.username FROM todos t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC";
    $todos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $todos = [];
    $message .= "ToDo取得エラー: " . $e->getMessage() . "<br>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>管理者ページ</title>
</head>
<body>
<div class="container">
    <?php if (!empty($message)): ?>
        <div class="info-message"><?= $message ?></div>
    <?php endif; ?>

    <h2>📋 管理者パネル</h2>

    <h3>ユーザー一覧</h3>
    <table border="1">
        <thead>
        <tr>
            <th>ID</th>
            <th>ユーザー名</th>
            <th>登録日時</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="4">登録ユーザーがいません。</td></tr>
        <?php else: ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u["id"]) ?></td>
                    <td><?= htmlspecialchars($u["username"]) ?></td>
                    <td><?= htmlspecialchars($u["created_at"]) ?></td>
                    <td>
                        <a href="admin.php?delete_id=<?= htmlspecialchars($u['id']) ?>" 
                           onclick="return confirm('このユーザーを削除しますか？（ToDoも削除されます）')">削除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <hr>

    <h3>全ユーザーのToDo一覧</h3>
    <table border="1">
        <thead>
        <tr>
            <th>ToDo ID</th>
            <th>ユーザー名</th>
            <th>タイトル</th>
            <th>詳細</th>
            <th>期限</th>
            <th>カテゴリ</th>
            <th>備考</th>
            <th>画像</th>
            <th>状態</th>
            <th>作成日時</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($todos)): ?>
            <tr><td colspan="10">登録されているToDoがありません。</td></tr>
        <?php else: ?>
            <?php foreach ($todos as $todo): ?>
                <tr>
                    <td><?= htmlspecialchars($todo["id"]) ?></td>
                    <td><?= htmlspecialchars($todo["username"]) ?></td>
                    <td><?= nl2br(htmlspecialchars($todo["title"] ?? '')) ?></td>
                    <td><?= nl2br(htmlspecialchars($todo["details"] ?? '')) ?></td>
                    <td><?= htmlspecialchars($todo["due_date"] ?? '—') ?></td>
                    <td><?= htmlspecialchars($todo["category"] ?? '—') ?></td>
                    <td><?= nl2br(htmlspecialchars($todo["plus"] ?? '')) ?></td>
                    <td>
                        <?php if (!empty($todo['image_path'])): ?>
                            <img src="<?= htmlspecialchars($todo['image_path']) ?>" alt="画像" style="max-width: 50px; max-height: 50px;">
                        <?php else: ?>
                            なし
                        <?php endif; ?>
                    </td>
                    <td><?= $todo['is_completed'] ? '完了' : '未完了' ?></td>
                    <td><?= htmlspecialchars($todo["created_at"]) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="footer-links">
        <p><a href="adminlogin.php">管理者ログイン画面に戻る</a></p>
        <p><a href="signin.php">ユーザーログイン画面に戻る</a></p>
    </div>
</div>
</body>
</html>
