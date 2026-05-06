<?php
/**
 * --- 1. HTTP-АВТОРИЗАЦИЯ ---
 */
$admin_login = 'admin'; // Логин админа
$admin_pass = '123';   // Пароль админа

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== $admin_login || 
    $_SERVER['PHP_AUTH_PW'] !== $admin_pass) {
    header('WWW-Authenticate: Basic realm="My Realm"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Доступ запрещен.');
}

// Подключение к БД (используйте свои данные)
$db_user = 'u82560';
$db_pass = '3961962';
$pdo = new PDO('mysql:host=localhost;dbname=u82560;charset=utf8', $db_user, $db_pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * --- 2. ОБРАБОТКА ДЕЙСТВИЙ (УДАЛЕНИЕ / ИЗМЕНЕНИЕ) ---
 */
// Удаление пользователя
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: admin.php');
    exit;
}

/**
 * --- 3. ПОЛУЧЕНИЕ ДАННЫХ ---
 */
// Статистика по языкам
$stats = $pdo->query("
    SELECT pl.name, COUNT(ul.user_id) as count 
    FROM programming_languages pl 
    LEFT JOIN user_languages ul ON pl.id = ul.language_id 
    GROUP BY pl.id
")->fetchAll(PDO::FETCH_ASSOC);

// Все пользователи
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f8f9fa; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: white; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #343a40; color: white; }
        .stats-container { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
        .stat-card { background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd; min-width: 120px; text-align: center; }
        .stat-card strong { display: block; font-size: 1.2em; color: #007bff; }
        .btn { padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 0.9em; }
        .btn-edit { background: #ffc107; color: black; }
        .btn-del { background: #dc3545; color: white; }
    </style>
</head>
<body>

    <h1>Панель администратора</h1>

    <h3>Статистика по языкам</h3>
    <div class="stats-container">
        <?php foreach ($stats as $s): ?>
            <div class="stat-card">
                <strong><?= $s['count'] ?></strong>
                <?= htmlspecialchars($s['name']) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <h3>Список пользователей</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ФИО</th>
                <th>Email</th>
                <th>Телефон</th>
                <th>Логин</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['fullname']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['phone']) ?></td>
                    <td><code><?= htmlspecialchars($u['login']) ?></code></td>
                    <td>
                        <a href="index.php?edit_id=<?= $u['id'] ?>" class="btn btn-edit">Ред.</a>
                        <a href="?delete=<?= $u['id'] ?>" class="btn btn-del" onclick="return confirm('Удалить?')">Х</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>
