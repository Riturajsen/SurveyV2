-- Database: `surveyV2 `
-- Make sure to create this database first.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
-- IMPORTANT: Default password is 'password'. CHANGE IT in a real application!
-- You can generate a hash in PHP using: password_hash('your_new_password', PASSWORD_DEFAULT);
-- and update the table manually or create a password change feature.
--

INSERT INTO `admins` (`admin_id`, `username`, `password_hash`) VALUES
(1, 'admin', '$2y$10$jLpM86YW.Pr5g7vJFrLGNu41xY6gaRMw.0kUrqfDogY7oM9UPEvU.'); -- Hash for 'password'

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_text` text NOT NULL,
  `question_type` enum('text','textarea','radio','checkbox','select') NOT NULL,
  `options` text DEFAULT NULL COMMENT 'Comma-separated options for radio, checkbox, select',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
   PRIMARY KEY (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `responses`
--

CREATE TABLE `responses` (
  `response_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `response_value` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `respondent_id` varchar(255) DEFAULT NULL COMMENT 'Optional: Session ID or other identifier',
   PRIMARY KEY (`response_id`),
   KEY `question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Constraints for dumped tables
--

--
-- Constraints for table `responses`
--
ALTER TABLE `responses`
  ADD CONSTRAINT `responses_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;