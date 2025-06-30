<?php
// --- デバッグ用設定 ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
// -----------------------

session_start();

// --- DB接続設定 ---
$dsn = 'mysql:dbname=データベース名;host=localhost';
$user = 'ユーザー名';
$password = 'パスワード';
try {
    $pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
} catch (PDOException $e) {
    error_log("データベース接続エラー (tasklist.php): " . $e->getMessage());
    $_SESSION['error_message'] = "現在、サービスに接続できません。時間をおいてお試しください。";
    header("Location: signin.php");
    exit();
}

// --- ログイン状態の確認 ---
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["username"])) {
    header("Location: signin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- completed_items テーブル作成チェック＆作成 ---
try {
    $checkSql = "SHOW TABLES LIKE 'completed_items'";
    $stmt = $pdo->query($checkSql);
    if ($stmt->rowCount() == 0) {
        // テーブルが存在しなければ作成
        $createSql = "
            CREATE TABLE completed_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                details TEXT,
                priority VARCHAR(10),
                category VARCHAR(50),
                plus TEXT,
                image_path VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createSql);
    }
} catch (PDOException $e) {
    error_log("completed_items テーブル作成エラー: " . $e->getMessage());
    $_SESSION['error_message'] = "システムエラーが発生しました。";
    // 必要に応じて処理停止やリダイレクトなどを実施
}


// --- 「購入済みに移動」処理 ---
if (isset($_POST['move_checked_to_completed'])) {
    try {
        $sql = "SELECT * FROM todos WHERE user_id = :uid AND is_completed = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $user_id]);
        $completed_todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$completed_todos) {
            $_SESSION['error_message'] = "購入済みのアイテムがありません。";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        $insert_sql = "INSERT INTO completed_items (user_id, title, details, priority, category, plus, image_path) 
                       VALUES (:uid, :title, :details, :priority, :category, :plus, :image)";
        $insert_stmt = $pdo->prepare($insert_sql);

        $ids_to_delete = [];

        foreach ($completed_todos as $todo) {
            $insert_stmt->execute([
                ':uid' => $user_id,
                ':title' => $todo['title'],
                ':details' => $todo['details'],
                ':priority' => $todo['priority'],
                ':category' => $todo['category'],
                ':plus' => $todo['plus'],
                ':image' => $todo['image_path']
            ]);
            $ids_to_delete[] = $todo['id'];
        }

        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
        $delete_sql = "DELETE FROM todos WHERE id IN ($placeholders) AND user_id = ?";
        $delete_stmt = $pdo->prepare($delete_sql);
        $delete_stmt->execute(array_merge($ids_to_delete, [$user_id]));

        $_SESSION['message'] = count($ids_to_delete) . "件を購入済みに移動しました。";
    } catch (PDOException $e) {
        error_log("購入済み移動エラー (tasklist.php): " . $e->getMessage());
        $_SESSION['error_message'] = "購入済みへの移動中にエラーが発生しました。";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- リダイレクト処理 (最優先で実行) ---
if (isset($_POST["completed"])) {
    header("Location: completed.php");
    exit();
} elseif (isset($_POST["pending"])) {
    header("Location: pending.php");
    exit();
}

// --- ToDoの完了/未完了切り替え処理 ---
if (isset($_POST['toggle_id'])) {
    $id = (int)$_POST['toggle_id'];

    try {
        $sql = "SELECT is_completed FROM todos WHERE id = :id AND user_id = :uid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':uid' => $user_id]);
        $todo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($todo) {
            $new_status = $todo['is_completed'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE todos SET is_completed = :status WHERE id = :id AND user_id = :uid");
            $stmt->execute([':status' => $new_status, ':id' => $id, ':uid' => $user_id]);
            $_SESSION['message'] = "ToDoのステータスを更新しました！";
        } else {
            $_SESSION['error_message'] = "指定されたToDoが見つかりませんでした。";
        }
    } catch (PDOException $e) {
        error_log("ToDo切り替えエラー (tasklist.php): " . $e->getMessage());
        $_SESSION['error_message'] = "ToDoのステータス更新中にエラーが発生しました。";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- 新規ToDo追加処理 ---
if (isset($_POST['add'])) {
    $title = $_POST['title'] ?? '';
    $details = $_POST['details'] ?? '';
    $priority = $_POST['priority'] ?? '★★';
    $category = $_POST['category'] ?? '';
    $plus = $_POST['plus'] ?? '';
    $image_path = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_tmp_name = $_FILES['image']['tmp_name'];
        $file_name = basename($_FILES['image']['name']);
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_file_name = uniqid() . '.' . $file_ext;
        $target_file = $upload_dir . $unique_file_name;

        if (move_uploaded_file($file_tmp_name, $target_file)) {
            $image_path = $target_file;
        } else {
            $_SESSION['error_message'] = "画像のアップロードに失敗しました。";
        }
    }

    if ($title !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO todos (user_id, title, details, priority, category, plus, is_completed, image_path) VALUES (:uid, :t, :d, :priority, :cat, :plus, 0, :image_path)");
            $stmt->execute([
                ':uid' => $user_id,
                ':t' => $title,
                ':d' => $details,
                ':priority' => $priority,
                ':cat' => $category,
                ':plus' => $plus,
                ':image_path' => $image_path
            ]);
            $_SESSION['message'] = "ToDoが正常に追加されました！";
        } catch (PDOException $e) {
            error_log("ToDo追加エラー (tasklist.php): " . $e->getMessage());
            $_SESSION['error_message'] = "ToDoの追加に失敗しました。";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['error_message'] = "タイトルは必須です。";
    }
}

// --- ToDo削除処理 ---
if (isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM todos WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $delete_id, ':uid' => $user_id]);
        $_SESSION['message'] = "ToDoを削除しました。";
    } catch (PDOException $e) {
        error_log("ToDo削除エラー (tasklist.php): " . $e->getMessage());
        $_SESSION['error_message'] = "ToDoの削除に失敗しました。";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- ToDo一覧取得 ---
$todos = [];
try {
    $sql = "SELECT * FROM todos WHERE user_id = :user_id ORDER BY is_completed ASC, FIELD(priority, '★★★', '★★', '★') DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("ToDo一覧取得エラー (tasklist.php): " . $e->getMessage());
    $_SESSION['error_message'] = "ToDoリストの取得中にエラーが発生しました。";
    $todos = [];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>買い物リスト</title>
  <style>
/* 全体デザイン：アットホームなベージュ系に変更 */
body {
    font-family: 'Segoe UI', sans-serif;
    background-color: #fdf5e6; /* 薄いベージュ */
    padding: 20px;
    color: #4b2e1e; /* ブラウン */
    font-size: 14px; /* 小さめに調整 */
}

.container {
    max-width: 900px;
    margin: auto;
    background-color: #fffaf0; /* フローラルホワイト系 */
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 8px rgba(165, 42, 42, 0.1);
}

h2, h3 {
    color: #8b4513; /* サドルブラウン */
}

input[type="text"],
textarea,
select {
    width: 100%;
    padding: 6px 10px;
    font-size: 14px;
    margin-top: 5px;
    margin-bottom: 12px;
    border: 1px solid #d2b48c;
    border-radius: 6px;
    box-sizing: border-box;
}

button {
    background-color: #cd853f; /* ペルー色（茶系） */
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s;
    font-size: 14px;
}

button:hover {
    background-color: #a0522d; /* sienna */
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    font-size: 13px; /* 表も少し小さめに */
}

thead {
    background-color: #deb887; /* バーリーウッド */
    color: white;
}

th, td {
    padding: 10px;
    text-align: center;
    border-bottom: 1px solid #e6ccb2;
}

tr:hover {
    background-color: #f5f5dc; /* ベージュ系 */
}

a {
    color: #a0522d;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

.message.success {
    background-color: #f0e6d6;
    color: #4b2e1e;
    border: 1px solid #e6ccb2;
}

.message.error {
    background-color: #ffe4e1;
    color: #8b0000;
    border: 1px solid #f5c6cb;
}


    
  </style>
</head>
<body>
<div class="container">

<?php if (isset($_SESSION['message'])): ?>
    <p class="message success"><?= htmlspecialchars($_SESSION['message']) ?></p>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <p class="message error"><?= htmlspecialchars($_SESSION['error_message']) ?></p>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<h3>買いたいものを追加👜</h3>
<form method="post" enctype="multipart/form-data">
    <label for="title">商品名：</label>
    <input type="text" id="title" name="title" required placeholder="例:卵"><br>

    <label for="details">購入場所：</label>
    <textarea id="details" name="details" placeholder="例:スーパー"></textarea><br>

    <label for="priority">優先度：</label>
    <select id="priority" name="priority">
        <option value="--" selected>-- 選択してください --</option>
        <option value="★★★">★★★</option>
        <option value="★★">★★</option>
        <option value="★">★</option>
    </select><br>

    <label for="category">カテゴリ：</label>
    <select id="category" name="category">
        <option value="">-- 選択してください --</option>
        <option value="食品">食品</option>
        <option value="日用品">日用品</option>
        <option value="雑貨">雑貨</option>
        <option value="化粧品">化粧品</option>
        <option value="その他">その他</option>
    </select><br>

    <label for="plus">備考：</label>
    <textarea id="plus" name="plus" placeholder="例:6個入りを1パック"></textarea><br>

    <label for="image">どんな商品？：</label>
    <input type="file" id="image" name="image" accept="image/*"><br>

    <button type="submit" name="add">追加</button>
</form>

<hr>

<!-- 「購入済みに移動」ボタン -->
<form method="post" onsubmit="return confirm('購入した✅にチェックがついているものをまとめて購入済みに移動します。よろしいですか？');">
    <button type="submit" name="move_checked_to_completed">✅をまとめて購入済み一覧に移動</button>
</form>

<h2>買い物リスト📝</h2>

<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>購入したら✅</th>
            <th>商品名</th>
            <th>購入場所</th>
            <th>優先度</th>
            <th>カテゴリ</th>
            <th>備考</th>
            <th>画像</th>
            <th>リスト削除</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($todos)): ?>
        <tr><td colspan="8" style="text-align: center;">まだ購入リストが記入されていません。</td></tr>
    <?php else: ?>
        <?php foreach ($todos as $todo): ?>
        <tr>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="toggle_id" value="<?= htmlspecialchars($todo['id']) ?>">
                    <input type="checkbox" name="is_completed" onchange="this.form.submit()" <?= $todo['is_completed'] ? 'checked' : '' ?>>
                </form>
            </td>
            <td><?= htmlspecialchars($todo['title']) ?></td>
            <td><?= nl2br(htmlspecialchars($todo['details'] ?? '')) ?></td>
            <td><?= htmlspecialchars($todo['priority'] ?? '—') ?></td>
            <td><?= htmlspecialchars($todo['category'] ?? '—') ?></td>
            <td><?= nl2br(htmlspecialchars($todo['plus'] ?? '')) ?></td>
            <td>
                <?php if (!empty($todo['image_path'])): ?>
                    <img src="<?= htmlspecialchars($todo['image_path']) ?>" alt="関連画像" class="todo-image-thumbnail" data-fullsize-src="<?= htmlspecialchars($todo['image_path']) ?>" style="max-width: 100px; max-height: 100px; display: block; cursor: pointer;">
                <?php else: ?>
                    画像なし
                <?php endif; ?>
            </td>
            <td>
                <form method="post" onsubmit="return confirm('本当に削除しますか？');">
                    <input type="hidden" name="delete_id" value="<?= htmlspecialchars($todo['id']) ?>">
                    <button type="submit">削除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<form method="get" action="completed_items.php" style="margin-bottom: 20px;">
  <button type="submit">購入済み一覧へ</button>
</form>

<a href="signin.php">ログアウト</a>
</div>

<!-- モーダル表示用 -->
<div id="imageModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9);">
  <span id="closeModal" style="position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
  <img class="modal-content" id="fullSizeImage" style="margin: auto; display: block; max-width: 90%; max-height: 90%; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
  <div id="imageCaption" style="margin: auto; display: block; width: 80%; max-width: 700px; text-align: center; color: #ccc; padding: 10px 0; height: 150px; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('imageModal');
    const fullSizeImage = document.getElementById('fullSizeImage');
    const closeModal = document.getElementById('closeModal');
    const todoImages = document.querySelectorAll('.todo-image-thumbnail');

    todoImages.forEach(image => {
        image.addEventListener('click', function() {
            fullSizeImage.src = this.dataset.fullsizeSrc;
            modal.style.display = 'block';
        });
    });

    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>
</body>
</html>
