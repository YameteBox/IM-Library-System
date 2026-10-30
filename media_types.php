<?php
/**
 * Single source of truth for the media types a catalog item can have.
 * The array keys are what is stored in books.media_type (and what the
 * database CHECK constraint allows); the values are the display labels.
 *
 * Included by library.php, admin_library.php and list_view.php.
 */
const MEDIA_TYPES = [
    'book'          => 'Book',
    'audiovisual'   => 'Audiovisual Media',
    'board_game'    => 'Board Game',
    'digital_media' => 'Digital Media',
];

// Two-digit classification number for each media type, used in accession numbers
// (YEAR-CODE-NUMBER, e.g. 2026-01-00001). Codes are permanent: never renumber an existing one.
// Adding a new media type? Add its key to MEDIA_TYPES above and give it the next free code here.
const MEDIA_TYPE_CODES = [
    'book'          => '01',
    'audiovisual'   => '02',
    'board_game'    => '03',
    'digital_media' => '04',
];

// Bootstrap badge colour for each media type (used for the card badges)
const MEDIA_TYPE_BADGES = [
    'book'          => 'primary',
    'audiovisual'   => 'danger',
    'board_game'    => 'success',
    'digital_media' => 'info',
];

/** True only for a string that is one of the MEDIA_TYPES keys. */
function isValidMediaType($value): bool
{
    return is_string($value) && isset(MEDIA_TYPES[$value]);
}

/** Search-filter value: a valid media type key, or 'all'. */
function sanitizeMediaTypeFilter($value): string
{
    return isValidMediaType($value) ? $value : 'all';
}

function mediaTypeLabel($value): string
{
    return isValidMediaType($value) ? MEDIA_TYPES[$value] : 'Unknown';
}

function mediaTypeBadgeClass($value): string
{
    return isValidMediaType($value) ? MEDIA_TYPE_BADGES[$value] : 'secondary';
}

/** CSS class for the media type pill, e.g. pill--type-board-game. */
function mediaTypePillClass($value): string
{
    return isValidMediaType($value)
        ? 'pill--type-' . str_replace('_', '-', $value)
        : 'pill--type-unknown';
}
