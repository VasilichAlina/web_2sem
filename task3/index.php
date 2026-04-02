<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета разработчика</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        form { padding: 30px; }
        .form-group {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
        }
        .form-group label {
            width: 200px;
            font-weight: 600;
            color: #333;
            padding-top: 10px;
        }
        .form-group input, .form-group select, .form-group textarea {
            flex: 1;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .radio-group, .checkbox-group {
            flex: 1;
            padding-top: 10px;
        }
        .radio-group label, .checkbox-group label {
            width: auto;
            margin-right: 20px;
            font-weight: normal;
        }
        .error {
            background: #fee;
            border-left: 4px solid #f44336;
            padding: 10px;
            margin-bottom: 20px;
            color: #c62828;
        }
        .success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 10px;
            margin-bottom: 20px;
            color: #2e7d32;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        select[multiple] {
            height: 120px;
        }
        @media (max-width: 600px) {
            .form-group label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Анкета разработчика</h1>
        <p>Заполните форму для регистрации</p>
    </div>
    <form method="POST" action="">
        <?php
        session_start();
        if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])) {
            echo '<div class="error"><strong> Ошибки:</strong><ul>';
            foreach ($_SESSION['errors'] as $error) {
                echo "<li>$error</li>";
            }
            echo '</ul></div>';
            unset($_SESSION['errors']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="success"> ' . $_SESSION['success'] . '</div>';
            unset($_SESSION['success']);
        }
        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);
        ?>
        
        <div class="form-group">
            <label>ФИО *</label>
            <input type="text" name="fullname" required value="<?= htmlspecialchars($old['fullname'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>Телефон *</label>
            <input type="tel" name="phone" required value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>E-mail *</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>Дата рождения *</label>
            <input type="date" name="birthdate" required value="<?= htmlspecialchars($old['birthdate'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>Пол *</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= (($old['gender'] ?? '') == 'male') ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= (($old['gender'] ?? '') == 'female') ? 'checked' : '' ?>> Женский</label>
            </div>
        </div>
        
        <div class="form-group">
            <label>Любимые языки *</label>
            <select name="languages[]" multiple required>
                <?php
                $langs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskell','Clojure','Prolog','Scala','Go'];
                $selected = $old['languages'] ?? [];
                foreach ($langs as $lang) {
                    $sel = in_array($lang, $selected) ? 'selected' : '';
                    echo "<option value=\"$lang\" $sel>$lang</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Биография</label>
            <textarea name="biography" rows="5"><?= htmlspecialchars($old['biography'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label>С контрактом ознакомлен *</label>
            <div class="checkbox-group">
                <label><input type="checkbox" name="contract" value="1" <?= (($old['contract'] ?? '') == '1') ? 'checked' : '' ?>> Да, я согласен</label>
            </div>
        </div>
        
        <button type="submit" name="submit"> Сохранить</button>
    </form>
</div>
</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    session_start();
    $errors = [];
    
    $fullname = trim($_POST['fullname'] ?? '');
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $fullname)) {
        $errors[] = 'ФИО должно содержать только буквы, пробелы и дефисы (до 150 символов)';
    }
    
    $phone = trim($_POST['phone'] ?? '');
    if (!preg_match('/^[\+\d\s\-\(\)]{10,20}$/', $phone)) {
        $errors[] = 'Телефон должен содержать цифры, +, -, пробелы (10-20 символов)';
    }
    
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email';
    }
    
    $birthdate = $_POST['birthdate'] ?? '';
    $dateObj = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$dateObj || $dateObj > new DateTime()) {
        $errors[] = 'Дата рождения должна быть реальной датой в прошлом';
    }
    
    $gender = $_POST['gender'] ?? '';
    if (!in_array($gender, ['male', 'female'])) {
        $errors[] = 'Выберите корректный пол';
    }
    
    $allowedLanguages = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskell','Clojure','Prolog','Scala','Go'];
    $languages = $_POST['languages'] ?? [];
    if (empty($languages)) {
        $errors[] = 'Выберите хотя бы один язык программирования';
    } else {
        foreach ($languages as $lang) {
            if (!in_array($lang, $allowedLanguages)) {
                $errors[] = 'Недопустимый язык программирования';
                break;
            }
        }
    }
    
    $biography = trim($_POST['biography'] ?? '');
    if (strlen($biography) > 5000) {
        $errors[] = 'Биография не должна превышать 5000 символов';
    }
    
    $contract = isset($_POST['contract']) ? 1 : 0;
    if (!$contract) {
        $errors[] = 'Вы должны подтвердить ознакомление с контрактом';
    }
    
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old'] = $_POST;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    try {
        
        $pdo = new PDO('mysql:host=localhost;dbname=form_db;charset=utf8', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO users (fullname, phone, email, birthdate, gender, biography, contract_accepted) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$fullname, $phone, $email, $birthdate, $gender, $biography, $contract]);
        $userId = $pdo->lastInsertId();
        
        $langStmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
        $insertLangStmt = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
        
        foreach ($languages as $langName) {
            $langStmt->execute([$langName]);
            $langId = $langStmt->fetchColumn();
            if ($langId) {
                $insertLangStmt->execute([$userId, $langId]);
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = 'Данные успешно сохранены!';
        unset($_SESSION['old']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['errors'] = ['Ошибка базы данных: ' . $e->getMessage()];
        $_SESSION['old'] = $_POST;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>
