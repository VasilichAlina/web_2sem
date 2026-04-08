<?php
session_start();

// --- Функция для сохранения ошибок в Cookies ---
function setErrorCookie($errors) {
    setcookie('form_errors', json_encode($errors), time() + 3600, '/'); // на 1 час
}

// --- Функция для получения ошибок из Cookies ---
function getErrorCookie() {
    if (isset($_COOKIE['form_errors'])) {
        $errors = json_decode($_COOKIE['form_errors'], true);
        setcookie('form_errors', '', time() - 3600, '/'); // удаляем после прочтения
        return $errors;
    }
    return [];
}

// --- Функция для сохранения данных в Cookies (на год) ---
function saveToCookie($data) {
    setcookie('saved_form_data', json_encode($data), time() + 365 * 24 * 3600, '/');
}

// --- Функция для получения сохранённых данных из Cookies ---
function getSavedFromCookie() {
    if (isset($_COOKIE['saved_form_data'])) {
        return json_decode($_COOKIE['saved_form_data'], true);
    }
    return [];
}

// --- Валидация через регулярные выражения ---
function validateForm($data) {
    $errors = [];
    
    // 1. ФИО: только буквы, пробелы, дефисы (2-150 символов)
    $fullname = trim($data['fullname'] ?? '');
    if (empty($fullname)) {
        $errors['fullname'] = 'ФИО обязательно для заполнения';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{2,150}$/u', $fullname)) {
        $errors['fullname'] = 'ФИО может содержать только буквы, пробелы и дефисы (2-150 символов)';
    }
    
    // 2. Телефон: цифры, +, -, пробелы, скобки (10-20 символов)
    $phone = trim($data['phone'] ?? '');
    if (empty($phone)) {
        $errors['phone'] = 'Телефон обязателен';
    } elseif (!preg_match('/^[\+\d\s\-\(\)]{10,20}$/', $phone)) {
        $errors['phone'] = 'Телефон может содержать только цифры, +, -, пробелы и скобки (10-20 символов)';
    }
    
    // 3. Email: стандартная валидация
    $email = trim($data['email'] ?? '');
    if (empty($email)) {
        $errors['email'] = 'Email обязателен';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email (например, name@domain.com)';
    }
    
    // 4. Дата рождения: формат YYYY-MM-DD и не в будущем
    $birthdate = $data['birthdate'] ?? '';
    if (empty($birthdate)) {
        $errors['birthdate'] = 'Дата рождения обязательна';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        $errors['birthdate'] = 'Дата должна быть в формате ГГГГ-ММ-ДД';
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $birthdate);
        if (!$dateObj || $dateObj > new DateTime()) {
            $errors['birthdate'] = 'Дата рождения не может быть в будущем';
        }
    }
    
    // 5. Пол: только male/female
    $gender = $data['gender'] ?? '';
    if (empty($gender)) {
        $errors['gender'] = 'Выберите пол';
    } elseif (!preg_match('/^(male|female)$/', $gender)) {
        $errors['gender'] = 'Некорректное значение пола';
    }
    
    // 6. Языки: массив, каждый язык из списка
    $allowedLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
    $languages = $data['languages'] ?? [];
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования';
    } else {
        foreach ($languages as $lang) {
            if (!in_array($lang, $allowedLanguages)) {
                $errors['languages'] = 'Выбран недопустимый язык программирования';
                break;
            }
        }
    }
    
    // 7. Биография: максимум 5000 символов
    $biography = trim($data['biography'] ?? '');
    if (strlen($biography) > 5000) {
        $errors['biography'] = 'Биография не должна превышать 5000 символов';
    }
    
    // 8. Контракт: должен быть отмечен
    $contract = isset($data['contract']) && $data['contract'] == '1';
    if (!$contract) {
        $errors['contract'] = 'Вы должны подтвердить ознакомление с контрактом';
    }
    
    return $errors;
}

// --- ОБРАБОТКА ФОРМЫ ---
$errors = [];
$oldData = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Валидируем данные
    $errors = validateForm($_POST);
    
    if (empty($errors)) {
        // Если ошибок нет - сохраняем в БД и в Cookies на год
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=u82560;charset=utf8', 'u82560', '3961962');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $pdo->beginTransaction();
            
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
            
            $langStmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
            $insertLangStmt = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
            
            foreach ($_POST['languages'] as $langName) {
                $langStmt->execute([$langName]);
                $langId = $langStmt->fetchColumn();
                if ($langId) {
                    $insertLangStmt->execute([$userId, $langId]);
                }
            }
            
            $pdo->commit();
            
            // Сохраняем данные в Cookies на год
            $dataToSave = [
                'fullname' => trim($_POST['fullname']),
                'phone' => trim($_POST['phone']),
                'email' => trim($_POST['email']),
                'birthdate' => $_POST['birthdate'],
                'gender' => $_POST['gender'],
                'biography' => trim($_POST['biography'] ?? ''),
                'languages' => $_POST['languages']
            ];
            saveToCookie($dataToSave);
            
            $success = true;
            $_POST = []; // очищаем POST
            
        } catch (PDOException $e) {
            $errors['database'] = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        // Сохраняем ошибки в Cookies и старые данные для отображения
        setErrorCookie($errors);
        $oldData = $_POST;
        // Перезагружаем методом GET
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// При GET-запросе получаем ошибки из Cookies
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $errors = getErrorCookie();
    // Получаем сохранённые данные из Cookies (если есть)
    $savedData = getSavedFromCookie();
}

// Данные для отображения в форме (приоритет: старые данные > сохранённые в Cookies)
$displayData = !empty($oldData) ? $oldData : ($savedData ?? []);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета разработчика (с Cookies)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
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
        .header p { opacity: 0.9; }
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
            transition: all 0.3s;
        }
        .form-group input.error-field, .form-group select.error-field, .form-group textarea.error-field {
            border-color: #f44336;
            background-color: #ffebee;
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
        select[multiple] {
            height: 130px;
        }
        .error-message {
            color: #f44336;
            font-size: 12px;
            margin-top: 5px;
            width: 100%;
            margin-left: 200px;
        }
        .error-list {
            background: #fee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .error-list ul {
            margin-left: 20px;
            color: #c62828;
        }
        .success-message {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
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
            transition: transform 0.2s;
        }
        button:hover { transform: translateY(-2px); }
        @media (max-width: 768px) {
            .form-group label { width: 100%; margin-bottom: 5px; }
            .error-message { margin-left: 0; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Анкета разработчика</h1>
        <p>Заполните форму для регистрации (данные сохраняются в Cookies)</p>
    </div>
    
    <form method="POST" action="">
        <?php if (!empty($errors) && !isset($errors['database'])): ?>
            <div class="error-list">
                <strong> Пожалуйста, исправьте следующие ошибки:</strong>
                <ul>
                    <?php foreach ($errors as $field => $error): ?>
                        <?php if ($field != 'database'): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors['database'])): ?>
            <div class="error-list">
                <strong> Ошибка базы данных:</strong>
                <ul><li><?= htmlspecialchars($errors['database']) ?></li></ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                 Данные успешно сохранены! Данные формы сохранены в Cookies на год.
            </div>
        <?php endif; ?>
        
        <!-- Поле ФИО -->
        <div class="form-group">
            <label>ФИО *</label>
            <input type="text" name="fullname" 
                   value="<?= htmlspecialchars($displayData['fullname'] ?? '') ?>"
                   class="<?= isset($errors['fullname']) ? 'error-field' : '' ?>"
                   placeholder="Иванов Иван Иванович">
        </div>
        <?php if (isset($errors['fullname'])): ?>
            <div class="error-message"><?= $errors['fullname'] ?></div>
        <?php endif; ?>
        
        <!-- Поле Телефон -->
        <div class="form-group">
            <label>Телефон *</label>
            <input type="tel" name="phone" 
                   value="<?= htmlspecialchars($displayData['phone'] ?? '') ?>"
                   class="<?= isset($errors['phone']) ? 'error-field' : '' ?>"
                   placeholder="+7 (123) 456-78-90">
        </div>
        <?php if (isset($errors['phone'])): ?>
            <div class="error-message"><?= $errors['phone'] ?></div>
        <?php endif; ?>
        
        <!-- Поле Email -->
        <div class="form-group">
            <label>E-mail *</label>
            <input type="email" name="email" 
                   value="<?= htmlspecialchars($displayData['email'] ?? '') ?>"
                   class="<?= isset($errors['email']) ? 'error-field' : '' ?>"
                   placeholder="ivan@example.com">
        </div>
        <?php if (isset($errors['email'])): ?>
            <div class="error-message"><?= $errors['email'] ?></div>
        <?php endif; ?>
        
        <!-- Поле Дата рождения -->
        <div class="form-group">
            <label>Дата рождения *</label>
            <input type="date" name="birthdate" 
                   value="<?= htmlspecialchars($displayData['birthdate'] ?? '') ?>"
                   class="<?= isset($errors['birthdate']) ? 'error-field' : '' ?>">
        </div>
        <?php if (isset($errors['birthdate'])): ?>
            <div class="error-message"><?= $errors['birthdate'] ?></div>
        <?php endif; ?>
        
        <!-- Поле Пол -->
        <div class="form-group">
            <label>Пол *</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="gender" value="male" 
                           <?= (($displayData['gender'] ?? '') == 'male') ? 'checked' : '' ?>
                           class="<?= isset($errors['gender']) ? 'error-field' : '' ?>"> Мужской
                </label>
                <label>
                    <input type="radio" name="gender" value="female" 
                           <?= (($displayData['gender'] ?? '') == 'female') ? 'checked' : '' ?>
                           class="<?= isset($errors['gender']) ? 'error-field' : '' ?>"> Женский
                </label>
            </div>
        </div>
        <?php if (isset($errors['gender'])): ?>
            <div class="error-message"><?= $errors['gender'] ?></div>
        <?php endif; ?>
        
        <!-- Поле Языки программирования -->
        <div class="form-group">
            <label>Любимые языки *</label>
            <select name="languages[]" multiple 
                    class="<?= isset($errors['languages']) ? 'error-field' : '' ?>">
                <?php
                $langs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskell','Clojure','Prolog','Scala','Go'];
                $selectedLangs = $displayData['languages'] ?? [];
                foreach ($langs as $lang) {
                    $selected = (in_array($lang, $selectedLangs)) ? 'selected' : '';
                    echo "<option value=\"$lang\" $selected>$lang</option>";
                }
                ?>
            </select>
            <div><small>Зажмите Ctrl для выбора нескольких языков</small></div>
        </div>
        <?php if (isset($errors['languages'])): ?>
            <div class="error-message"><?= $errors['languages'] ?></div>
        <?php endif; ?>
        
        <!-- Поле Биография -->
        <div class="form-group">
            <label>Биография</label>
            <textarea name="biography" rows="5" 
                      class="<?= isset($errors['biography']) ? 'error-field' : '' ?>"
                      placeholder="Расскажите немного о себе..."><?= htmlspecialchars($displayData['biography'] ?? '') ?></textarea>
        </div>
        <?php if (isset($errors['biography'])): ?>
            <div class="error-message"><?= $errors['biography'] ?></div>
        <?php endif; ?>
        
        <!-- Поле Контракт -->
        <div class="form-group">
            <label>С контрактом ознакомлен *</label>
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="contract" value="1" 
                           <?= (isset($displayData['contract']) && $displayData['contract'] == '1') ? 'checked' : '' ?>
                           class="<?= isset($errors['contract']) ? 'error-field' : '' ?>"> 
                    Да, я ознакомлен(а) с условиями контракта
                </label>
            </div>
        </div>
        <?php if (isset($errors['contract'])): ?>
            <div class="error-message"><?= $errors['contract'] ?></div>
        <?php endif; ?>
        
        <button type="submit">Сохранить</button>
    </form>
</div>
</body>
</html>
