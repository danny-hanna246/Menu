<?php
function validateInput($type, $value, $options = [])
{
    $value = trim($value);

    switch ($type) {
        case 'string':
            $maxLength = $options['max_length'] ?? 255;
            if (strlen($value) > $maxLength) {
                throw new InvalidArgumentException("String too long (max: {$maxLength})");
            }
            return sanitizeString($value);

        case 'int':
            $min = $options['min'] ?? PHP_INT_MIN;
            $max = $options['max'] ?? PHP_INT_MAX;
            $int = filter_var($value, FILTER_VALIDATE_INT);
            if ($int === false || $int < $min || $int > $max) {
                throw new InvalidArgumentException("Invalid integer value");
            }
            return $int;

        case 'float':
            $min = $options['min'] ?? -PHP_FLOAT_MAX;
            $max = $options['max'] ?? PHP_FLOAT_MAX;
            $float = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($float === false || $float < $min || $float > $max) {
                throw new InvalidArgumentException("Invalid float value");
            }
            return $float;

        case 'email':
            $email = filter_var($value, FILTER_VALIDATE_EMAIL);
            if ($email === false) {
                throw new InvalidArgumentException("Invalid email address");
            }
            return $email;

        case 'url':
            $url = filter_var($value, FILTER_VALIDATE_URL);
            if ($url === false) {
                throw new InvalidArgumentException("Invalid URL");
            }
            return $url;

        default:
            throw new InvalidArgumentException("Unknown validation type: {$type}");
    }
}
