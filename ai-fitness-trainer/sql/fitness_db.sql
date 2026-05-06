-- ============================================
-- AI Fitness Trainer - Database Schema
-- Run this in phpMyAdmin or MySQL CLI
-- ============================================

CREATE DATABASE IF NOT EXISTS ai_fitness_trainer
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ai_fitness_trainer;

-- -----------------------------------------------
-- Table: users
-- Stores registered user accounts
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,   -- bcrypt hashed
    avatar      VARCHAR(10)         DEFAULT '💪',
    created_at  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------
-- Table: workouts
-- Stores individual workout session results
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS workouts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT             NOT NULL,
    exercise_type   ENUM('squat','pushup') NOT NULL,
    reps            INT             DEFAULT 0,
    accuracy        DECIMAL(5,2)    DEFAULT 0.00,  -- percentage 0–100
    duration_sec    INT             DEFAULT 0,      -- session length in seconds
    calories        DECIMAL(6,2)    DEFAULT 0.00,
    date            TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------
-- Indexes for performance
-- -----------------------------------------------
CREATE INDEX idx_workouts_user  ON workouts(user_id);
CREATE INDEX idx_workouts_date  ON workouts(date);
CREATE INDEX idx_workouts_type  ON workouts(exercise_type);

-- -----------------------------------------------
-- Sample seed data (optional – for testing)
-- -----------------------------------------------
INSERT INTO users (name, email, password, avatar) VALUES
('Demo User', 'demo@fitness.ai', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '🏋️');
-- demo password: "password"
