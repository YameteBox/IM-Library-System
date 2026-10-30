<?php
/**
 * Shared search helpers: catalog search (books) and user search (profiles).
 * Included by admin-library.php, library.php, search-profiles.php, suspend-user.php.
 */
require_once __DIR__ . '/media_types.php';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatPublishedDate')) {
    function formatPublishedDate(string $isoDate): string
    {
        $timestamp = strtotime($isoDate);
        return $timestamp ? date('F j, Y', $timestamp) : $isoDate;
    }
}

/** Escape % _ \ so what the person types is matched literally by ILIKE. */
function likePattern(string $term): string
{
    return '%' . addcslashes($term, '%_\\') . '%';
}

// ---------------------------------------------------------------------------
// Catalog search
// ---------------------------------------------------------------------------

/** Search-field dropdown: request value => label + the books columns it searches. */
const CATALOG_SEARCH_FIELDS = [
    'all'            => ['label' => 'All Fields',     'columns' => ['title', 'authors', 'isbn', 'shelf_number', 'key_word', 'classification']],
    'title'          => ['label' => 'Title',          'columns' => ['title']],
    'author'         => ['label' => 'Author',         'columns' => ['authors']],
    'isbn'           => ['label' => 'ISBN',           'columns' => ['isbn']],
    'shelf'          => ['label' => 'Shelf',          'columns' => ['shelf_number']],
    'tags'           => ['label' => 'Tags',           'columns' => ['key_word']],
    'classification' => ['label' => 'Classification', 'columns' => ['classification']],
];

/** Read q / type / field from a request array, falling back to safe defaults. */
function catalogSearchParams(array $src): array
{
    $q     = trim((string) ($src['q'] ?? ''));
    $field = $src['field'] ?? 'all';
    if (!is_string($field) || !isset(CATALOG_SEARCH_FIELDS[$field])) {
        $field = 'all';
    }
    $type = sanitizeMediaTypeFilter($src['type'] ?? 'all');

    return ['q' => $q, 'field' => $field, 'type' => $type];
}

function catalogFiltersActive(array $p): bool
{
    return $p['q'] !== '' || $p['type'] !== 'all';
}

/**
 * Accession numbers: every catalog item gets a unique, permanent number in the form
 *     [YEAR ADDED]-[MEDIA TYPE CODE]-[CATALOGED NUMBER]      e.g. 2026-01-00001
 * - YEAR ADDED: the year the item was added to the library (Asia/Manila time).
 * - MEDIA TYPE CODE: two digits from MEDIA_TYPE_CODES (01 book, 02 audiovisual, ...).
 * - CATALOGED NUMBER: 5-digit running count that restarts at 00001 for each year + media type.
 * A BEFORE INSERT trigger fills it in, so every insert path gets it and two admins adding
 * items at the same moment can never get the same number. Numbers are never reused or edited.
 * Items numbered before this format existed keep their old ACC-000001 style numbers.
 * Sets everything up the first time it is needed (no-op afterwards).
 */
function ensureAccessionColumn(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (accessionSetupComplete($pdo)) {
        return;
    }

    $pdo->beginTransaction();
    try {
        // One request sets this up at a time; a second one waits, then finds it done
        $pdo->exec('SELECT pg_advisory_xact_lock(7342001)');
        if (accessionSetupComplete($pdo)) {
            $pdo->commit();
            return;
        }

        $pdo->exec('ALTER TABLE books ADD COLUMN IF NOT EXISTS accession_number VARCHAR(20)');
        // Items from before this column existed (never numbered): give them a legacy number
        $pdo->exec(
            "UPDATE books SET accession_number = 'ACC-' || LPAD(id::text, 6, '0')
              WHERE accession_number IS NULL"
        );
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS books_accession_number_key ON books (accession_number)');
        $pdo->exec('ALTER TABLE books ALTER COLUMN accession_number SET NOT NULL');
        // The old sequence-based default is replaced by the trigger below
        $pdo->exec('ALTER TABLE books ALTER COLUMN accession_number DROP DEFAULT');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS accession_type_codes (
                media_type VARCHAR(50) PRIMARY KEY,
                type_code  CHAR(2)     NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS accession_counters (
                cat_year    INTEGER NOT NULL,
                type_code   CHAR(2) NOT NULL,
                last_number INTEGER NOT NULL,
                PRIMARY KEY (cat_year, type_code)
            )'
        );

        $seed = $pdo->prepare(
            'INSERT INTO accession_type_codes (media_type, type_code) VALUES (:t, :c)
             ON CONFLICT (media_type) DO NOTHING'
        );
        foreach (MEDIA_TYPE_CODES as $type => $code) {
            $seed->execute([':t' => $type, ':c' => $code]);
        }

        // Start each counter from the highest number already used (matters if this is re-run)
        $pdo->exec(
            "INSERT INTO accession_counters (cat_year, type_code, last_number)
             SELECT SUBSTRING(accession_number FROM 1 FOR 4)::int,
                    SUBSTRING(accession_number FROM 6 FOR 2),
                    MAX(SUBSTRING(accession_number FROM 9)::int)
               FROM books
              WHERE accession_number ~ '^[0-9]{4}-[0-9]{2}-[0-9]+$'
              GROUP BY 1, 2
             ON CONFLICT (cat_year, type_code)
             DO UPDATE SET last_number = GREATEST(accession_counters.last_number, EXCLUDED.last_number)"
        );

        $pdo->exec(<<<'SQL'
CREATE OR REPLACE FUNCTION books_set_accession() RETURNS trigger AS $fn$
DECLARE
    v_year INTEGER;
    v_code CHAR(2);
    v_num  INTEGER;
BEGIN
    v_year := EXTRACT(YEAR FROM (NOW() AT TIME ZONE 'Asia/Manila'))::INTEGER;
    SELECT c.type_code INTO v_code FROM accession_type_codes c WHERE c.media_type = NEW.media_type;
    IF v_code IS NULL THEN
        RAISE EXCEPTION 'No accession code defined for media type %', NEW.media_type;
    END IF;
    -- The row lock taken by this upsert serialises concurrent inserts of the same year + type
    INSERT INTO accession_counters (cat_year, type_code, last_number)
         VALUES (v_year, v_code, 1)
    ON CONFLICT (cat_year, type_code)
      DO UPDATE SET last_number = accession_counters.last_number + 1
      RETURNING last_number INTO v_num;
    NEW.accession_number := v_year::text || '-' || v_code || '-' || LPAD(v_num::text, 5, '0');
    RETURN NEW;
END;
$fn$ LANGUAGE plpgsql
SQL
        );
        $pdo->exec('DROP TRIGGER IF EXISTS books_accession_trg ON books');
        // Last step: the trigger marks the setup as complete
        $pdo->exec(
            'CREATE TRIGGER books_accession_trg BEFORE INSERT ON books
             FOR EACH ROW EXECUTE FUNCTION books_set_accession()'
        );
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** True once the accession trigger exists and every media type in MEDIA_TYPE_CODES has its code stored. */
function accessionSetupComplete(PDO $pdo): bool
{
    $stmt = $pdo->query(
        "SELECT 1 FROM pg_trigger
          WHERE tgname = 'books_accession_trg' AND tgrelid = 'books'::regclass AND NOT tgisinternal"
    );
    if (!$stmt->fetchColumn()) {
        return false;
    }
    $stmt = $pdo->query('SELECT COUNT(*) FROM accession_type_codes');
    return (int) $stmt->fetchColumn() >= count(MEDIA_TYPE_CODES);
}

function searchCatalog(PDO $pdo, array $p): array
{
    ensureAccessionColumn($pdo);
    $where  = [];
    $params = [];

    if ($p['type'] !== 'all') {
        $where[]          = 'media_type = :type';
        $params[':type']  = $p['type'];
    }

    if ($p['q'] !== '') {
        // Column names come from the CATALOG_SEARCH_FIELDS constant, never from the request.
        $columns = CATALOG_SEARCH_FIELDS[$p['field']]['columns'];
        $like    = likePattern($p['q']);
        $parts   = [];
        foreach ($columns as $i => $col) {
            // One placeholder per column: PDO can't reliably reuse a named placeholder
            $parts[]              = $col . ' ILIKE :q' . $i;
            $params[':q' . $i]    = $like;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $sql = 'SELECT id, title, authors, isbn, accession_number, published_date, shelf_number, key_word,
                   classification, media_type, copies_available, cover_image
              FROM books'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY title, id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** <option> list for the media type filter. */
function mediaTypeFilterOptions(string $selected): string
{
    $html = '<option value="all"' . ($selected === 'all' ? ' selected' : '') . '>All Media Types</option>';
    foreach (MEDIA_TYPES as $key => $label) {
        $html .= '<option value="' . h($key) . '"' . ($selected === $key ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    return $html;
}

/** <option> list for the search-field filter. */
function searchFieldOptions(string $selected): string
{
    $html = '';
    foreach (CATALOG_SEARCH_FIELDS as $key => $field) {
        $html .= '<option value="' . h($key) . '"' . ($selected === $key ? ' selected' : '') . '>' . h($field['label']) . '</option>';
    }
    return $html;
}

// ---------------------------------------------------------------------------
// Advanced catalog search (advanced-search.php)
// ---------------------------------------------------------------------------

/** Classifications a catalog item can have (same list admin-library.php uses). */
const CATALOG_CLASSIFICATIONS = [
    'General Works, Computer Science, and Information',
    'Philosophy and Psychology',
    'Religion',
    'Social Sciences',
    'Language',
    'Science',
    'Technology',
    'Arts and Recreation',
    'Literature',
    'History and Geography',
];

const ADVANCED_AVAILABILITY = [
    'any'         => 'Any availability',
    'available'   => 'Available now',
    'unavailable' => 'Unavailable',
];

const ADVANCED_SORTS = [
    'relevance' => 'Relevance',
    'title'     => 'Title',
    'author'    => 'Author',
    'newest'    => 'Newest first',
];

/** Read the advanced-search form from a request array, falling back to safe defaults. */
function advancedSearchParams(array $src): array
{
    $text = function (string $key, int $max) use ($src): string {
        $v = $src[$key] ?? '';
        return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
    };
    // A year is 1-9999 or empty
    $year = function (string $key) use ($src): string {
        $v = $src[$key] ?? '';
        if (!is_string($v) || !preg_match('/^\d{1,4}$/', trim($v)) || (int) trim($v) < 1) {
            return '';
        }
        return (string) (int) trim($v);
    };

    $class = $text('classification', 100);
    if (!in_array($class, CATALOG_CLASSIFICATIONS, true)) {
        $class = '';
    }
    $availability = $src['availability'] ?? 'any';
    if (!is_string($availability) || !isset(ADVANCED_AVAILABILITY[$availability])) {
        $availability = 'any';
    }
    $sort = $src['sort'] ?? 'relevance';
    if (!is_string($sort) || !isset(ADVANCED_SORTS[$sort])) {
        $sort = 'relevance';
    }

    $from = $year('from');
    $to   = $year('to');
    if ($from !== '' && $to !== '' && (int) $from > (int) $to) {
        [$from, $to] = [$to, $from]; // a backwards range still finds something
    }

    return [
        'tags'           => $text('tags', 200),
        'title'          => $text('title', 255),
        'author'         => $text('author', 255),
        'isbn'           => $text('isbn', 20),
        'type'           => sanitizeMediaTypeFilter($src['type'] ?? 'all'),
        'classification' => $class,
        'from'           => $from,
        'to'             => $to,
        'availability'   => $availability,
        'shelf'          => $text('shelf', 20),
        'sort'           => $sort,
    ];
}

/** True when at least one search criterion was filled in (sorting alone does not count). */
function advancedFiltersActive(array $p): bool
{
    foreach (['tags', 'title', 'author', 'isbn', 'classification', 'from', 'to', 'shelf'] as $key) {
        if ($p[$key] !== '') {
            return true;
        }
    }
    return $p['type'] !== 'all' || $p['availability'] !== 'any';
}

function searchCatalogAdvanced(PDO $pdo, array $p): array
{
    ensureAccessionColumn($pdo);
    $where  = [];
    $params = [];

    if ($p['title'] !== '') {
        $where[]          = 'title ILIKE :title';
        $params[':title'] = likePattern($p['title']);
    }
    if ($p['author'] !== '') {
        $where[]           = 'authors ILIKE :author';
        $params[':author'] = likePattern($p['author']);
    }
    if ($p['isbn'] !== '') {
        // Match with or without hyphens/spaces, e.g. 9780345539434 finds 978-0-345-53943-4
        $where[]         = "REPLACE(REPLACE(isbn, '-', ''), ' ', '') ILIKE :isbn";
        $params[':isbn'] = likePattern(str_replace(['-', ' '], '', $p['isbn']));
    }
    if ($p['shelf'] !== '') {
        $where[]          = 'shelf_number ILIKE :shelf';
        $params[':shelf'] = likePattern($p['shelf']);
    }
    if ($p['type'] !== 'all') {
        $where[]         = 'media_type = :type';
        $params[':type'] = $p['type'];
    }
    if ($p['classification'] !== '') {
        $where[]          = 'classification = :class';
        $params[':class'] = $p['classification'];
    }

    // Tags: comma-separated; every tag has to appear in the item's key words
    if ($p['tags'] !== '') {
        $tags = array_values(array_filter(array_map('trim', explode(',', $p['tags'])), 'strlen'));
        foreach ($tags as $i => $tag) {
            $where[]             = 'key_word ILIKE :tag' . $i;
            $params[':tag' . $i] = likePattern($tag);
        }
    }

    // Published year range (inclusive)
    if ($p['from'] !== '') {
        $where[]          = 'published_date >= :from_date';
        $params[':from_date'] = sprintf('%04d-01-01', (int) $p['from']);
    }
    if ($p['to'] !== '') {
        $where[]            = 'published_date <= :to_date';
        $params[':to_date'] = sprintf('%04d-12-31', (int) $p['to']);
    }

    if ($p['availability'] === 'available') {
        $where[] = 'COALESCE(copies_available, 0) > 0';
    } elseif ($p['availability'] === 'unavailable') {
        $where[] = 'COALESCE(copies_available, 0) <= 0';
    }

    switch ($p['sort']) {
        case 'title':
            $order = 'title, id';
            break;
        case 'author':
            $order = 'authors, title, id';
            break;
        case 'newest':
            $order = 'published_date DESC NULLS LAST, title, id';
            break;
        default: // relevance: exact title match first, then titles that start with the search
            if ($p['title'] !== '') {
                $order = '(LOWER(title) = LOWER(:ord_exact)) DESC, (title ILIKE :ord_prefix) DESC, title, id';
                $params[':ord_exact']  = $p['title'];
                $params[':ord_prefix'] = addcslashes($p['title'], '%_\\') . '%';
            } else {
                $order = 'title, id';
            }
    }

    $sql = 'SELECT id, title, authors, isbn, accession_number, published_date, shelf_number, key_word,
                   classification, media_type, copies_available, cover_image
              FROM books'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY ' . $order;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** <option> list for a fixed key => label map. */
function simpleOptions(array $options, string $selected): string
{
    $html = '';
    foreach ($options as $key => $label) {
        $html .= '<option value="' . h($key) . '"' . ($selected === (string) $key ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    return $html;
}

// ---------------------------------------------------------------------------
// User search (username, email or student number)
// ---------------------------------------------------------------------------

function searchUsers(PDO $pdo, string $q, int $limit = 50): array
{
    $params = [];
    $where  = '';

    if ($q !== '') {
        // One placeholder per column: PDO can't reliably reuse a named placeholder
        $like   = likePattern($q);
        $where  = 'WHERE d.username ILIKE :q0 OR d.email ILIKE :q1 OR u.student_number ILIKE :q2';
        $params = [':q0' => $like, ':q1' => $like, ':q2' => $like];
    }

    // username/email live in user_details, bio in user_profiles.
    // student_number is returned as student_id, the key the user cards and borrow lookup use.
    $stmt = $pdo->prepare(
        "SELECT u.id,
                d.username,
                d.email,
                COALESCE(p.bio, '') AS bio,
                u.student_number    AS student_id,
                u.banned
           FROM users u
           JOIN user_details d       ON d.id = u.id
           LEFT JOIN user_profiles p ON p.id = u.id
           $where
          ORDER BY d.username, u.id
          LIMIT :lim"
    );
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Approved, not-yet-returned loans for the given student numbers, keyed by student number. */
function currentlyBorrowedByUsers(PDO $pdo, array $studentIds): array
{
    $studentIds = array_values(array_unique(array_map('strval', $studentIds)));
    if (empty($studentIds)) {
        return [];
    }

    $in   = implode(',', array_fill(0, count($studentIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT CAST(u.student_number AS text) AS student_number, bk.title, bk.authors, bb.due_date
           FROM borrowed_books bb
           JOIN users u  ON u.id = bb.user_id
           JOIN books bk ON bk.id = bb.book_id
          WHERE bb.status = 'approved' AND bb.returned_at IS NULL
            AND CAST(u.student_number AS text) IN ($in)
          ORDER BY bb.due_date, bb.id"
    );
    $stmt->execute($studentIds);

    $byStudent = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byStudent[$row['student_number']][] = [
            'title'    => $row['title'],
            'authors'  => $row['authors'],
            'due_date' => $row['due_date'],
        ];
    }
    return $byStudent;
}

/**
 * True when the student's account is banned (users.banned). A ban only blocks borrow requests;
 * everything else works as normal. Read from the database each time so a new ban applies at once.
 * Any code that records a borrow request must check this too, not just hide the button.
 */
function userIsBanned(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT banned FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    return (bool) $stmt->fetchColumn();
}

/** Notice shown above the catalog to a banned student. */
function bannedNoticeHtml(): string
{
    return '<div role="status" style="padding:12px 16px;border-radius:10px;border:1px solid #f59e0b;background:#fffbeb;color:#92400e;font-size:.9rem">'
        . 'Your account is banned, so you can\'t request to borrow books. You can still search, browse and use your lists.'
        . '</div>';
}

/** The "Request to borrow" button for a book card (disabled when banned or already pending). */
function borrowButtonHtml(bool $banned, bool $pending): string
{
    if ($banned) {
        return '<button class="btn btn--block is-disabled" type="button" disabled title="Your account is banned from borrowing">Borrowing unavailable</button>';
    }
    if ($pending) {
        return '<button class="btn btn--block is-disabled" type="button" data-action="borrow">Borrow request pending</button>';
    }
    return '<button class="btn btn--block" type="button" data-action="borrow">Request to borrow</button>';
}

function userInitials(string $username): string
{
    $parts = preg_split('/[\s._-]+/', trim($username), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return strtoupper(mb_substr($username, 0, 2));
}

/** One .user-card. $withActions adds the Add Friend / Block buttons. */
function renderUserCard(array $u, array $borrowed, bool $withActions): string
{
    $username  = (string) ($u['username'] ?? '');
    $studentId = (string) ($u['student_id'] ?? '');
    $email     = (string) ($u['email'] ?? '');
    $bio       = (string) ($u['bio'] ?? '');

    ob_start(); ?>
      <article class="user-card" data-student-id="<?= h($studentId) ?>" data-search="<?= h(trim("$username $studentId $email $bio")) ?>">
        <div class="profile-head">
          <div class="avatar avatar--grad" aria-hidden="true"><?= h(userInitials($username)) ?></div>
          <div>
            <h3 class="profile-name"><?= h($username) ?><?php if (!empty($u['banned'])): ?> <span class="pill pill--err">Suspended</span><?php endif; ?></h3>
            <dl class="kv"><dt>Student No.</dt><dd><?= h($studentId) ?></dd><dt>Email</dt><dd><?= h($email) ?></dd><dt>Bio</dt><dd><?= h($bio) ?></dd></dl>
          </div>
        </div>
        <?php if ($withActions): ?>
        <div class="actions">
          <button class="btn" type="button" data-action="friend">Add Friend</button>
          <button class="btn btn--danger" type="button" data-action="block">Block</button>
        </div>
        <?php endif; ?>
        <p class="user-card__borrowed">Currently borrowed</p>
        <?php if (empty($borrowed)): ?>
        <p class="user-card__item">Nothing borrowed right now</p>
        <?php else: foreach ($borrowed as $loan): ?>
        <p class="user-card__item"><?= h($loan['title']) ?> — <?= h($loan['authors']) ?> · Due <?= h(formatPublishedDate((string) $loan['due_date'])) ?></p>
        <?php endforeach; endif; ?>
      </article>
<?php
    return ob_get_clean();
}


// ---------------------------------------------------------------------------
// Cover images
// ---------------------------------------------------------------------------

const COVER_UPLOAD_DIR = 'uploads/covers';
const COVER_MAX_BYTES  = 2 * 1024 * 1024; // 2 MB

/** Adds books.cover_image the first time it is needed (see add_cover_image.sql). */
function ensureCoverColumn(PDO $pdo): void
{
    // TODO (backend): make sure books.cover_image exists (no-op once the column is in the schema).
}

/**
 * Handle a cover submitted either as an uploaded file ($_FILES[$fileField])
 * or as an image URL. An uploaded file wins over the URL.
 *
 * Returns [stored path or URL | null, error message | null].
 * [null, null] means no new cover was provided.
 */
function saveCoverImage(string $fileField, string $urlValue): array
{
    $file = $_FILES[$fileField] ?? null;

    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return [null, 'That image is too large. The limit is 2 MB.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return [null, 'The image could not be uploaded. Please try again.'];
        }
        if ($file['size'] > COVER_MAX_BYTES) {
            return [null, 'That image is too large. The limit is 2 MB.'];
        }

        // Check the real file contents, not just the extension the browser reports
        $info    = @getimagesize($file['tmp_name']);
        $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
        if (!$info || !isset($allowed[$info[2]])) {
            return [null, 'Cover images must be .jpg or .png files.'];
        }

        $dir = __DIR__ . '/' . COVER_UPLOAD_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return [null, 'The cover folder could not be created. Check the folder permissions.'];
        }

        $name = bin2hex(random_bytes(10)) . '.' . $allowed[$info[2]];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return [null, 'The image could not be saved. Check the folder permissions.'];
        }

        return [COVER_UPLOAD_DIR . '/' . $name, null];
    }

    $url = trim($urlValue);
    if ($url !== '') {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (strlen($url) > 2000 || !filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            return [null, 'Please enter a valid image URL starting with http:// or https://.'];
        }
        return [$url, null];
    }

    return [null, null];
}

/** Delete a cover we stored ourselves. URLs and anything else are left alone. */
function deleteLocalCover(?string $path): void
{
    if ($path && preg_match('#^' . preg_quote(COVER_UPLOAD_DIR, '#') . '/[a-f0-9]{20}\.(jpg|png)$#', $path)) {
        $full = __DIR__ . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

/** True when the stored cover is an external URL rather than one of our uploads. */
function coverIsUrl(?string $cover): bool
{
    return $cover !== null && preg_match('#^https?://#i', $cover) === 1;
}

/** Safe src for a stored cover (local path or external URL). */
function coverSrc($cover): string
{
    return h(str_replace([' ', "\n", "\r"], ['%20', '', ''], trim((string) $cover)));
}

/**
 * <img> that fills a .book-card__cover and hides the paper texture behind it.
 * If the picture can't be loaded it removes itself and the paper comes back.
 */
function coverImgHtml($cover): string
{
    if (trim((string) $cover) === '') {
        return '';
    }
    return '<img class="book-card__cover-img" src="' . coverSrc($cover) . '" alt="" loading="lazy" '
        . 'onerror="this.parentNode.classList.remove(\'has-cover\');this.remove()">';
}
