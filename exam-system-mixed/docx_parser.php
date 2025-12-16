<?php
// docx_parser.php (Fixed)

function docx_to_text($file) {
    // Check if ZipArchive is available and the file is valid
    if (!class_exists('ZipArchive') || !file_exists($file)) {
        return false;
    }
    
    $zip = new ZipArchive;
    if ($zip->open($file) === TRUE) {
        if (($index = $zip->locateName('word/document.xml')) !== false) {
            $xml_content = $zip->getFromIndex($index);
            $zip->close();
            
            // FIX 1: Robust XML parsing using regex to target text nodes and insert spaces
            // The document.xml uses <w:t> tags for text. Replace all </w:t> with a space 
            // and then strip the remaining tags.
            $text_with_spaces = preg_replace('/<\/w:t>/', ' ', $xml_content);
            $text = strip_tags($text_with_spaces);
            
            // Clean up multiple spaces and trim
            return trim(preg_replace('/\s+/', ' ', $text));
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

        // Detect Question Start (Q: ..., Question 1., etc.)
        if (preg_match('/^(Q|Question)\s*\d*[:.)]?\s*(.+)$/i', $line, $m)) {
            if ($current) $items[] = $current; // Save previous
            $current = [
                'type' => 'mcq', 
                'question' => $m[2],
                'A' => null, 'B' => null, 'C' => null, 'D' => null,
                'correct' => null,
                'answer_text' => null
            ];
            continue;
        }

        // Detect Options (A., B), etc.
        if ($current && preg_match('/^(A|B|C|D)[\.)\s]+(.+)$/', $line, $m)) {
            $current[$m[1]] = $m[2];
            continue;
        }

        // Detect Correct Answer - Letter (e.g., Answer: A)
        if ($current && preg_match('/^Answer[:\s]+([A-D])$/i', $line, $m)) {
            $current['correct'] = strtoupper($m[1]);
            // If we found a letter answer, confirm it's an MCQ
            $current['type'] = 'mcq'; 
            continue;
        }

        // Detect Correct Answer - Text (Fill-in-the-Blank/Short Answer)
        if ($current && preg_match('/^Answer[:\s]+(.+)$/i', $line, $m)) {
             // If we found a text answer AND we don't have a correct letter, treat as fill
            if (!$current['correct']) {
                $current['type'] = 'fill';
            }
            $current['answer_text'] = trim($m[1]);
            continue;
        }
        
        // FIX 2: Append continuation lines to the question text for multi-line support
        if ($current && !isset($current['correct']) && !$current['A'] && !$current['B']) {
            $current['question'] .= ' ' . $line;
            continue;
        }
        
        // If the line is none of the above, just ignore it (it's likely noise or unparsed formatting)
    }

    if ($current) $items[] = $current; // Last one
    return $items;
}
?>