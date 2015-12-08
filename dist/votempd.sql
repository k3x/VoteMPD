-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Erstellungszeit: 08. Dez 2015 um 14:30
-- Server Version: 5.5.46-0ubuntu0.14.04.2
-- PHP-Version: 5.5.9-1ubuntu4.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Datenbank: `votempd`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `files`
--

CREATE TABLE IF NOT EXISTS `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `folderid` int(11) NOT NULL,
  `artist` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `album` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `year` varchar(4) COLLATE utf8_unicode_ci NOT NULL,
  `length` int(11) NOT NULL COMMENT 'in seconds',
  `size` int(11) NOT NULL COMMENT 'Bytes',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=16033 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `folders`
--

CREATE TABLE IF NOT EXISTS `folders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `foldername` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `picture` mediumblob,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=2493 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `options_date`
--

CREATE TABLE IF NOT EXISTS `options_date` (
  `id` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `value` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `options_int`
--

CREATE TABLE IF NOT EXISTS `options_int` (
  `id` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `value` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `playlistitems`
--

CREATE TABLE IF NOT EXISTS `playlistitems` (
  `playlistname` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `fileid` int(11) DEFAULT NULL,
  `filepath` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'only set if no fileid found'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `playlog`
--

CREATE TABLE IF NOT EXISTS `playlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fileid` int(11) NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=43 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `voteforskip`
--

CREATE TABLE IF NOT EXISTS `voteforskip` (
  `fileid` int(11) NOT NULL,
  `ip` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `votes`
--

CREATE TABLE IF NOT EXISTS `votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fileid` int(11) NOT NULL,
  `ip` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `date` datetime NOT NULL,
  `played` tinyint(4) NOT NULL DEFAULT '0' COMMENT '1 wenn bereits abgespielt',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=22 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
