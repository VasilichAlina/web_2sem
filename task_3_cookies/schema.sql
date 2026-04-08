-- Создание базы данных
CREATE DATABASE IF NOT EXISTS u82560;
USE u82560;

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    birthdate DATE NOT NULL,
    gender ENUM('male', 'female') NOT NULL,
    biography TEXT,
    contract_accepted BOOLEAN NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Справочник языков
CREATE TABLE IF NOT EXISTS programming_languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

-- Заполнение языков
INSERT INTO programming_languages (name) VALUES
('Pascal'), ('C'), ('C++'), ('JavaScript'), ('PHP'),
('Python'), ('Java'), ('Haskell'), ('Clojure'), 
('Prolog'), ('Scala'), ('Go')
ON DUPLICATE KEY UPDATE name = name;

-- Связь пользователей с языками
CREATE TABLE IF NOT EXISTS user_languages (
    user_id INT NOT NULL,
    language_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (language_id) REFERENCES programming_languages(id),
    PRIMARY KEY (user_id, language_id)
);
