-- Run once. (admin-library.php also does this automatically if the column is missing.)
ALTER TABLE books ADD COLUMN IF NOT EXISTS cover_image TEXT;
