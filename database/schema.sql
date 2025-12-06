-- ============================================================================
-- HamroSewa Complete Database Schema
-- MySQL/MariaDB - All Features Included
-- ============================================================================
-- This is the COMPLETE schema file containing all tables and features
-- Use this for new installations or reference
-- For existing databases, use: add_missing_columns.sql
-- ============================================================================

CREATE DATABASE IF NOT EXISTS hamrosewa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hamrosewa;

-- ============================================================================
-- USERS TABLE
-- Stores all user accounts: donors, volunteers, organizations, admins
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'organization', 'admin') DEFAULT 'user',
    is_donor BOOLEAN DEFAULT FALSE,
    is_volunteer BOOLEAN DEFAULT FALSE,
    blood_group VARCHAR(10) NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    district VARCHAR(100) NULL,
    municipality VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    last_donation DATE NULL,
    badges JSON DEFAULT '[]',
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255) NULL,
    reset_token VARCHAR(255) NULL,
    reset_token_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_province (province),
    INDEX idx_district (district),
    INDEX idx_municipality (municipality),
    INDEX idx_blood_group (blood_group),
    INDEX idx_is_donor (is_donor),
    INDEX idx_is_volunteer (is_volunteer),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ORGANIZATIONS TABLE
-- Stores organization information for verified organizations
-- ============================================================================
CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    contact VARCHAR(20) NULL,
    address TEXT NULL,
    verified BOOLEAN DEFAULT FALSE,
    docs JSON DEFAULT '[]',
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_verified (verified),
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BLOOD CAMPS TABLE
-- Stores blood donation camp information organized by organizations
-- ============================================================================
CREATE TABLE IF NOT EXISTS camps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    date_time DATETIME NOT NULL,
    location_lat DECIMAL(10, 8) NULL,
    location_lng DECIMAL(11, 8) NULL,
    location_address TEXT NULL,
    province VARCHAR(100) NULL,
    district VARCHAR(100) NULL,
    municipality VARCHAR(100) NULL,
    needed_groups JSON DEFAULT '[]',
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_date_time (date_time),
    INDEX idx_status (status),
    INDEX idx_province (province),
    INDEX idx_district (district),
    INDEX idx_municipality (municipality),
    INDEX idx_org_id (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CAMP ATTENDEES TABLE
-- Tracks which users are attending which blood donation camps
-- ============================================================================
CREATE TABLE IF NOT EXISTS camp_attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camp_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('donor', 'volunteer') NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'attended', 'absent') DEFAULT 'pending',
    blood_group VARCHAR(10) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_camp_user (camp_id, user_id),
    INDEX idx_status (status),
    INDEX idx_camp_id (camp_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- VOLUNTEER TASKS TABLE
-- Stores volunteer task opportunities created by organizations
-- ============================================================================
CREATE TABLE IF NOT EXISTS volunteer_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    date_time DATETIME NOT NULL,
    location TEXT NULL,
    province VARCHAR(100) NULL,
    district VARCHAR(100) NULL,
    municipality VARCHAR(100) NULL,
    hours_estimated INT DEFAULT 0,
    status ENUM('open', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_date_time (date_time),
    INDEX idx_status (status),
    INDEX idx_province (province),
    INDEX idx_district (district),
    INDEX idx_municipality (municipality),
    INDEX idx_org_id (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TASK APPLICANTS TABLE
-- Tracks which users have applied for which volunteer tasks
-- ============================================================================
CREATE TABLE IF NOT EXISTS task_applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    hours_logged INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES volunteer_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_task_user (task_id, user_id),
    INDEX idx_status (status),
    INDEX idx_task_id (task_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CAMPAIGNS TABLE
-- Stores community campaigns organized by organizations
-- ============================================================================
CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    goal_tokens INT NOT NULL DEFAULT 0,
    raised_tokens INT DEFAULT 0,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_org_id (org_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CAMPAIGN SUPPORTERS TABLE
-- Tracks users who support campaigns with tokens
-- ============================================================================
CREATE TABLE IF NOT EXISTS campaign_supporters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    tokens INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_campaign (campaign_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CAMPAIGN UPDATES TABLE
-- Stores updates posted for campaigns
-- ============================================================================
CREATE TABLE IF NOT EXISTS campaign_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    title VARCHAR(255) NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    INDEX idx_campaign (campaign_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- POLLS TABLE
-- Stores community polls and discussions
-- ============================================================================
CREATE TABLE IF NOT EXISTS polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by INT NOT NULL,
    question TEXT NOT NULL,
    options JSON NOT NULL,
    expires_at DATETIME NULL,
    status ENUM('active', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_expires (expires_at),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- POLL VOTES TABLE
-- Tracks votes cast on polls
-- ============================================================================
CREATE TABLE IF NOT EXISTS poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    user_id INT NOT NULL,
    option_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_poll_user (poll_id, user_id),
    INDEX idx_poll (poll_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- POLL COMMENTS TABLE
-- Stores comments on polls
-- ============================================================================
CREATE TABLE IF NOT EXISTS poll_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_poll (poll_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- NOTIFICATIONS TABLE
-- Stores user notifications
-- ============================================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    meta JSON DEFAULT '{}',
    read_status BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, read_status),
    INDEX idx_created (created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- POST LIKES TABLE
-- Stores likes for feed items (camps, tasks, campaigns, polls)
-- ============================================================================
CREATE TABLE IF NOT EXISTS post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_type ENUM('camp','task','campaign','poll') NOT NULL,
    post_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, post_type, post_id),
    INDEX idx_post (post_type, post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- POST INTERESTS TABLE
-- Tracks users who marked interest on posts/polls
-- ============================================================================
CREATE TABLE IF NOT EXISTS post_interests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_type ENUM('camp','task','campaign','poll') NOT NULL,
    post_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_interest (user_id, post_type, post_id),
    INDEX idx_post_interest (post_type, post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
-- This schema includes all features:
-- ✅ User management with location fields (province, district, municipality)
-- ✅ Blood donation camps with location tracking
-- ✅ Volunteer tasks with location tracking
-- ✅ Community campaigns
-- ✅ Polls and voting
-- ✅ Notifications
-- ✅ All necessary indexes for performance
-- ============================================================================
