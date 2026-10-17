-- 008 Remove "gelding" as a horse sex/category option (the stud does not track geldings).
SET NAMES utf8mb4;

UPDATE horses SET sex = 'male' WHERE sex = 'gelding';
UPDATE horses SET category = 'stallion' WHERE category = 'gelding';

ALTER TABLE horses
  MODIFY COLUMN sex ENUM('male','female') NOT NULL,
  MODIFY COLUMN category ENUM('foal','colt','filly','stallion','mare') NOT NULL;
