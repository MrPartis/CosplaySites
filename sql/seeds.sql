USE `cosplay_sites`;

-- sample users (password: 'password' hashed with PHP password_hash) - replace in production
INSERT INTO `users` (`username`, `email`, `passwordHash`, `accountType`)
VALUES
  ('alice', 'alice@example.com', '$2y$10$EXAMPLEHASHPLACEHOLDERreplacewithreal', 'user'),
  ('bob', 'bob@example.com', '$2y$10$EXAMPLEHASHPLACEHOLDERreplacewithreal', 'user');

-- sample shop owned by alice (id 1 assumed)
INSERT INTO `shops` (`ownerUserId`, `name`, `address`, `phone`, `description`)
VALUES
  (1, 'Alice Cosplay Shop', '123 Anime St', '+84123456789', 'A small cosplay rental and sales shop.');
-- external/original shop link for availability
UPDATE `shops` SET `externalUrl` = 'https://example.com/alice-shop' WHERE `name` = 'Alice Cosplay Shop';

-- sample items
INSERT INTO `items` (`shopId`, `name`, `series`, `brand`, `size`, `priceTest`, `priceShoot`, `priceFestival`, `sourceLink`, `description`)
VALUES
  (1, 'Sailor Moon Outfit', 'Sailor Moon', 'BrandA', 'M', 100000, 200000, 300000, NULL, 'Classic set for rent'),
  (1, 'Naruto Wig', 'Naruto', 'BrandWigs', 'One-size', 20000, 30000, 40000, NULL, 'High-quality synthetic wig');

INSERT INTO `item_images` (`itemId`, `url`, `isPrimary`) VALUES (1, '/uploads/sailor_moon.jpg', 1), (2, '/uploads/naruto_wig.jpg', 1);

-- availability mapping: both items available at Alice's shop
INSERT INTO `item_availability` (`itemId`, `shopId`, `available`, `note`) VALUES (1,1,1,'In stock'),(2,1,1,'Limited stock');
