<?php
    // DB接続設定
$dsn = 'mysql:dbname=データベース名;host=localhost';
$user = 'ユーザー名';
$password = 'パスワード';
$pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));
    $sql = 'SELECT * FROM todos ORDER BY id';
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();
    echo "id,user_id,title,details,due_date,category,is_completed";
    //ループして、取得したデータを表示
    foreach ($results as $row){
    //$rowの中にはテーブルのカラム名が入る
    echo $row['id'].',';
    echo $row['user_id'].',';
    echo $row['title'].',';
    echo $row['details'].',';
    echo $row['due_date'].',';
    echo $row['category'].',';
    echo $row['is_completed'].'<br>';
    echo "<hr>";
    }
?>
