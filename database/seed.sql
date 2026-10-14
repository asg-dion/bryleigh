USE bryleigh;
INSERT INTO categories (category_name) VALUES ('Tops'),('Dresses'),('Outerwear'),('Bags'),('Shoes') ON DUPLICATE KEY UPDATE category_name=VALUES(category_name);
INSERT IGNORE INTO categories (category_name) VALUES ('Bottoms');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT category_id,'Vintage Denim Jacket','Classic relaxed-fit denim jacket.',850.00,'Good',3,NULL,'active' FROM categories WHERE category_name='Outerwear' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Vintage Denim Jacket');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT category_id,'Floral Summer Dress','Light floral dress for sunny days.',650.00,'Like New',4,NULL,'active' FROM categories WHERE category_name='Dresses' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Floral Summer Dress');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT category_id,'Leather Crossbody Bag','Compact genuine leather crossbody bag.',1200.00,'Good',2,NULL,'active' FROM categories WHERE category_name='Bags' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Leather Crossbody Bag');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT category_id,'Preloved Sneakers','Everyday sneakers with plenty of life left.',950.00,'Fair',0,NULL,'active' FROM categories WHERE category_name='Shoes' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Preloved Sneakers');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT category_id,'Knit Cardigan','Soft neutral cardigan.',700.00,'Good',1,NULL,'active' FROM categories WHERE category_name='Tops' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Knit Cardigan');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT category_id,'Plaid Blazer','Smart plaid blazer for layering.',1100.00,'Like New',2,NULL,'active' FROM categories WHERE category_name='Outerwear' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Plaid Blazer');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT category_id,'Denim Shorts','Casual high-waist denim shorts.',450.00,'Good',5,NULL,'active' FROM categories WHERE category_name='Tops' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Denim Shorts');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT category_id,'Canvas Tote','Sturdy reusable canvas tote.',350.00,'Good',6,NULL,'active' FROM categories WHERE category_name='Bags' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Canvas Tote');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT category_id,'Ribbed Crop Top','Minimal ribbed cotton crop top.',300.00,'Like New',3,NULL,'active' FROM categories WHERE category_name='Tops' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Ribbed Crop Top');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT category_id,'Pleated Midi Skirt','Flowy pleated midi skirt.',575.00,'Good',2,NULL,'active' FROM categories WHERE category_name='Dresses' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Pleated Midi Skirt');

INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Linen Button-Down Shirt','Breathable oversized linen shirt, light beige, barely worn.',480.00,'Like New',5,'linen-button-down-shirt.jpg','active' FROM categories c WHERE c.category_name='Tops' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Linen Button-Down Shirt');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Graphic Band Tee','Vintage-style cotton tee, slight fading from wash, soft fabric.',220.00,'Fair',2,'graphic-band-tee.jpg','active' FROM categories c WHERE c.category_name='Tops' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Graphic Band Tee');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Silk Cami Top','Delicate silk-blend camisole, adjustable straps, size S.',650.00,'Good',0,'silk-cami-top.jpg','active' FROM categories c WHERE c.category_name='Tops' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Silk Cami Top');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Satin Slip Dress','Midi-length satin slip dress, deep emerald color, elegant drape.',890.00,'Like New',3,'satin-slip-dress.jpg','active' FROM categories c WHERE c.category_name='Dresses' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Satin Slip Dress');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Boho Maxi Dress','Flowy floral maxi dress, slightly loose thread on hem.',540.00,'Fair',1,'boho-maxi-dress.jpg','active' FROM categories c WHERE c.category_name='Dresses' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Boho Maxi Dress');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Corduroy Pinafore Dress','Warm corduroy overall dress, deep rust color, great for layering.',610.00,'Good',4,'corduroy-pinafore-dress.jpg','active' FROM categories c WHERE c.category_name='Dresses' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Corduroy Pinafore Dress');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Oversized Wool Coat','Camel wool-blend coat, tailored fit, minimal pilling.',1450.00,'Good',2,'oversized-wool-coat.jpg','active' FROM categories c WHERE c.category_name='Outerwear' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Oversized Wool Coat');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Cropped Puffer Jacket','Lightweight cropped puffer, black, small zipper repair on left pocket.',780.00,'Fair',0,'cropped-puffer-jacket.jpg','active' FROM categories c WHERE c.category_name='Outerwear' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Cropped Puffer Jacket');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Varsity Bomber Jacket','Classic varsity jacket, cream and navy, ribbed cuffs intact.',950.00,'Like New',3,'varsity-bomber-jacket.jpg','active' FROM categories c WHERE c.category_name='Outerwear' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Varsity Bomber Jacket');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Woven Straw Tote','Handwoven straw tote bag, perfect for summer, light wear on handles.',390.00,'Good',6,'woven-straw-tote.jpg','active' FROM categories c WHERE c.category_name='Bags' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Woven Straw Tote');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Mini Quilted Shoulder Bag','Quilted vegan leather shoulder bag with gold chain strap.',720.00,'Like New',2,'mini-quilted-shoulder-bag.jpg','active' FROM categories c WHERE c.category_name='Bags' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Mini Quilted Shoulder Bag');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Canvas Drawstring Backpack','Casual canvas backpack, roomy interior, one small ink stain inside.',310.00,'Fair',1,'canvas-drawstring-backpack.jpg','active' FROM categories c WHERE c.category_name='Bags' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Canvas Drawstring Backpack');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Chunky Platform Boots','Faux leather platform boots, minor scuffing on toe.',980.00,'Fair',0,'chunky-platform-boots.jpg','active' FROM categories c WHERE c.category_name='Shoes' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Chunky Platform Boots');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Classic White Sneakers','Clean white low-top sneakers, lightly worn soles.',520.00,'Good',5,'classic-white-sneakers.jpg','active' FROM categories c WHERE c.category_name='Shoes' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Classic White Sneakers');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Strappy Block Heels','Nude block heel sandals, worn once for an event.',650.00,'Like New',1,'strappy-block-heels.jpg','active' FROM categories c WHERE c.category_name='Shoes' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Strappy Block Heels');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Wide-Leg Trousers','High-waisted wide-leg trousers, charcoal gray, tailored fit.',560.00,'Good',4,'wide-leg-trousers.jpg','active' FROM categories c WHERE c.category_name='Bottoms' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Wide-Leg Trousers');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Cargo Joggers','Relaxed-fit cargo joggers, olive green, drawstring waist.',430.00,'Fair',2,'cargo-joggers.jpg','active' FROM categories c WHERE c.category_name='Bottoms' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Cargo Joggers');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'Pleated Tennis Skirt','Classic pleated mini skirt, white, small mark near zipper.',350.00,'Fair',0,'pleated-tennis-skirt.jpg','active' FROM categories c WHERE c.category_name='Bottoms' AND NOT EXISTS (SELECT 1 FROM products WHERE title='Pleated Tennis Skirt');
INSERT INTO products (category_id,title,description,price,item_condition,stock,image_path,status) SELECT c.category_id,'High-Waisted Mom Jeans','Light wash mom jeans, stretch denim, true to size.',480.00,'Good',3,'high-waisted-mom-jeans.jpg','active' FROM categories c WHERE c.category_name='Bottoms' AND NOT EXISTS (SELECT 1 FROM products WHERE title='High-Waisted Mom Jeans');

UPDATE products
SET image_path = CASE title
    WHEN 'Pleated Midi Skirt' THEN 'pleated-midi-skirt.jpg'
    WHEN 'Ribbed Crop Top' THEN 'ribbed-crop-top.jpg'
    WHEN 'Canvas Tote' THEN 'canvas-tote.jpg'
    WHEN 'Denim Shorts' THEN 'denim-shorts.jpg'
    WHEN 'Plaid Blazer' THEN 'plaid-blazer.jpg'
    WHEN 'Knit Cardigan' THEN 'knit-cardigan.jpg'
    WHEN 'Preloved Sneakers' THEN 'preloved-sneakers.jpg'
    WHEN 'Leather Crossbody Bag' THEN 'leather-crossbody-bag.jpg'
    WHEN 'Floral Summer Dress' THEN 'floral-summer-dress.jpg'
    WHEN 'Vintage Denim Jacket' THEN 'vintage-denim-jacket.jpg'
    ELSE image_path
END
WHERE title IN (
    'Pleated Midi Skirt',
    'Ribbed Crop Top',
    'Canvas Tote',
    'Denim Shorts',
    'Plaid Blazer',
    'Knit Cardigan',
    'Preloved Sneakers',
    'Leather Crossbody Bag',
    'Floral Summer Dress',
    'Vintage Denim Jacket'
)
AND image_path IS NULL;
