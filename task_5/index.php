<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

/**
* --- НАСТРОЙКИ ПОДКЛЮЧЕНИЯ ---
*/
$db_config = [
'host' => 'localhost',
'db' => 'u82560',
'user' => 'u82560',
'pass' => '3961962',
];

function get_db() {
global $db_config;
try {
$pdo = new PDO("mysql:host={$db_config['host']};dbname={$db_config['db']};charset=utf8", $db_config['user'], $db_config['pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
return $pdo;
} catch (PDOException $e) {
die("Ошибка подключения к БД: " . $e->getMessage());
}
}

/**
* --- ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ---
*/
if (isset($_GET['logout'])) {
session_destroy();
header('Location: ' . $_SERVER['PHP_SELF']);
exit;
}

function validateForm($data) {
$errors = [];
if (empty(trim($data['fullname'] ?? '')) || !preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{2,150}$/u', $data['fullname'])) {
$errors['fullname'] = 'Введите корректное ФИО';
}
if (empty(trim($data['phone'] ?? '')) || !preg_match('/^[\+\d\s\-\(\)]{10,20}$/', $data['phone'])) {
$errors['phone'] = 'Некорректный формат телефона';
}
if (empty(trim($data['email'] ?? '')) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
$errors['email'] = 'Введите корректный email';
}
if (empty($data['birthdate'])) $errors['birthdate'] = 'Укажите дату рождения';
if (empty($data['gender'])) $errors['gender'] = 'Выберите пол';
if (empty($data['languages'])) $errors['languages'] = 'Выберите языки';
if (empty($data['contract'])) $errors['contract'] = 'Необходимо согласие';
return $errors;
}

/**
* --- ЛОГИКА АВТОРИЗАЦИИ ---
*/
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
$pdo = get_db();
$stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE login = ?");
$stmt->execute([$_POST['login_field']]);
$user = $stmt->fetch();

if ($user && password_verify($_POST['pass_field'], $user['password_hash'])) {
$_SESSION['user_id'] = $user['id'];
header('Location: ' . $_SERVER['PHP_SELF']);
exit;
} else {
$login_error = 'Неверный логин или пароль';
}
}

/**
* --- ОБРАБОТКА ФОРМЫ (SAVE/UPDATE) ---
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
$errors = validateForm($_POST);

if (empty($errors)) {
try {
$pdo = get_db();
$pdo->beginTransaction();
$is_auth = isset($_SESSION['user_id']);

if ($is_auth) {
// ОБНОВЛЕНИЕ
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("UPDATE users SET fullname=?, phone=?, email=?, birthdate=?, gender=?, biography=? WHERE id=?");
$stmt->execute([
trim($_POST['fullname']), trim($_POST['phone']), trim($_POST['email']),
$_POST['birthdate'], $_POST['gender'], trim($_POST['biography'] ?? ''), $userId
]);
$pdo->prepare("DELETE FROM user_languages WHERE user_id = ?")->execute([$userId]);
$_SESSION['success_msg'] = "Данные успешно обновлены!";
} else {
// РЕГИСТРАЦИЯ
$login = 'user' . rand(1000, 9999);
$pass = substr(md5(uniqid()), 0, 8);
$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (fullname, phone, email, birthdate, gender, biography, contract_accepted, login, password_hash) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)");
$stmt->execute([
trim($_POST['fullname']), trim($_POST['phone']), trim($_POST['email']),
$_POST['birthdate'], $_POST['gender'], trim($_POST['biography'] ?? ''), $login, $hash
]);
$userId = $pdo->lastInsertId();

$_SESSION['new_creds'] = ['login' => $login, 'pass' => $pass];
$_SESSION['success_msg'] = "Вы успешно зарегистрированы!";
}

// Сохранение языков
$langStmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
$insLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
foreach ($_POST['languages'] as $l) {
$langStmt->execute([$l]);
$lid = $langStmt->fetchColumn();
if ($lid) $insLang->execute([$userId, $lid]);
}

$pdo->commit();
header('Location: ' . $_SERVER['PHP_SELF']);
exit;

} catch (Exception $e) {
$pdo->rollBack();
die("Ошибка сохранения: " . $e->getMessage());
}
} else {
$_SESSION['form_errors'] = $errors;
$_SESSION['form_values'] = $_POST;
header('Location: ' . $_SERVER['PHP_SELF']);
exit;
}
}

/**
* --- ПОДГОТОВКА ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ ---
*/
$errors = $_SESSION['form_errors'] ?? [];
$displayData = $_SESSION['form_values'] ?? [];
$success_msg = $_SESSION['success_msg'] ?? null;
$new_creds = $_SESSION['new_creds'] ?? null;

// Если залогинены и форма пуста - тянем из БД
if (isset($_SESSION['user_id']) && empty($displayData)) {
$pdo = get_db();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$displayData = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT l.name FROM user_languages ul JOIN programming_languages l ON ul.language_id = l.id WHERE ul.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$displayData['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Очищаем временные данные сессии
unset($_SESSION['form_errors'], $_SESSION['form_values'], $_SESSION['success_msg'], $_SESSION['new_creds']);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Анкета (Задание 5)</title>
<style>
body { font-family: Arial, sans-serif; background-color: #f4f7f6; display: flex; flex-direction: column; align-items: center; padding: 20px; }
.form-container { background: #fff; width: 100%; max-width: 500px; padding: 30px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px; }
.field { margin-bottom: 15px; }
label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
input[type="text"], input[type="email"], input[type="tel"], input[type="date"], textarea, select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
.error-input { border-color: #d9534f; background-color: #fdf7f7; }
.error-text { color: #d9534f; font-size: 12px; margin-top: 4px; }
.alert { padding: 15px; margin-bottom: 15px; border-radius: 3px; font-size: 14px; border: 1px solid transparent; width: 100%; max-width: 500px; box-sizing: border-box; }
.alert-success { background: #dff0d8; color: #3c763d; border-color: #d6e9c6; }
.alert-creds { background: #fff3cd; color: #856404; border-color: #ffeeba; }
button { background-color: #5bc0de; color: white; border: none; padding: 10px; border-radius: 3px; cursor: pointer; width: 100%; font-size: 16px; }
button:hover { background-color: #46b8da; }
.auth-form { font-size: 13px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
.btn-logout { background: #d9534f; width: auto; padding: 5px 10px; font-size: 12px; float: right; }
</style>
</head>
<body>

<?php if ($success_msg): ?>
<div class="alert alert-success"><?= $success_msg ?></div>
<?php endif; ?>

<?php if ($new_creds): ?>
<div class="alert alert-creds">
<strong>Сохраните данные для входа:</strong><br>
Логин: <code><?= $new_creds['login'] ?></code><br>
Пароль: <code><?= $new_creds['pass'] ?></code>
</div>
<?php endif; ?>

<div class="form-container">
<?php if (!isset($_SESSION['user_id'])): ?>
<div class="auth-form">
<strong>Вход для редактирования:</strong>
<form method="POST" style="display: flex; gap: 5px; margin-top: 10px;">
<input type="text" name="login_field" placeholder="Логин" required>
<input type="password" name="pass_field" placeholder="Пароль" required>
<button type="submit" name="do_login" style="width: 80px;">Войти</button>
</form>
<?php if ($login_error): ?> <div class="error-text"><?= $login_error ?></div> <?php endif; ?>
</div>
<?php else: ?>
<div class="auth-form">
<a href="?logout=1" class="btn-logout" style="color:white; text-decoration:none;">Выйти</a>
Вы вошли как пользователь №<?= $_SESSION['user_id'] ?>
</div>
<?php endif; ?>

<h2><?= isset($_SESSION['user_id']) ? 'Редактирование' : 'Регистрация' ?></h2>
<form method="POST">
<div class="field">
<label>ФИО</label>
<input type="text" name="fullname" value="<?= htmlspecialchars($displayData['fullname'] ?? '') ?>" class="<?= isset($errors['fullname']) ? 'error-input' : '' ?>">
<?php if (isset($errors['fullname'])): ?><div class="error-text"><?= $errors['fullname'] ?></div><?php endif; ?>
</div>

<div class="field">
<label>Телефон</label>
<input type="tel" name="phone" value="<?= htmlspecialchars($displayData['phone'] ?? '') ?>" class="<?= isset($errors['phone']) ? 'error-input' : '' ?>">
<?php if (isset($errors['phone'])): ?><div class="error-text"><?= $errors['phone'] ?></div><?php endif; ?>
</div>

<div class="field">
<label>Email</label>
<input type="email" name="email" value="<?= htmlspecialchars($displayData['email'] ?? '') ?>" class="<?= isset($errors['email']) ? 'error-input' : '' ?>">
<?php if (isset($errors['email'])): ?><div class="error-text"><?= $errors['email'] ?></div><?php endif; ?>
</div>

<div class="field">
<label>Дата рождения</label>
<input type="date" name="birthdate" value="<?= htmlspecialchars($displayData['birthdate'] ?? '') ?>" class="<?= isset($errors['birthdate']) ? 'error-input' : '' ?>">
</div>

<div class="field">
<label>Пол</label>
<input type="radio" name="gender" value="male" <?= ($displayData['gender'] ?? '') == 'male' ? 'checked' : '' ?>> М
<input type="radio" name="gender" value="female" <?= ($displayData['gender'] ?? '') == 'female' ? 'checked' : '' ?>> Ж
</div>

<div class="field">
<label>Любимые языки</label>
<select name="languages[]" multiple size="5" class="<?= isset($errors['languages']) ? 'error-input' : '' ?>">
<?php
$langs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Go'];
$selected = $displayData['languages'] ?? [];
foreach ($langs as $l) {
$sel = in_array($l, $selected) ? 'selected' : '';
echo "<option value=\"$l\" $sel>$l</option>";
}
?>
</select>
</div>

<div class="field">
<label>О себе</label>
<textarea name="biography" rows="3"><?= htmlspecialchars($displayData['biography'] ?? '') ?></textarea>
</div>

<?php if (!isset($_SESSION['user_id'])): ?>
<div class="field">
<input type="checkbox" name="contract" id="c" value="1" <?= isset($displayData['contract']) ? 'checked' : '' ?>>
<label for="c" style="display:inline;">Принимаю условия</label>
<?php if (isset($errors['contract'])): ?><div class="error-text"><?= $errors['contract'] ?></div><?php endif; ?>
</div>
<?php else: ?>
<input type="hidden" name="contract" value="1">
<?php endif; ?>

<button type="submit" name="save_form">Сохранить</button>
</form>
</div>
</body>
</html>
