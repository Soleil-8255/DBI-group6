-- ============================================================================
-- COMP1044 Internship Result Management System - Final Database Implementation
-- Group 6: Su Lei, Yang Qianqian, Zhang Hanwen
-- Advanced features - triggers for automatic total mark calculation and a view for assessor workload analysis.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS internship_system;
USE internship_system;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- 1. DROP EXISTING STRUCTURES (For safe re-import)
-- ---------------------------------------------------------
DROP VIEW IF EXISTS Assessor_Workload_View;
DROP TRIGGER IF EXISTS calc_total_marks_insert;
DROP TRIGGER IF EXISTS calc_total_marks_update;
DROP TABLE IF EXISTS Assessments;
DROP TABLE IF EXISTS Audit_Logs;
DROP TABLE IF EXISTS Internships;
DROP TABLE IF EXISTS Companies;
DROP TABLE IF EXISTS Students;
DROP TABLE IF EXISTS Programmes;
DROP TABLE IF EXISTS Schools;
DROP TABLE IF EXISTS States;
DROP TABLE IF EXISTS Users;

-- ---------------------------------------------------------
-- 2. DDL: TABLE CREATION (9 tables with relationships)
-- ---------------------------------------------------------
CREATE TABLE Schools (
    school_id INT AUTO_INCREMENT PRIMARY KEY,
    school_name VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE Programmes (
    prog_id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL, 
    prog_name VARCHAR(150) NOT NULL,
    FOREIGN KEY (school_id) REFERENCES Schools(school_id) ON DELETE CASCADE
);

CREATE TABLE States (
    state_id INT AUTO_INCREMENT PRIMARY KEY,
    state_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Assessor', 'Student') NOT NULL, 
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE Students (
    student_id VARCHAR(20) PRIMARY KEY,
    user_id INT UNIQUE, 
    prog_id INT NOT NULL,
    cohort_year YEAR NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (prog_id) REFERENCES Programmes(prog_id)
);

CREATE TABLE Companies (
    company_id INT AUTO_INCREMENT PRIMARY KEY,
    state_id INT NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    FOREIGN KEY (state_id) REFERENCES States(state_id)
);

CREATE TABLE Internships (
    internship_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    company_id INT NOT NULL,
    assessor_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    status ENUM('Ongoing', 'Completed') DEFAULT 'Ongoing',
    FOREIGN KEY (student_id) REFERENCES Students(student_id),
    FOREIGN KEY (company_id) REFERENCES Companies(company_id),
    FOREIGN KEY (assessor_id) REFERENCES Users(user_id)
);

CREATE TABLE Audit_Logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT, 
    action_type VARCHAR(50),
    description TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE SET NULL
);

CREATE TABLE Assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    internship_id INT NOT NULL UNIQUE, 
    score_tasks DECIMAL(5,2) CHECK (score_tasks BETWEEN 0 AND 100),
    score_safety DECIMAL(5,2) CHECK (score_safety BETWEEN 0 AND 100),
    score_theory DECIMAL(5,2) CHECK (score_theory BETWEEN 0 AND 100),
    score_report DECIMAL(5,2) CHECK (score_report BETWEEN 0 AND 100),
    score_language DECIMAL(5,2) CHECK (score_language BETWEEN 0 AND 100),
    score_lifelong DECIMAL(5,2) CHECK (score_lifelong BETWEEN 0 AND 100),
    score_proj_mgmt DECIMAL(5,2) CHECK (score_proj_mgmt BETWEEN 0 AND 100),
    score_time_mgmt DECIMAL(5,2) CHECK (score_time_mgmt BETWEEN 0 AND 100),
    total_mark DECIMAL(5,2),
    comments TEXT NOT NULL, 
    FOREIGN KEY (internship_id) REFERENCES Internships(internship_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 3. DDL: TRIGGERS AND VIEWS 
-- ---------------------------------------------------------
DELIMITER //

-- trigger 1: Handle initial grade entry (INSERT) - Auto-calculate total_mark
CREATE TRIGGER calc_total_marks_insert BEFORE INSERT ON Assessments FOR EACH ROW
BEGIN
    SET NEW.total_mark = (
        NEW.score_tasks * 0.10 + NEW.score_safety * 0.10 + 
        NEW.score_theory * 0.10 + NEW.score_report * 0.15 + 
        NEW.score_language * 0.10 + NEW.score_lifelong * 0.15 + 
        NEW.score_proj_mgmt * 0.15 + NEW.score_time_mgmt * 0.15
    );
    
    IF NEW.total_mark < 40.00 THEN
        INSERT INTO Audit_Logs (action_type, description) 
        VALUES ('GRADE_ALERT', CONCAT('FAILING GRADE (<40) detected for Internship ID: ', NEW.internship_id));
    ELSEIF NEW.total_mark > 95.00 THEN
        INSERT INTO Audit_Logs (action_type, description) 
        VALUES ('GRADE_ALERT', CONCAT('EXCEPTIONAL GRADE (>95) detected for Internship ID: ', NEW.internship_id));
    END IF;
END //

-- trigger 2: Handle grade update (UPDATE)
CREATE TRIGGER calc_total_marks_update BEFORE UPDATE ON Assessments FOR EACH ROW
BEGIN
    SET NEW.total_mark = (
        NEW.score_tasks * 0.10 + NEW.score_safety * 0.10 + 
        NEW.score_theory * 0.10 + NEW.score_report * 0.15 + 
        NEW.score_language * 0.10 + NEW.score_lifelong * 0.15 + 
        NEW.score_proj_mgmt * 0.15 + NEW.score_time_mgmt * 0.15
    );
    
    IF NEW.total_mark < 40.00 AND OLD.total_mark >= 40.00 THEN
        INSERT INTO Audit_Logs (action_type, description) 
        VALUES ('GRADE_UPDATED', CONCAT('Grade changed to FAILING for Internship ID: ', NEW.internship_id));
    ELSEIF NEW.total_mark > 95.00 AND OLD.total_mark <= 95.00 THEN
        INSERT INTO Audit_Logs (action_type, description) 
        VALUES ('GRADE_UPDATED', CONCAT('Grade changed to EXCEPTIONAL for Internship ID: ', NEW.internship_id));
    END IF;
END //

DELIMITER ;

CREATE VIEW Assessor_Workload_View AS
SELECT 
    u.full_name AS 'Assessor_Name',
    COUNT(i.internship_id) AS 'Students_Assigned',
    CASE 
        WHEN COUNT(i.internship_id) >= 5 THEN 'Overloaded'
        WHEN COUNT(i.internship_id) BETWEEN 3 AND 4 THEN 'Busy'
        ELSE 'Optimal'
    END AS 'Workload_Status'
FROM Users u
LEFT JOIN Internships i ON u.user_id = i.assessor_id
WHERE u.role = 'Assessor'
GROUP BY u.user_id;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------
-- 4. DML: INSERT REALISTIC DATA
-- ---------------------------------------------------------
INSERT INTO Schools (school_id, school_name) VALUES 
(1, 'School of Computer Science (FOSE)'), 
(2, 'School of Engineering (FOSE)'), 
(3, 'School of Biosciences (FOSE)'), 
(4, 'School of Psychology (FOSE)'), 
(5, 'School of Pharmacy (FOSE)'), 
(6, 'Nottingham University Business School (NUBS)'), 
(7, 'School of Economics (FASS)'), 
(8, 'School of Media, Languages and Cultures (FASS)'), 
(9, 'School of Politics, History and International Relations (FASS)'), 
(10, 'School of Education (FASS)');

INSERT INTO Programmes (prog_id, school_id, prog_name) VALUES 
(1, 1, 'BSc (Hons) Computer Science'), 
(2, 1, 'BSc (Hons) Computer Science with AI'), 
(3, 1, 'BSc (Hons) Software Engineering'),
(4, 2, 'BEng (Hons) Mechanical Engineering'), 
(5, 3, 'BSc (Hons) Biotechnology'), 
(6, 6, 'BSc (Hons) Finance, Accounting and Management'), 
(7, 6, 'BSc (Hons) International Business Management'), 
(8, 7, 'BSc (Hons) Economics'), 
(9, 8, 'BA (Hons) International Communications Studies'),
(10, 4, 'BSc (Hons) Psychology');

INSERT INTO States (state_id, state_name) VALUES 
(1, 'Selangor'), 
(2, 'Kuala Lumpur'), 
(3, 'Penang'), 
(4, 'Johor');

-- Note: Password hash represents the universal testing password '123123'
INSERT INTO Users (user_id, username, password_hash, role, full_name, email) VALUES 
(1, 'admin', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Admin', 'Registry Office', 'registry@nottingham.edu.my'),
-- Assessors (ID: 2, 3, 4, 5, 6)
(2, 'yasir_s', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Assessor', 'Yasir Shah', 'yasir.hafeez@nottingham.edu.my'),
(3, 'tomas_n', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Assessor', 'Tomas Naul', 'tomas.maul@nottingham.edu.my'),
(4, 'hazel_c', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Assessor', 'Hazel Chen', 'hazel.ramos@nottingham.edu.my'),
(5, 'benjamin_m', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Assessor', 'Benjamin Milbeam', 'benjamin@nottingham.edu.my'),
(6, 'chen_zs', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Assessor', 'Chen ZhiSheng', 'zhisheng.chen@nottingham.edu.my'),
-- Students (ID: 7, 8, 9, 10, 11, 12, 13, 14, 15)
(7, 'stu_sulei', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Student', 'Sulei', 'hcys1@nottingham.edu.my'),
(8, 'stu_qianqian', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Student', 'Qianqian', 'hcys2@nottingham.edu.my'),
(9, 'stu_hanwen', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Student', 'Hanwen', 'hcyh1@nottingham.edu.my'),
(10, 'stu_aisha', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Student', 'Aisha Othman', 'hcya1@nottingham.edu.my'),
(11, 'stu_kumar', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Student', 'Kumar Raj', 'hcyk1@nottingham.edu.my'),
(12, 'stu_mei', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Student', 'Mei Ling', 'hcym1@nottingham.edu.my'),
(13, 'stu_ahmad', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Student', 'Ahmad Razak', 'hcya2@nottingham.edu.my'),
(14, 'stu_elaine', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Student', 'Elaine Tan', 'hcye1@nottingham.edu.my'),
(15, 'stu_wei', '$2y$10$axHqcgQVDyLiF1l7wVXbKeWj9OWlCYphzO4A2rCRkV1JZjZL39.ti', 'Student', 'Tan Wei Jie', 'hcyt1@nottingham.edu.my');

INSERT INTO Students (student_id, user_id, prog_id, cohort_year) VALUES 
('S2024001', 7, 2, 2024), -- Sulei (AI)
('S2024002', 8, 1, 2024), -- Qianqian (CS)
('S2024003', 9, 6, 2024), -- Hanwen (Finance)
('S2024004', 10, 7, 2024), -- Aisha (IBM)
('S2024005', 11, 4, 2024), -- Kumar (Mech)
('S2024006', 12, 10, 2024), -- Mei (Psychology)
('S2024007', 13, 9, 2024), -- Ahmad (Media)
('S2024008', 14, 3, 2024), -- Elaine (SE)
('S2024009', 15, 2, 2024); -- Wei Jie (AI)

INSERT INTO Companies (company_id, state_id, company_name, contact_person) VALUES 
(1, 1, 'Grab Malaysia', 'Mr. Lim'), 
(2, 2, 'Maybank HQ', 'En. Ahmad'), 
(3, 3, 'Intel Penang', 'Ms. Wong'), 
(4, 1, 'Maxis Berhad', 'Mr. Tan'), 
(5, 1, 'Top Glove', 'Mr. Lee'),
(6, 2, 'Shopee MY', 'Ms. Jessica'),
(7, 2, 'CIMB Bank', 'Ms. Siti'),
(8, 1, 'Petronas', 'En. Hairi');

INSERT INTO Internships (internship_id, student_id, company_id, assessor_id, start_date, end_date, status) VALUES 
(1, 'S2024001', 1, 2, '2026-01-01', '2026-04-01', 'Completed'), -- Sulei assigned to Yasir
(2, 'S2024002', 3, 2, '2026-01-15', '2026-04-15', 'Completed'), -- Qianqian assigned to Yasir
(3, 'S2024003', 2, 3, '2026-02-01', NULL, 'Ongoing'),           -- Hanwen assigned to Tomas
(4, 'S2024004', 6, 4, '2026-01-01', '2026-04-01', 'Completed'), -- Aisha assigned to Hazel
(5, 'S2024005', 4, 5, '2026-03-01', NULL, 'Ongoing'),           -- Kumar assigned to Benjamin
(6, 'S2024008', 7, 2, '2026-02-15', '2026-05-15', 'Completed'); -- Elaine assigned to Yasir

INSERT INTO Assessments (internship_id, score_tasks, score_safety, score_theory, score_report, score_language, score_lifelong, score_proj_mgmt, score_time_mgmt, comments) VALUES 
-- [case 1]: Outstanding Student (Showcase High Score Alert)
(1, 95, 92, 94, 90, 95, 93, 94, 96, 'Outstanding work at Grab. High technical competence demonstrated.'),
-- [case 2]: Borderline Student (Showcase Average Scores)
(2, 55, 60, 50, 55, 60, 55, 50, 55, 'Satisfactory performance. Needs to improve technical depth.'),
-- [case 3]: Failing Student (Showcase Failure Alert)
(4, 30, 40, 35, 30, 45, 35, 30, 35, 'Failed to meet minimum internship requirements. Poor attendance.'),
-- [case 4]: Good Student (Showcase Solid Performance)
(6, 80, 85, 78, 82, 85, 80, 82, 80, 'Solid performance throughout the internship period.');

