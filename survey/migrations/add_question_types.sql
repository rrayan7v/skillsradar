-- Migration: add question types and options
ALTER TABLE `questions` 
ADD COLUMN `question_type` varchar(20) NOT NULL DEFAULT 'scale' AFTER `question_text`,
ADD COLUMN `question_options` text DEFAULT NULL AFTER `question_type`;

-- Update existing questions to scale type
UPDATE `questions` SET `question_type` = 'scale' WHERE `question_type` = '';