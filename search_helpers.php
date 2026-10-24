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

function searchCatalog(PDO $pdo, array $p): array
{
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

    $sql = 'SELECT id, title, authors, isbn, published_date, shelf_number, key_word,
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
// User search (username, email or student number)
// ---------------------------------------------------------------------------

function searchUsers(PDO $pdo, string $q, int $limit = 50): array
{
    // TODO (backend): query the users table by username, email or student_id (use likePattern($q)
    // when $q is not empty), order by username, limit $limit. Return a list of user rows.
    return [];
}

/** Approved, not-yet-returned loans for the given student numbers, keyed by student number. */
function currentlyBorrowedByUsers(PDO $pdo, array $studentIds): array
{
    // TODO (backend): fetch approved, not-yet-returned loans (with book title and authors) for
    // these student numbers. Return them grouped by student number:
    // [ '2023-00123' => [ ['title' => ..., 'authors' => ..., 'due_date' => ...], ... ], ... ]
    return [];
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
      <article class="user-card" data-search="<?= h(trim("$username $studentId $email $bio")) ?>">
        <div class="profile-head">
          <div class="avatar avatar--grad" aria-hidden="true"><?= h(userInitials($username)) ?></div>
          <div>
            <h3 class="profile-name"><?= h($username) ?></h3>
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
