<?php

declare(strict_types=1);

namespace NewsApiPlugin\Services;

class JsonFixer
{
    /**
     * Fixes an incomplete JSON string by removing unwanted symbols and ensuring it is valid.
     *
     * @param string $jsonString The incomplete JSON string.
     * @return string The fixed and valid JSON string.
     */
    public static function fixIncompleteJson(string $jsonString): string
    {
        // Step 1: Remove the ```json prefix and any trailing incomplete data
        $jsonString = preg_replace('/^```json\s*/', '', $jsonString); // Remove ```json
        $jsonString = preg_replace('/\s*```$/', '', $jsonString); // Remove trailing ```

        // Step 2: Remove any trailing incomplete data (e.g., truncated strings or brackets)
        $jsonString = self::removeTrailingIncompleteData($jsonString);

        // Step 3: Ensure the JSON is properly closed
        $jsonString = self::ensureJsonIsClosed($jsonString);

        // Step 4: Validate the JSON
        if (!self::isValidJson($jsonString)) {
            throw new \InvalidArgumentException("Failed to fix JSON: Invalid format.");
        }

        return $jsonString;
    }

    /**
     * Removes trailing incomplete data from the JSON string.
     *
     * @param string $jsonString The JSON string to clean.
     * @return string The cleaned JSON string.
     */
    private static function removeTrailingIncompleteData(string $jsonString): string
    {
        // Remove any trailing incomplete data (e.g., truncated strings or brackets)
        $jsonString = rtrim($jsonString, "\n\r\t,"); // Remove trailing commas or whitespace
        $jsonString = preg_replace('/,\s*$/', '', $jsonString); // Remove trailing comma
        $jsonString = preg_replace('/[^\[\]\{\}\w\s\/\.\-:,"\']+$/', '', $jsonString); // Remove invalid trailing characters

        return $jsonString;
    }

    /**
     * Ensures the JSON string is properly closed (e.g., adds missing brackets or quotes).
     *
     * @param string $jsonString The JSON string to fix.
     * @return string The fixed JSON string.
     */
    private static function ensureJsonIsClosed(string $jsonString): string
    {
        // Count the number of open and close brackets
        $openBrackets = substr_count($jsonString, '[');
        $closeBrackets = substr_count($jsonString, ']');

        // Add missing close brackets
        if ($openBrackets > $closeBrackets) {
            $jsonString .= str_repeat(']', $openBrackets - $closeBrackets);
        }

        // Ensure the JSON ends with a close bracket
        if (!preg_match('/\]\s*$/', $jsonString)) {
            $jsonString .= ']';
        }

        return $jsonString;
    }

    /**
     * Validates if a string is valid JSON.
     *
     * @param string $jsonString The JSON string to validate.
     * @return bool True if the JSON is valid, false otherwise.
     */
    private static function isValidJson(string $jsonString): bool
    {
        json_decode($jsonString);
        return (json_last_error() === JSON_ERROR_NONE);
    }
}