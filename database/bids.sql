CREATE TABLE `bids` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) UNSIGNED NOT NULL,
  `painter_id` int(11) UNSIGNED NOT NULL,
  `bid_amount` decimal(10,2) NOT NULL,
  `message` text,
  `