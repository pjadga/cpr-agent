<?php

namespace App\Http\Controllers;

/**
 * Class JsonPrettifier
 *
 * This class is responsible for prettifying JSON responses by making IDs and UUIDs clickable
 * and applying syntax highlighting to improve readability.
 */
class JsonPrettifier
{
    /**
     * Prettifies the response by making IDs and UUIDs clickable.
     *
     * @param array $data The response data
     * @return array The prettified response data
     */
    public function prettifyResponse(array $data): array
    {
        // Add a flag to indicate this response has been prettified
        $data['_prettified'] = true;

        // Process the data recursively to make IDs and UUIDs clickable
        $processedData = $this->processDataRecursively($data);

        // Apply JSON syntax highlighting and indentation
        $processedData['_html_formatted'] = $this->applyJsonSyntaxHighlighting(json_encode($processedData, JSON_PRETTY_PRINT));

        return $processedData;
    }

    /**
     * Recursively processes the data to make IDs and UUIDs clickable.
     * This enhanced version provides more comprehensive ID/UUID detection and formatting.
     *
     * @param mixed $data The data to process
     * @return mixed The processed data
     */
    private function processDataRecursively($data)
    {
        $result = [];

        // Expanded and more comprehensive list of common ID/UUID-like keys
        // These are checked case-insensitively. This list aims to be inclusive
        // for typical Foundry VTT document IDs, UUIDs, and related identifiers,
        // while trying to exclude common non-ID keys that might contain "id" (e.g., "requestId").
        $idKeys = [
            // Generic IDs (often used as primary keys)
            'id',
            'uuid',
            '_id', // Common for MongoDB-like databases

            // Specific Document/Entity IDs often found in Foundry VTT
            'documentId', 'entityId', 'actorId', 'itemId', 'sceneId',
            'journalId', 'macroId', 'tileId', 'tokenid', 'folderId',
            'playlistId', 'tableId', 'cardId', 'rollTableId', 'chatMessageId',
            'effectId', 'abilityId', 'spellId', 'featId', 'classId',
            'raceId', 'backgroundId', 'orgId', 'encounterId',
            'clientId', // Explicitly included as seen in your Vue script

            // UUIDs specific to various document types or relationships
            'itemUuid', 'actorUuid', 'tokenUuid', 'sceneUuid', 'journalUuid',
            'macroUuid', 'tileUuid', 'folderUuid', 'playlistUuid', 'tableUuid',
            'cardUuid', 'rollTableUuid', 'chatMessageUuid',
            'toUuid', // Seen in your `giveItem` payload, often a recipient UUID
            'itemSourceUuid', // Potential source UUID for items
            'sourceId', // Generic source ID/UUID
            'targetId', // Generic target ID/UUID
        ];

        // Additional UUID patterns to detect in string values
        $uuidPatterns = [
            // Standard UUID format (8-4-4-4-12)
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i',

            // Foundry-specific UUID formats (e.g., Scene.abc123.Token.def456)
            '/\b(Actor|Item|Scene|JournalEntry|Macro|RollTable|Playlist|Compendium)\.[a-zA-Z0-9]{16,}\b/',
            '/\b(Actor|Item|Scene|JournalEntry|Macro|RollTable|Playlist|Compendium)\.[a-zA-Z0-9]+\.(Token|Tile|Drawing|Note|AmbientLight|AmbientSound|MeasuredTemplate|Wall)\.[a-zA-Z0-9]+\b/'
        ];

        foreach ($data as $key => $value) {
            // Skip the _prettified flag from being processed as an ID itself.
            // This ensures it remains a meta-flag for the frontend.
            if ($key === '_prettified' || $key === '_html_formatted') {
                $result[$key] = $value;
                continue;
            }

            // Check if the current key (case-insensitive) is in our curated list of ID keys
            // AND its value is a non-empty string.
            if (
                is_string($key) &&
                in_array(strtolower($key), array_map('strtolower', $idKeys)) && // Case-insensitive key comparison
                is_string($value) &&
                !empty($value)
            ) {
                // This is an ID field, make it clickable
                $result[$key] = [
                    'value' => $value,
                    'clickable' => true,
                    'type' => 'id',
                    'format' => (strpos(strtolower($key), 'uuid') !== false) ? 'uuid' : 'id'
                ];
            }
            // Check if this is a string value that matches a UUID pattern
            elseif (is_string($value)) {
                $isUuid = false;
                foreach ($uuidPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $isUuid = true;
                        break;
                    }
                }

                if ($isUuid) {
                    // This is a UUID string, make it clickable
                    $result[$key] = [
                        'value' => $value,
                        'clickable' => true,
                        'type' => 'detected-uuid',
                        'format' => 'uuid'
                    ];
                } else {
                    // Regular string, no special processing
                    $result[$key] = $value;
                }
            }
            // Recursively process arrays
            elseif (is_array($value)) {
                $result[$key] = $this->processDataRecursively($value);
            }
            // All other types pass through unchanged
            else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Process JSON data for highlighting by recursively traversing the structure
     * and applying appropriate HTML formatting.
     *
     * @param mixed $data The JSON data to process
     * @param int $indent The current indentation level
     * @return string The highlighted HTML string
     */
    private function processJsonForHighlighting($data, $indent = 0)
    {
        $indentStr = str_repeat('  ', $indent);
        $html = '';

        if (is_array($data)) {
            // Check if this is a clickable ID object
            if (isset($data['value']) && isset($data['clickable']) && isset($data['type']) && isset($data['format']) && $data['clickable'] === true) {
                // This is a clickable ID object, format it specially
                $value = $data['value'];
                $type = $data['type'];
                $format = $data['format'];

                // Determine the color class based on the type and format
                $colorClass = 'text-yellow-400';
                if ($format === 'uuid') {
                    $colorClass = 'text-green-400';
                } else if ($type === 'detected-uuid') {
                    $colorClass = 'text-blue-400';
                }

                // Create a clickable span with appropriate styling and click-to-copy functionality
                $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $copyScript = "const textToCopy = '" . addslashes($value) . "'; " .
                             "const tempInput = document.createElement('textarea'); " .
                             "tempInput.value = textToCopy; " .
                             "document.body.appendChild(tempInput); " .
                             "tempInput.select(); " .
                             "try { document.execCommand('copy'); this.dataset.copied = 'true'; " .
                             "setTimeout(() => { delete this.dataset.copied; }, 2000); } " .
                             "catch (err) { console.error('Failed to copy text: ', err); } " .
                             "finally { document.body.removeChild(tempInput); }";

                $html .= '<span class="json-bracket">{</span>' . "\n";
                $html .= $indentStr . '  <span class="json-indent-guide"></span> <span class="json-key">"value"</span><span class="json-colon">:</span> ' .
                       '<span class="clickable-id ' . $colorClass . ' cursor-pointer inline-flex items-center space-x-1 transition-colors duration-200" ' .
                       'data-type="' . htmlspecialchars($type) . '" data-format="' . htmlspecialchars($format) . '" ' .
                       'onclick="' . $copyScript . '">' .
                       $escapedValue .
                       '<svg xmlns="http://www.w3.org/2000/svg" class="copy-icon ml-1 w-4 h-4 text-gray-500 group-hover:text-yellow-400 transition-colors duration-200" ' .
                       'viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">' .
                       '<path d="M8 4v12a2 2 0 002 2h8a2 2 0 002-2V8.342a2 2 0 00-.602-1.43l-4.44-4.342A2 2 0 0013.56 2H10a2 2 0 00-2 2z" />' .
                       '<path d="M16 18v2a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2h2" /></svg>' .
                       '</span><span class="json-comma">,</span>' . "\n";
                $html .= $indentStr . '  <span class="json-indent-guide"></span> <span class="json-key">"clickable"</span><span class="json-colon">:</span> <span class="json-boolean-null">true</span><span class="json-comma">,</span>' . "\n";
                $html .= $indentStr . '  <span class="json-indent-guide"></span> <span class="json-key">"type"</span><span class="json-colon">:</span> <span class="json-string">"' . htmlspecialchars($type) . '"</span><span class="json-comma">,</span>' . "\n";
                $html .= $indentStr . '  <span class="json-indent-guide"></span> <span class="json-key">"format"</span><span class="json-colon">:</span> <span class="json-string">"' . htmlspecialchars($format) . '"</span>' . "\n";
                $html .= $indentStr . '<span class="json-bracket">}</span>';

                return $html;
            }

            // Check if this is an array or an object
            $isAssoc = array_keys($data) !== range(0, count($data) - 1);

            if ($isAssoc) {
                // This is an object
                $html .= '<span class="json-bracket">{</span>' . "\n";
                $first = true;

                foreach ($data as $key => $value) {
                    // Skip internal properties
                    if ($key === '_prettified' || $key === '_html_formatted') {
                        continue;
                    }

                    if (!$first) {
                        $html .= '<span class="json-comma">,</span>' . "\n";
                    }
                    $first = false;

                    $html .= $indentStr . '  <span class="json-indent-guide"></span> <span class="json-key">"' . htmlspecialchars($key) . '"</span><span class="json-colon">:</span> ';
                    $html .= $this->processJsonForHighlighting($value, $indent + 1);
                }

                $html .= "\n" . $indentStr . '<span class="json-bracket">}</span>';
            } else {
                // This is an array
                $html .= '<span class="json-bracket">[</span>' . "\n";
                $first = true;

                foreach ($data as $value) {
                    if (!$first) {
                        $html .= '<span class="json-comma">,</span>' . "\n";
                    }
                    $first = false;

                    $html .= $indentStr . '  <span class="json-indent-guide"></span> ';
                    $html .= $this->processJsonForHighlighting($value, $indent + 1);
                }

                $html .= "\n" . $indentStr . '<span class="json-bracket">]</span>';
            }
        } elseif (is_string($data)) {
            $html .= '<span class="json-string">"' . htmlspecialchars($data) . '"</span>';
        } elseif (is_int($data) || is_float($data)) {
            $html .= '<span class="json-number">' . $data . '</span>';
        } elseif (is_bool($data)) {
            $html .= '<span class="json-boolean-null">' . ($data ? 'true' : 'false') . '</span>';
        } elseif (is_null($data)) {
            $html .= '<span class="json-boolean-null">null</span>';
        } else {
            $html .= htmlspecialchars((string)$data);
        }

        return $html;
    }

    /**
     * Applies JSON syntax highlighting to a JSON string.
     *
     * @param string $jsonString The JSON string to highlight
     * @return string The highlighted JSON string
     */
    public function applyJsonSyntaxHighlighting(string $jsonString): string
    {
        $highlightedString = $jsonString;

        // First, let's identify and specially format clickable ID objects
        // We'll use a JSON parser instead of regex to reliably extract clickable objects
        // This is more robust than regex for handling JSON objects with properties in any order
        $jsonData = json_decode($jsonString, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $highlightedString = $this->processJsonForHighlighting($jsonData);
            return $highlightedString;
        }

        // If JSON parsing fails, fall back to regex-based highlighting
        // Pattern to find objects with our clickable ID structure
        // We're looking for objects with value, clickable, type, and format properties in any order
        $pattern = '/{(?:[^{}]|"(?:\\\\.|[^"\\\\])*")*"value"\s*:\s*"([^"]+)"(?:[^{}]|"(?:\\\\.|[^"\\\\])*")*"clickable"\s*:\s*(true|false)(?:[^{}]|"(?:\\\\.|[^"\\\\])*")*"type"\s*:\s*"([^"]+)"(?:[^{}]|"(?:\\\\.|[^"\\\\])*")*"format"\s*:\s*"([^"]+)"(?:[^{}]|"(?:\\\\.|[^"\\\\])*")*}/';

        // Replace clickable ID objects with specially formatted HTML
        $highlightedString = preg_replace_callback(
            $pattern,
            function($matches) {
                $value = $matches[1];
                $isClickable = $matches[2] === 'true';
                $type = $matches[3];
                $format = $matches[4];

                if ($isClickable) {
                    // Determine the color class based on the type and format
                    $colorClass = 'text-yellow-400';
                    if ($format === 'uuid') {
                        $colorClass = 'text-green-400';
                    } else if ($type === 'detected-uuid') {
                        $colorClass = 'text-blue-400';
                    }

                    // Create a clickable span with appropriate styling and click-to-copy functionality
                    $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    $copyScript = "const textToCopy = '" . addslashes($value) . "'; " .
                                 "const tempInput = document.createElement('textarea'); " .
                                 "tempInput.value = textToCopy; " .
                                 "document.body.appendChild(tempInput); " .
                                 "tempInput.select(); " .
                                 "try { document.execCommand('copy'); this.dataset.copied = 'true'; " .
                                 "setTimeout(() => { delete this.dataset.copied; }, 2000); } " .
                                 "catch (err) { console.error('Failed to copy text: ', err); } " .
                                 "finally { document.body.removeChild(tempInput); }";

                    return '<span class="json-bracket">{</span>' .
                           '<span class="json-indent-guide"></span> <span class="json-key">"value"</span><span class="json-colon">:</span> ' .
                           '<span class="clickable-id ' . $colorClass . ' cursor-pointer inline-flex items-center space-x-1 transition-colors duration-200" ' .
                           'data-type="' . htmlspecialchars($type) . '" data-format="' . htmlspecialchars($format) . '" ' .
                           'onclick="' . $copyScript . '">' .
                           $escapedValue .
                           '<svg xmlns="http://www.w3.org/2000/svg" class="copy-icon ml-1 w-4 h-4 text-gray-500 group-hover:text-yellow-400 transition-colors duration-200" ' .
                           'viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">' .
                           '<path d="M8 4v12a2 2 0 002 2h8a2 2 0 002-2V8.342a2 2 0 00-.602-1.43l-4.44-4.342A2 2 0 0013.56 2H10a2 2 0 00-2 2z" />' .
                           '<path d="M16 18v2a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2h2" /></svg>' .
                           '</span><span class="json-comma">,</span>' .
                           '<span class="json-indent-guide"></span> <span class="json-key">"clickable"</span><span class="json-colon">:</span> <span class="json-boolean-null">true</span><span class="json-comma">,</span>' .
                           '<span class="json-indent-guide"></span> <span class="json-key">"type"</span><span class="json-colon">:</span> <span class="json-string">"' . htmlspecialchars($type) . '"</span><span class="json-comma">,</span>' .
                           '<span class="json-indent-guide"></span> <span class="json-key">"format"</span><span class="json-colon">:</span> <span class="json-string">"' . htmlspecialchars($format) . '"</span>' .
                           '<span class="json-bracket">}</span>';
                }

                // If not clickable, return the original match
                return $matches[0];
            },
            $highlightedString
        );

        // Step 1: Highlight JSON string VALUES first.
        // This regex specifically targets string values by looking for delimiters before them (:, ,, [, {)
        // and ensuring they are not followed by a colon (which would indicate a key).
        // We'll exclude our clickable ID objects from this highlighting.
        $highlightedString = preg_replace(
            '/(?<=[:,\[\{]|\s)\s*("((?:[^"\\\\]|\\\\.)*?)")(?!\s*[:\}\]])/',
            '<span class="json-string">$1</span>',
            $highlightedString
        );

        // Step 2: Highlight JSON keys.
        // This regex targets quoted strings immediately followed by a colon.
        // It will only wrap the *content* of the key, leaving the quotes outside the span.
        $highlightedString = preg_replace(
            '/"((?:[^"\\\\]|\\\\.)*?)":/',
            '"<span class="json-key">$1</span>":',
            $highlightedString
        );

        // Step 3: Highlight numbers.
        // No need for excludeClickablePlaceholder here, as numbers won't contain these strings.
        $highlightedString = preg_replace(
            '/(\b\d+(\.\d+)?\b)/',
            '<span class="json-number">$1</span>',
            $highlightedString
        );

        // Step 4: Highlight booleans and null.
        // No need for excludeClickablePlaceholder here.
        $highlightedString = preg_replace(
            '/(\b(true|false|null)\b)/',
            '<span class="json-boolean-null">$1</span>',
            $highlightedString
        );

        // Step 5: Highlight structural elements (braces, brackets, colons, commas)
        // This enhances readability by visually distinguishing the JSON structure
        $highlightedString = preg_replace(
            '/({|}|\[|\])/',
            '<span class="json-bracket">$1</span>',
            $highlightedString
        );

        $highlightedString = preg_replace(
            '/(:)/',
            '<span class="json-colon">$1</span>',
            $highlightedString
        );

        $highlightedString = preg_replace(
            '/(,)/',
            '<span class="json-comma">$1</span>',
            $highlightedString
        );

        // Add indentation guides to improve readability of nested structures
        $highlightedString = $this->addIndentationGuides($highlightedString);

        return $highlightedString;
    }

    /**
     * Adds visual indentation guides to JSON to improve readability of nested structures.
     *
     * @param string $jsonHtml The JSON HTML string with syntax highlighting already applied
     * @return string The JSON HTML with indentation guides added
     */
    private function addIndentationGuides(string $jsonHtml): string
    {
        // Split the JSON by line
        $lines = explode("\n", $jsonHtml);
        $processedLines = [];

        // Process each line to add indentation guides
        foreach ($lines as $line) {
            // Count leading spaces to determine indentation level
            if (preg_match('/^(\s+)/', $line, $match)) {
                $leadingSpaces = $match[1];
                $indentLevel = floor(strlen($leadingSpaces) / 2);

                if ($indentLevel > 0) {
                    // Create indentation guides by replacing the leading spaces
                    $indentGuides = '';
                    for ($j = 0; $j < strlen($leadingSpaces); $j++) {
                        // Add a guide at every even position (0-indexed)
                        if ($j % 2 === 0) {
                            $indentGuides .= ' '; // Keep the space
                        } else {
                            $indentGuides .= '<span class="json-indent-guide"></span>';
                        }
                    }
                    // Replace only the leading whitespace with our indentation guides
                    $indentedLine = preg_replace('/^(\s+)/', $indentGuides, $line);
                    $processedLines[] = $indentedLine;
                } else {
                    $processedLines[] = $line;
                }
            } else {
                $processedLines[] = $line;
            }
        }

        return implode("\n", $processedLines);
    }
}
