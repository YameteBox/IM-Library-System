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
