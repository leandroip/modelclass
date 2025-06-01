-- Cria o banco de dados de teste
CREATE DATABASE IF NOT EXISTS test_db;
USE test_db;

-- Cria a tabela de usuários
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(300) NOT NULL,
    email VARCHAR(250) NOT NULL,
    password VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insere um usuário de teste
INSERT INTO users (id, name, email, password) VALUES (
    '1',
    'John Doe',
    'john.doe@example.com',
    'password123'
);
