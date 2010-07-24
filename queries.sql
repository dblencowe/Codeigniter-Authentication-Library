CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(15) NOT NULL DEFAULT '',
  `password` varchar(45) NOT NULL DEFAULT '',
  `firstname` varchar(60) NOT NULL DEFAULT '',
  `lastname` varchar(60) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `group_memberships` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `userid` int(11) NOT NULL,
    `groupid` int(11) NOT NULL,
    PRIMARY KEY(`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;