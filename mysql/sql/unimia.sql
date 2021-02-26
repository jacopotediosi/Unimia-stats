SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `unimia` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `unimia`;

CREATE TABLE IF NOT EXISTS `stats` (
  `datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_up` tinyint(1) NOT NULL,
  `response_time` mediumint(9) NOT NULL DEFAULT '0',
  `reason` varchar(255) NOT NULL DEFAULT '',
  `hour_datetime` tinyint(4) GENERATED ALWAYS AS (hour(`datetime`)) VIRTUAL,
  `date_datetime` date GENERATED ALWAYS AS (cast(`datetime` as date)) VIRTUAL,
  PRIMARY KEY (`datetime`),
  KEY `stats_idx_date_datetime` (`date_datetime`),
  KEY `stats_idx_is_up_date_datetime` (`is_up`,`date_datetime`),
  KEY `stats_idx_is_up_hour_datetime` (`is_up`,`hour_datetime`),
  KEY `stats_idx_hour_datetime` (`hour_datetime`),
  KEY `stats_idx_is_up` (`is_up`),
  KEY `stats_idx_response_time` (`response_time`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
