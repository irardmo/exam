<?php
function docx_to_text($file) {
    // Extract text from .docx using ZipArchive
    $zip = new ZipArchive;
    if ($zip->open($file) === TRUE) {
        if (($index = $zip->locateName('word/document.xml')) !== false) {
            $xml = $zip->getFromIndex($index);
            $zip->close();
            $text = strip_tags($xml);
            return trim($text);
        }
    }
    return false;
}

function parse_mixed_blocks($text) {
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    $items = [];
    $current = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;

        // Detect Question Start (Q: ...)
        if (preg_match('/^(Q|Question)\s*\d*[:.)]?\s*(.+)$/i', $line, $m)) {
            if ($current) $items[] = $current; // Save previous
            $current = [
                'type' => 'mcq', // default, will switch to 'fill' later if needed
                'question' => $m[2],
                'A' => null, 'B' => null, 'C' => null, 'D' => null,
                'correct' => null,
                'answer_text' => null
            ];
            continue;
        }

        // Detect Options
        if ($current && preg_match('/^(A|B|C|D)[\.)\s]+(.+)$/', $line, $m)) {
            $current[$m[1]] = $m[2];
            continue;
        }

        // Detect Correct Answer (e.g., Answer: A)
        if ($current && preg_match('/^Answer[:\s]+([A-D])$/i', $line, $m)) {
            $current['correct'] = strtoupper($m[1]);
            continue;
        }

        // Detect Fill-in-the-Blank Answer
        if ($current && preg_match('/^Answer[:\s]+(.+)$/i', $line, $m)) {
            // If question has no A/B/C/D, treat as fill
            if (!$current['A'] && !$current['B'] && !$current['C'] && !$current['D']) {
                $current['type'] = 'fill';
            }
            $current['answer_text'] = trim($m[1]);
            continue;
        }
    }

    if ($current) $items[] = $current; // Last one
    return $items;
}
?>
