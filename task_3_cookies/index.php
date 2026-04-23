<?php
session_start();

/**
 * --- ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ДЛЯ COOKIES ---
 */

function setErrorCookie($errors) {
    setcookie('form_errors', json_encode($errors), time() + 3600, '/');
}

function getErrorCookie() {
    if (isset($_COOKIE['form_errors'])) {
        $errors = json_decode($_COOKIE['form_errors'], true);
        setcookie('form_errors', '', time() - 3600, '/');
        return $errors;
    }
    return [];
}

function setValuesCookie($values) {
    setcookie('form_values', json_encode($values), time() + 3600, '/');
}

function getValuesCookie() {
    if (isset($_COOKIE['form_values'])) {
        $values = json_decode($_COOKIE['form_values'], true);
        setcookie('form_values', '', time() - 3600, '/');
        return $values;
    }
    return [];
}

function saveToCookiePermanent($data) {
    setcookie('saved_form_data', json_encode($data), time() + 365 * 24 * 3600, '/');
}

function getSavedFromCookie() {
    if (isset($_COOKIE['saved_form_data'])) {
        return json_decode($_COOKIE['saved_form_data'], true);
    }
    return [];
}

/**
 * --- ВАЛИДАЦИЯ ---
 */
function validateForm($data) {
    $errors = [];
    $allowedLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];

    if (empty(trim($data['fullname'] ?? '')) || !preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{2,150}$/u', $data['fullname'])) {
        $errors['fullname'] = 'Введите корректное ФИО';
    }
    if (empty(trim($data['phone'] ?? '')) || !preg_match('/^[\+\d\s\-\(\)]{10,20}$/', $data['phone'])) {
        $errors['phone'] = 'Некорректный формат телефона';
    }
    if (empty(trim($data['email'] ?? '')) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email';
    }
    
    if (empty($data['birthdate'])) {
        $errors['birthdate'] = 'Укажите дату рождения';
    }
    if (empty($data['gender'])) {
        $errors['gender'] = 'Выберите пол';
    }
    if (empty($data['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык';
    }
    if (empty($data['contract'])) {
        $errors['contract'] = 'Необходимо согласие';
    }

    return $errors;
}

/**
 * --- ОБРАБОТКА ---
 */
$errors = [];
$displayData = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validateForm($_POST);

    if (empty($errors)) {
        try {
            // 1. Подключение (проверь свои данные: u82560 и пароль)
            $pdo = new PDO('mysql:host=localhost;dbname=u82560;charset=utf8', 'u82560', '3961962');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $pdo->beginTransaction();
            
            // 2. Вставка пользователя
            $stmt = $pdo->prepare("INSERT INTO users (fullname, phone, email, birthdate, gender, biography, contract_accepted) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($_POST['fullname']),
                trim($_POST['phone']),
                trim($_POST['email']),
                $_POST['birthdate'],
                $_POST['gender'],
                trim($_POST['biography'] ?? ''),
                isset($_POST['contract']) ? 1 : 0
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // 3. Вставка языков
            $langStmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
            $insertLangStmt = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
            
            if (!empty($_POST['languages'])) {
                foreach ($_POST['languages'] as $langName) {
                    $langStmt->execute([$langName]);
                    $langId = $langStmt->fetchColumn();
                    if ($langId) {
                        $insertLangStmt->execute([$userId, $langId]);
                    }
                }
            }
            
            $pdo->commit();
            
            // 4. Если всё прошло успешно — сохраняем куки на год и редиректим
            saveToCookiePermanent($_POST);
            $_SESSION['success_msg'] = true;
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['database'] = 'Ошибка сохранения в базу: ' . $e->getMessage();
        }
    }

    // Если есть ошибки (валидации или БД) — сохраняем их и введенные данные в куки
    if (!empty($errors)) {
        setErrorCookie($errors);
        setValuesCookie($_POST);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
            // Здесь должна быть ваша логика вставки в БД (как в предыдущем коде)
            // ...
            
            saveToCookiePermanent($_POST);
            $_SESSION['success_msg'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $errors['database'] = 'Ошибка базы данных';
        }
    }

    if (!empty($errors)) {
        setErrorCookie($errors);
        setValuesCookie($_POST);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
} else {
    $errors = getErrorCookie();
    $tempValues = getValuesCookie();
    $permanentValues = getSavedFromCookie();
    $displayData = !empty($tempValues) ? $tempValues : $permanentValues;

    if (isset($_SESSION['success_msg'])) {
        $success = true;
        unset($_SESSION['success_msg']);
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            display: flex;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        .form-container {
            background: #ffffff;
            width: 100%;
            max-width: 500px;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        h2 {
            margin-top: 0;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            font-size: 22px;
        }
        .field {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: bold;
        }
        input[type="text"], input[type="email"], input[type="tel"], input[type="date"], textarea, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-size: 14px;
        }
        .error-input {
            border-color: #d9534f !important;
            background-color: #fdf7f7;
        }
        .error-text {
            color: #d9534f;
            font-size: 12px;
            margin-top: 4px;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 3px;
            font-size: 14px;
        }
        .alert-success { background: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .alert-error { background: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        
        .radio-group, .checkbox-group {
            display: flex;
            gap: 15px;
            align-items: center;
            font-size: 14px;
        }
        .radio-group label, .checkbox-group label {
            font-weight: normal;
            margin: 0;
        }
        button {
            background-color: #5bc0de;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover { background-color: #46b8da; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Регистрация</h2>

    <?php if ($success): ?>
        <div class="alert alert-success">Данные сохранены.</div>
    <?php endif; ?>

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
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= ($displayData['gender'] ?? '') == 'male' ? 'checked' : '' ?>> М</label>
                <label><input type="radio" name="gender" value="female" <?= ($displayData['gender'] ?? '') == 'female' ? 'checked' : '' ?>> Ж</label>
            </div>
            <?php if (isset($errors['gender'])): ?><div class="error-text"><?= $errors['gender'] ?></div><?php endif; ?>
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

        <div class="field">
            <div class="checkbox-group">
                <input type="checkbox" name="contract" id="c" value="1" <?= isset($displayData['contract']) ? 'checked' : '' ?>>
                <label for="c">Принимаю условия</label>
            </div>
            <?php if (isset($errors['contract'])): ?><div class="error-text"><?= $errors['contract'] ?></div><?php endif; ?>
        </div>

        <button type="submit">Сохранить</button>
    </form>
</div>

</body>
</html>

