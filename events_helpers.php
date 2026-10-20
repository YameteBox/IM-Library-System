<?php
/**
 * Calendar events: shared by dashboard.php (read-only) and admin-events.php (add / edit / delete).
 */
require_once __DIR__ . '/search_helpers.php'; // h()

const EVENT_TITLE_MAX = 120;
const EVENT_DESC_MAX  = 1000;

/** Creates the events table the first time it is needed. */
function ensureEventsTable(PDO $pdo): void
{
    // TODO (backend): events table: id, title, description (nullable), event_date, event_time (nullable).
}

/** First day of the month named by ?month=YYYY-MM (defaults to the current month). */
function calendarMonth($value): DateTime
{
    if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
        return new DateTime($value . '-01');
    }
    return new DateTime('first day of this month');
}

function eventsForMonth(PDO $pdo, DateTime $first): array
{
    // TODO (backend): events whose event_date falls in the month starting at $first,
    // ordered by date, time, title. Rows: id, title, description, event_date, event_time.
    return [];
}

function upcomingEvents(PDO $pdo, int $limit = 5): array
{
    // TODO (backend): the next $limit events from today on, ordered by date, time, title.
    return [];
}

/**
 * Validate the add / edit form.
 * Returns [clean data | null, error message | null].
 */
function validateEventInput(array $src): array
{
    $title = trim((string) ($src['title'] ?? ''));
    $desc  = trim((string) ($src['description'] ?? ''));
    $date  = trim((string) ($src['event_date'] ?? ''));
    $time  = trim((string) ($src['event_time'] ?? ''));

    if ($title === '') {
        return [null, 'Please enter a title for the event.'];
    }
    if (mb_strlen($title) > EVENT_TITLE_MAX) {
        return [null, 'The title must be ' . EVENT_TITLE_MAX . ' characters or fewer.'];
    }
    if (mb_strlen($desc) > EVENT_DESC_MAX) {
        return [null, 'The description must be ' . EVENT_DESC_MAX . ' characters or fewer.'];
    }

    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return [null, 'Please choose a valid date.'];
    }

    if ($time !== '') {
        $t = DateTime::createFromFormat('H:i', $time);
        if (!$t || $t->format('H:i') !== $time) {
            return [null, 'Please enter a valid time.'];
        }
    }

    return [[
        'title'       => $title,
        'description' => $desc !== '' ? $desc : null,
        'event_date'  => $date,
        'event_time'  => $time !== '' ? $time : null,
    ], null];
}

function formatEventTime(?string $time): string
{
    if (!$time) {
        return '';
    }
    $ts = strtotime($time);
    return $ts ? date('g:i A', $ts) : '';
}

/**
 * Month grid. With $editPage set, each event is a link to "$editPage?month=..&edit=ID"
 * (used by the admin page); otherwise events are plain labels.
 */
function renderCalendar(DateTime $first, array $events, string $page, ?string $editPage = null): string
{
    $byDay = [];
    foreach ($events as $e) {
        $byDay[(int) substr((string) $e['event_date'], 8, 2)][] = $e;
    }

    $month       = $first->format('Y-m');
    $prev        = (clone $first)->modify('-1 month')->format('Y-m');
    $next        = (clone $first)->modify('+1 month')->format('Y-m');
    $daysInMonth = (int) $first->format('t');
    $startDow    = (int) $first->format('w'); // 0 = Sunday
    $today       = date('Y-m-d');

    ob_start(); ?>
    <div class="cal" id="calendar">
      <div class="cal__head">
        <a class="btn btn--outline btn--sm" href="<?= h($page . '?month=' . $prev) ?>" aria-label="Previous month">‹</a>
        <h3 class="cal__month"><?= h($first->format('F Y')) ?></h3>
        <a class="btn btn--outline btn--sm" href="<?= h($page . '?month=' . $next) ?>" aria-label="Next month">›</a>
        <a class="btn btn--outline btn--sm" href="<?= h($page) ?>">Today</a>
      </div>
      <div class="cal__grid" role="grid" aria-label="<?= h($first->format('F Y')) ?>">
        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow): ?>
          <div class="cal__dow" role="columnheader"><?= $dow ?></div>
        <?php endforeach; ?>
        <?php for ($i = 0; $i < $startDow; $i++): ?><div class="cal__cell cal__cell--empty" aria-hidden="true"></div><?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $iso = $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT); ?>
          <div class="cal__cell<?= $iso === $today ? ' is-today' : '' ?>" role="gridcell">
            <span class="cal__day"><?= $day ?></span>
            <?php foreach ($byDay[$day] ?? [] as $e):
                $time  = formatEventTime($e['event_time'] ?? null);
                $label = ($time !== '' ? $time . ' ' : '') . $e['title'];
                if ($editPage !== null): ?>
              <a class="cal__ev" href="<?= h($editPage . '?month=' . $month . '&edit=' . (int) $e['id']) ?>#event-form" title="<?= h($label) ?>"><?= h($label) ?></a>
            <?php else: ?>
              <span class="cal__ev" title="<?= h($label) ?>"><?= h($label) ?></span>
            <?php endif; endforeach; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>
<?php
    return ob_get_clean();
}

/** Styles shared by the dashboard and the admin events page. */
function calendarStyles(): string
{
    return <<<CSS
    .cal { display: flex; flex-direction: column; gap: 12px; }
    .cal__head { display: flex; align-items: center; gap: 8px; }
    .cal__month { flex: 1; text-align: center; margin: 0; font-size: 1.1rem; }
    .cal__grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; }
    .cal__dow { text-align: center; font-size: .75rem; font-weight: 600; opacity: .7; padding: 4px 0; }
    .cal__cell { min-height: 84px; padding: 4px; display: flex; flex-direction: column; gap: 2px; overflow: hidden;
      border: 1px solid color-mix(in srgb, currentColor 20%, transparent); border-radius: 8px; }
    .cal__cell--empty { border-color: transparent; }
    .cal__cell.is-today { border: 2px solid #1E5EBA; }
    .cal__day { font-size: .8rem; font-weight: 600; }
    .cal__ev { display: block; font-size: .7rem; line-height: 1.25; padding: 2px 4px; border-radius: 4px; color: #fff; background: #1E5EBA;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none; }
    a.cal__ev:hover { background: #17489a; }
    .event-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
    .event-list li { display: flex; flex-direction: column; gap: 2px; }
    .event-list__when { font-size: .8rem; opacity: .75; }
    .event-list__desc { font-size: .85rem; }
    @media (max-width: 640px) {
      .cal__cell { min-height: 56px; }
      .cal__ev { font-size: .6rem; }
    }
CSS;
}
