-- Migration: add teacher feedback fields to responses
ALTER TABLE `responses`
    ADD COLUMN `teacher_feedback` text DEFAULT NULL AFTER `score`,
    ADD COLUMN `feedback_by` int(11) DEFAULT NULL AFTER `teacher_feedback`,
    ADD COLUMN `feedback_at` timestamp NULL DEFAULT NULL AFTER `feedback_by`;
