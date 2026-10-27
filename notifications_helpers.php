<?php
/**
 * Student notifications (the bell in library.php), shared with notifications.php.
 *
 * Nothing here needs the admin pages to "send" anything. Notifications are worked out from
 * the database each time the page loads:
 *   - Request approved : an approved loan that is not yet due soon.
 *   - Due soon         : an approved, unreturned loan due within NOTIF_DUE_SOON_DAYS days.
 *   - Overdue          : an approved, unreturned loan past its due date.
 *   - Back in stock    : a book the student asked to be told about (book_watches) that
 *                        has copies available again.
 * notification_reads remembers which loan notifications the student has marked as read.
 */
require_once __DIR__ . '/search_helpers.php'; // h(), formatPublishedDate()

const NOTIF_DUE_SOON_DAYS = 2; // same "due soon" window profile.php uses

/** Creates the two small tables the first time they are needed. */
function ensureNotificationTables(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notification_reads (
            user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            notif_key VARCHAR(40) NOT NULL,
            read_at   TIMESTAMP NOT NULL DEFAULT NOW(),
            PRIMARY KEY (user_id, notif_key)
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS book_watches (
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            book_id    INTEGER NOT NULL REFERENCES books(id) ON DELETE CASCADE,
            created_at TIMESTAMP NOT NULL DEFAULT NOW(),
            PRIMARY KEY (user_id, book_id)
        )'
    );
}

/** One-time token for the notification POSTs (kept in the session). */
function notificationCsrfToken(): string
{
    if (empty($_SESSION['notif_csrf'])) {
        $_SESSION['notif_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['notif_csrf'];
}

/** True for a well-formed notification key such as "overdue:12" or "stock:7". */
function isValidNotificationKey($key): bool
{
    return is_string($key) && preg_match('/^(approved|due-soon|overdue|stock):[0-9]{1,10}$/', $key) === 1;
}

function notifDaysText(int $n): string
{
    return $n . ' day' . ($n === 1 ? '' : 's');
}

/**
 * Unread notifications and the ids of the books this student is watching.
 * Returns ['items' => [...], 'watched' => [book ids]]. If anything goes wrong the library page
 * still loads, just without notifications (the error is written to the PHP error log).
 */
function notificationData(PDO $pdo, int $userId): array
{
    $out = ['items' => [], 'watched' => []];

    try {
        ensureNotificationTables($pdo);

        $stmt = $pdo->prepare('SELECT notif_key FROM notification_reads WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
        $read = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

        $stmt = $pdo->prepare('SELECT book_id FROM book_watches WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
        $out['watched'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        // Approved, not-yet-returned loans
        $stmt = $pdo->prepare(
            "SELECT bb.id AS loan_id, bk.title, bb.due_date
               FROM borrowed_books bb
               JOIN books bk ON bk.id = bb.book_id
              WHERE bb.user_id = :uid AND bb.status = 'approved' AND bb.returned_at IS NULL
              ORDER BY bb.due_date, bb.id"
        );
        $stmt->execute([':uid' => $userId]);
        $today = new DateTime('today');

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $loan) {
            $id    = (int) $loan['loan_id'];
            $title = (string) $loan['title'];
            $due   = new DateTime((string) $loan['due_date']);
            $due->setTime(0, 0);
            $daysLeft = (int) $today->diff($due)->format('%r%a');
            $dueText  = formatPublishedDate($due->format('Y-m-d'));

            if ($daysLeft < 0) {
                $type = 'overdue';
                $head = 'Overdue';
                $body = '“' . $title . '” is ' . notifDaysText(-$daysLeft) . ' overdue. It was due ' . $dueText . '.';
            } elseif ($daysLeft <= NOTIF_DUE_SOON_DAYS) {
                $type = 'due-soon';
                $head = 'Due soon';
                $when = $daysLeft === 0 ? 'due today' : ($daysLeft === 1 ? 'due tomorrow' : 'due in ' . notifDaysText($daysLeft));
                $body = '“' . $title . '” is ' . $when . ' (' . $dueText . ').';
            } else {
                $type = 'approved';
                $head = 'Request approved';
                $body = 'Your request for “' . $title . '” was approved. It is due ' . $dueText . '.';
            }

            $key = $type . ':' . $id;
            if (!isset($read[$key])) {
                $out['items'][] = ['key' => $key, 'type' => $type, 'head' => $head, 'body' => $body, 'href' => ''];
            }
        }

        // Watched books that have copies again
        $stmt = $pdo->prepare(
            'SELECT bk.id, bk.title, bk.authors, bk.copies_available
               FROM book_watches w
               JOIN books bk ON bk.id = w.book_id
              WHERE w.user_id = :uid AND COALESCE(bk.copies_available, 0) > 0
              ORDER BY bk.title, bk.id'
        );
        $stmt->execute([':uid' => $userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $book) {
            $copies = (int) $book['copies_available'];
            $out['items'][] = [
                'key'  => 'stock:' . (int) $book['id'],
                'type' => 'stock',
                'head' => 'Back in stock',
                'body' => '“' . $book['title'] . '” now has ' . $copies . ' cop' . ($copies === 1 ? 'y' : 'ies') . ' available.',
                'href' => 'library.php?' . http_build_query(['q' => $book['title'], 'field' => 'title', 'type' => 'all']),
            ];
        }

        // Most urgent first
        $rank = ['overdue' => 0, 'due-soon' => 1, 'approved' => 2, 'stock' => 3];
        usort($out['items'], function ($a, $b) use ($rank) {
            return $rank[$a['type']] <=> $rank[$b['type']];
        });
    } catch (Throwable $e) {
        error_log('notificationData: ' . $e->getMessage());
        $out['items'] = [];
    }

    return $out;
}

/** The bell button and its dropdown. Goes inside the header <nav>. */
function notificationBellHtml(array $items): string
{
    $count = count($items);

    ob_start(); ?>
        <div class="notif" data-notif data-csrf="<?= h(notificationCsrfToken()) ?>">
          <button class="nav__link notif__btn" type="button" id="notif-toggle" aria-expanded="false" aria-controls="notif-panel" aria-label="Notifications, <?= $count ?> unread">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            <span>Notifications</span>
            <span class="notif__badge" data-notif-badge<?= $count === 0 ? ' hidden' : '' ?>><?= $count > 99 ? '99+' : $count ?></span>
          </button>
          <section class="panel notif__panel" id="notif-panel" aria-label="Notifications" hidden>
            <div class="notif__head">
              <h2 class="notif__title">Notifications</h2>
              <button class="btn btn--outline btn--sm" type="button" data-notif-all<?= $count === 0 ? ' hidden' : '' ?>>Mark all as read</button>
            </div>
            <ul class="notif__list" data-notif-list>
<?php foreach ($items as $n): ?>
              <li class="notif__item notif__item--<?= h($n['type']) ?>" data-notif-key="<?= h($n['key']) ?>">
                <div class="notif__text">
                  <strong><?= h($n['head']) ?></strong>
                  <span><?= h($n['body']) ?></span>
<?php if ($n['href'] !== ''): ?>
                  <a href="<?= h($n['href']) ?>">View in catalog</a>
<?php endif; ?>
                </div>
                <button class="notif__read" type="button" data-notif-read>Mark as read</button>
              </li>
<?php endforeach; ?>
            </ul>
            <p class="sub notif__empty" data-notif-empty<?= $count > 0 ? ' hidden' : '' ?>>You’re all caught up.</p>
          </section>
        </div>
<?php
    return ob_get_clean();
}

/** "Notify me when available" toggle for a book card with no copies left. */
function watchButtonHtml(int $bookId, bool $watching): string
{
    return '<button class="btn btn--block btn--outline" type="button" data-notif-watch="' . $bookId . '" aria-pressed="' . ($watching ? 'true' : 'false') . '">'
        . ($watching ? 'Stop notifying me' : 'Notify me when available')
        . '</button>';
}

/** Styles for the bell, dropdown and notification rows. */
function notificationStyles(): string
{
    return <<<CSS
    .notif { position: relative; display: inline-block; }
    .notif [hidden], .notif__panel[hidden] { display: none !important; }
    .notif__btn { display: inline-flex; align-items: center; gap: 6px; background: none; border: 0; font: inherit; color: inherit; cursor: pointer; }
    .notif__btn:focus-visible, .notif__read:focus-visible { outline: 2px solid #1E5EBA; outline-offset: 2px; border-radius: 4px; }
    .notif__badge { min-width: 1.25rem; padding: 0 6px; border-radius: 999px; background: #b42318; color: #fff;
      font-size: .7rem; font-weight: 600; line-height: 1.25rem; text-align: center; }

    /* The panel is fixed to the viewport (the script moves it to <body> and sets left/top/max-height),
       so it can never be clipped by the header or run off the edge of the screen. */
    .notif__panel { position: fixed; top: 0; left: 0; z-index: 1000; box-sizing: border-box;
      width: min(380px, calc(100vw - 24px)); max-height: calc(100vh - 24px); max-height: calc(100dvh - 24px);
      overflow-y: auto; overscroll-behavior: contain; display: flex; flex-direction: column; gap: 12px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, .18); }
    .notif__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .notif__title { margin: 0; font-size: 1.05rem; }
    .notif__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
    .notif__item { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;
      padding: 2px 0 2px 10px; border-left: 4px solid #1E5EBA; }
    .notif__item--overdue { border-left-color: #b42318; }
    .notif__item--due-soon { border-left-color: #b45309; }
    .notif__item--approved { border-left-color: #15803d; }
    .notif__text { display: flex; flex-direction: column; gap: 2px; min-width: 0; overflow-wrap: anywhere;
      font-size: .88rem; text-align: left; }
    .notif__text a { font-size: .85rem; }
    .notif__read { flex: none; background: none; border: 0; padding: 2px 0; font: inherit; font-size: .8rem;
      color: inherit; opacity: .75; text-decoration: underline; cursor: pointer; }
    .notif__read:hover { opacity: 1; }
    .notif__read:disabled { opacity: .4; cursor: default; }
    .notif__empty { margin: 0; }
    @media (max-width: 420px) {
      .notif__item { flex-direction: column; gap: 4px; }
    }
CSS;
}

/** Click handling for the bell and the "Notify me when available" buttons. */
function notificationScript(): string
{
    return <<<'JS'
(function () {
  var root   = document.querySelector('[data-notif]');
  if (!root) return;

  var csrf   = root.getAttribute('data-csrf');
  var toggle = document.getElementById('notif-toggle');
  var panel  = document.getElementById('notif-panel');
  var badge  = root.querySelector('[data-notif-badge]');
  var list   = panel.querySelector('[data-notif-list]');
  var empty  = panel.querySelector('[data-notif-empty]');
  var allBtn = panel.querySelector('[data-notif-all]');
  var MARGIN = 12;

  // Move the panel out of the header so no ancestor (overflow, transform, backdrop-filter)
  // can clip it or change what "fixed" is measured against.
  document.body.appendChild(panel);

  // Keep the panel under the bell and fully on screen, whatever the screen size.
  function place() {
    var r  = toggle.getBoundingClientRect();
    var vw = document.documentElement.clientWidth;
    var vh = window.innerHeight;
    var w  = panel.offsetWidth;
    var left = Math.min(Math.max(r.right - w, MARGIN), Math.max(vw - w - MARGIN, MARGIN));
    var top  = Math.min(Math.max(r.bottom + 8, MARGIN), Math.max(vh - 160, MARGIN));
    panel.style.left      = left + 'px';
    panel.style.top       = top + 'px';
    panel.style.maxHeight = Math.max(vh - top - MARGIN, 120) + 'px';
  }

  function setOpen(open) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) place();
  }

  function render() {
    var n = list.querySelectorAll('[data-notif-key]').length;
    badge.textContent = n > 99 ? '99+' : String(n);
    badge.hidden = n === 0;
    empty.hidden = n !== 0;
    allBtn.hidden = n === 0;
    toggle.setAttribute('aria-label', 'Notifications, ' + n + ' unread');
    if (!panel.hidden) place();
  }

  function toast(message) {
    var region = document.querySelector('[data-toast-region]');
    if (!region) return;
    var box = document.createElement('div');
    box.className = 'alert alert-danger alert-dismissible fade show';
    box.setAttribute('role', 'alert');
    box.appendChild(document.createTextNode(message));
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close';
    close.setAttribute('data-bs-dismiss', 'alert');
    close.setAttribute('aria-label', 'Close');
    box.appendChild(close);
    region.appendChild(box);
  }

  function post(data) {
    var body = new URLSearchParams();
    body.set('csrf', csrf);
    Object.keys(data).forEach(function (k) { body.set(k, data[k]); });
    return fetch('notifications.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      body: body
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (json) {
        if (!res.ok || !json.ok) throw new Error(json.error || 'Something went wrong. Please try again.');
        return json;
      });
    }, function () {
      throw new Error('Could not reach the server. Check your connection and try again.');
    });
  }

  // Capture phase, so app.js (prototype) never also handles these clicks
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;

    if (t.closest('#notif-toggle')) {
      e.stopImmediatePropagation();
      setOpen(panel.hidden);
      return;
    }

    var one = t.closest('[data-notif-read]');
    if (one) {
      e.stopImmediatePropagation();
      var li = one.closest('[data-notif-key]');
      one.disabled = true;
      post({ action: 'read', keys: li.getAttribute('data-notif-key') })
        .then(function () { li.remove(); render(); })
        .catch(function (err) { one.disabled = false; toast(err.message); });
      return;
    }

    if (t.closest('[data-notif-all]')) {
      e.stopImmediatePropagation();
      var keys = Array.prototype.map.call(list.querySelectorAll('[data-notif-key]'), function (li) {
        return li.getAttribute('data-notif-key');
      });
      if (!keys.length) return;
      allBtn.disabled = true;
      post({ action: 'read', keys: keys.join(',') })
        .then(function () { list.innerHTML = ''; allBtn.disabled = false; render(); })
        .catch(function (err) { allBtn.disabled = false; toast(err.message); });
      return;
    }

    var watch = t.closest('[data-notif-watch]');
    if (watch) {
      e.stopImmediatePropagation();
      var watching = watch.getAttribute('aria-pressed') === 'true';
      watch.disabled = true;
      post({ action: watching ? 'unwatch' : 'watch', book_id: watch.getAttribute('data-notif-watch') })
        .then(function () {
          watch.disabled = false;
          watch.setAttribute('aria-pressed', watching ? 'false' : 'true');
          watch.textContent = watching ? 'Notify me when available' : 'Stop notifying me';
        })
        .catch(function (err) { watch.disabled = false; toast(err.message); });
      return;
    }

    // Click anywhere else closes the panel
    if (!panel.hidden && !panel.contains(t)) setOpen(false);
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !panel.hidden) { setOpen(false); toggle.focus(); }
  });

  // Follow the bell when the window is resized, rotated or scrolled while the panel is open
  window.addEventListener('resize', function () { if (!panel.hidden) place(); });
  window.addEventListener('scroll', function () { if (!panel.hidden) place(); }, { passive: true });
})();
JS;
}
