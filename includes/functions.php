<?php

function format_currency($amount) {
    return 'KSh ' . number_format((float) $amount, 0);
}

function format_event_date($datetime) {
    return date('D, j M Y', strtotime($datetime));
}

function format_event_time($datetime) {
    return date('g:i A', strtotime($datetime));
}

// Generates a short, unique-enough code for a single physical ticket.
// Uniqueness is still enforced at the database level (unique_code UNIQUE).
function generate_ticket_code() {
    return 'SD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
