<?php
/**
 * Escape HTML special characters in a string
 * 
 * @param string $value The string to escape
 * @return string The escaped string
 */
function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
}
