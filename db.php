<?php
// db.php
// 데이터베이스 접속만 담당하는 파일

$host = 'localhost';
$db   = 'DBNAME';    // DB 이름
$user = 'DBUSERNAME';        // DB 유저
$pass = 'DBUSERPASSWORD';    // DB 비밀번호

try {
    // DSN (Data Source Name)
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
} catch (PDOException $e) {
    echo "DB 연결 실패: " . $e->getMessage();
    exit;
}
