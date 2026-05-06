<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// Данные подключения
$db_user = 'u82560';
$db_pass = '3961962';
$db_name = 'u82560';

/**
 * Вспомогательные функции
 */
function get_db() {
    global $db_user, $db_pass, $db_name;
    $pdo = new PDO("mysql:host=localhost;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

// Выход из аккаунта
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/**
 * ЛОГИКА АВТОРИЗАЦИИ (LOGIN)
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
 * ПОЛУЧЕНИЕ ДАННЫХ ДЛЯ ФОРМЫ
 */
$displayData = [];
$is_auth = isset($_SESSION['user_id']);

if ($is_auth) {
    // Если залогинены — берем данные из БД для предзаполнения
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $displayData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Получаем языки
    $stmt = $pdo->prepare("SELECT l.name FROM user_languages ul JOIN programming_languages l ON ul.language_id = l.id WHERE ul.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $displayData['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * СОХРАНЕНИЕ / РЕГИСТРАЦИЯ
 */
$errors = [];
$credentials = []; // Для отображения нового логина/пароля

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    // Валидация (упрощенная для примера)
    if (empty($_POST['fullname'])) $errors['fullname'] = 'Введите ФИО';
    // ... тут добавьте остальные проверки из вашего исходника ...

    if (empty($errors)) {
        try {
            $pdo = get_db();
            $pdo->beginTransaction();

            if ($is_auth) {
                /** ОБНОВЛЕНИЕ **/
                $userId = $_SESSION['user_id'];
                $stmt = $pdo->prepare("UPDATE users SET fullname=?, phone=?, email=?, birthdate=?, gender=?, biography=? WHERE id=?");
                $stmt->execute([$_POST['fullname'], $_POST['phone'], $_POST['email'], $_POST['birthdate'], $_POST['gender'], $_POST['biography'], $userId]);
                
                // Очищаем старые языки и пишем новые
                $pdo->prepare("DELETE FROM user_languages WHERE user_id = ?")->execute([$userId]);
            } else {
                /** ПЕРВИЧНАЯ РЕГИСТРАЦИЯ **/
                $login = 'user_' . substr(uniqid(), -5);
                $pass = substr(md5(microtime()), 0, 8);
                $hash = password_hash($pass, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO users (fullname, phone, email, birthdate, gender, biography, login, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['fullname'], $_POST['phone'], $_POST['email'], $_POST['birthdate'], $_POST['gender'], $_POST['biography'], $login, $hash]);
                $userId = $pdo->lastInsertId();
                
                $credentials = ['login' => $login, 'pass' => $pass];
            }

            // Сохранение языков
            if (!empty($_POST['languages'])) {
                $langStmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
                $insLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
                foreach ($_POST['languages'] as $l) {
                    $langStmt->execute([$l]);
                    $lid = $langStmt->fetchColumn();
                    if ($lid) $insLang->execute([$userId, $lid]);
                }
            }

            $pdo->commit();
            
            if (!$is_auth) {
                $_SESSION['new_creds'] = $credentials;
            }
            $_SESSION['success_msg'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = "Ошибка: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Задание 5: JWT/Sessions</title>
    <style>
        /* Ваши стили + немного новых */
        body { font-family: sans-serif; background: #f0f2f5; display: flex; flex-direction: column; align-items: center; padding: 20px; }
        .form-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 400px; margin-bottom: 20px; }
        .auth-box { font-size: 0.9em; background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid #1877f2; }
        .field { margin-bottom: 15px; }
        input, select, textarea { width: 100%; box-sizing: border-box; padding: 8px; margin-top: 5px; }
        button { width: 100%; padding: 10px; background: #28a745; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .btn-login { background: #007bff; margin-top: 10px; }
        .error { color: red; font-size: 12px; }
        .logout { text-align: right; margin-bottom: 10px; }
    </style>
</head>
<body>

    <?php if (!$is_auth): ?>
    <div class="form-container">
        <h3>Вход для редактирования</h3>
        <form method="POST">
            <input type="text" name="login_field" placeholder="Логин" required>
            <input type="password" name="pass_field" placeholder="Пароль" required>
            <button type="submit" name="do_login" class="btn-login">Войти</button>
            <?php if($login_error): ?> <p class="error"><?= $login_error ?></p> <?php endif; ?>
        </form>
    </div>
    <?php else: ?>
        <div class="form-container logout">
            Вы вошли как <strong>ID: <?= $_SESSION['user_id'] ?></strong> | <a href="?logout=1">Выйти</a>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['new_creds'])): ?>
        <div class="form-container auth-box">
            <strong>Регистрация успешна!</strong><br>
            Ваш логин: <code><?= $_SESSION['new_creds']['login'] ?></code><br>
            Ваш пароль: <code><?= $_SESSION['new_creds']['pass'] ?></code><br>
            <em>Запишите их для редактирования данных в будущем.</em>
        </div>
        <?php unset($_SESSION['new_creds']); ?>
    <?php endif; ?>

    <div class="form-container">
        <h2><?= $is_auth ? 'Редактирование данных' : 'Регистрация' ?></h2>
        
        <form method="POST">
            <div class="field">
                <label>ФИО</label>
                <input type="text" name="fullname" value="<?= htmlspecialchars($displayData['fullname'] ?? '') ?>">
                <?php if(isset($errors['fullname'])) echo "<div class='error'>{$errors['fullname']}</div>"; ?>
            </div>

            <div class="field">
                <label>Телефон</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($displayData['phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($displayData['email'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Дата рождения</label>
                <input type="date" name="birthdate" value="<?= htmlspecialchars($displayData['birthdate'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Пол</label>
                <input type="radio" name="gender" value="male" <?= ($displayData['gender'] ?? '') == 'male' ? 'checked' : '' ?>> М
                <input type="radio" name="gender" value="female" <?= ($displayData['gender'] ?? '') == 'female' ? 'checked' : '' ?>> Ж
            </div>

            <div class="field">
                <label>Любимые языки</label>
                <select name="languages[]" multiple size="5">
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
                <textarea name="biography"><?= htmlspecialchars($displayData['biography'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="save_form">
                <?= $is_auth ? 'Обновить данные' : 'Зарегистрироваться' ?>
            </button>
        </form>
    </div>

</body>
</html>
