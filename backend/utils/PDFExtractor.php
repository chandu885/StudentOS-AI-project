<?php
// backend/utils/PDFExtractor.php - Pure PHP PDF Text Extractor & Chunker for RAG

class PDFExtractor {

    /**
     * Extract plain text from a PDF file path
     *
     * @param string $filePath
     * @return string
     */
    public static function extractText($filePath) {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return '';
        }

        $content = @file_get_contents($filePath);
        if ($content === false || empty($content)) {
            return '';
        }

        // Fast extract from PDF content
        $text = self::parsePdfContent($content);

        // Clean and normalize text
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', $text); // remove non-printable control chars except \n and \t
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n+/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Parse binary/string content of PDF to extract text streams
     */
    private static function parsePdfContent($content) {
        $extractedText = '';

        // Locate all streams in PDF
        if (preg_match_all('#stream[\r\n]+(.*?)[\r\n]+endstream#s', $content, $matches)) {
            foreach ($matches[1] as $streamData) {
                // Try decompressing with gzuncompress or gzinflate
                $decompressed = @gzuncompress($streamData);
                if ($decompressed === false) {
                    $decompressed = @gzinflate($streamData);
                }
                if ($decompressed === false) {
                    // Try removing 2-byte header if present
                    if (strlen($streamData) > 2) {
                        $decompressed = @gzinflate(substr($streamData, 2));
                    }
                }
                if ($decompressed === false) {
                    $decompressed = $streamData;
                }

                // Extract text objects from stream
                $streamText = self::extractTextFromStream($decompressed);
                if (!empty($streamText)) {
                    $extractedText .= $streamText . "\n\n";
                }
            }
        }

        // If streams didn't yield text, try searching for literal BT ... ET blocks in full content
        if (empty(trim($extractedText))) {
            $extractedText = self::extractTextFromStream($content);
        }

        return $extractedText;
    }

    /**
     * Extract text from a single decompressed PDF stream
     */
    private static function extractTextFromStream($stream) {
        $text = '';

        // Match all BT ... ET (Begin Text ... End Text) blocks
        if (preg_match_all('#BT(.*?)ET#s', $stream, $textBlocks)) {
            foreach ($textBlocks[1] as $block) {
                // 1. Array text operator: [(str) 20 (str2)] TJ
                if (preg_match_all('#\[(.*?)\]\s*TJ#s', $block, $tjMatches)) {
                    foreach ($tjMatches[1] as $tjArray) {
                        if (preg_match_all('#\((.*?)\)#s', $tjArray, $strMatches)) {
                            $text .= implode('', array_map([self::class, 'cleanPdfString'], $strMatches[1])) . ' ';
                        }
                    }
                }

                // 2. Single string operator: (str) Tj
                if (preg_match_all('#\((.*?)\)\s*Tj#s', $block, $tjMatches)) {
                    foreach ($tjMatches[1] as $str) {
                        $text .= self::cleanPdfString($str) . ' ';
                    }
                }

                // 3. Next line string: (str) '
                if (preg_match_all('#\((.*?)\)\s*\'#s', $block, $quoteMatches)) {
                    foreach ($quoteMatches[1] as $str) {
                        $text .= "\n" . self::cleanPdfString($str) . ' ';
                    }
                }

                // 4. Hex string operators: <48656c6c6f> Tj
                if (preg_match_all('#<([0-9a-fA-F]+)>\s*(?:Tj|TJ)#', $block, $hexMatches)) {
                    foreach ($hexMatches[1] as $hex) {
                        $decoded = @hex2bin($hex);
                        if ($decoded !== false) {
                            $text .= $decoded . ' ';
                        }
                    }
                }
            }
        }

        return trim($text);
    }

    /**
     * Clean PDF string escaping (octal, parentheses, backslashes)
     */
    private static function cleanPdfString($str) {
        // Replace octal escapes (\ddd)
        $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
            return chr(octdec($m[1]));
        }, $str);

        // Replace standard escapes
        $replacements = [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\b' => "\x08",
            '\\f' => "\x0C",
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\'
        ];
        return strtr($str, $replacements);
    }

    /**
     * Break raw text into semantic chunks for RAG
     *
     * @param string $text Full extracted text
     * @param int $wordsPerChunk Target chunk size in words
     * @param int $overlap Words to overlap between consecutive chunks
     * @return array Array of chunk text strings
     */
    public static function chunkText($text, $wordsPerChunk = 300, $overlap = 40) {
        $text = trim($text);
        if (empty($text)) {
            return [];
        }

        // Split into words
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $totalWords = count($words);

        if ($totalWords <= $wordsPerChunk) {
            return [$text];
        }

        $chunks = [];
        $i = 0;
        $step = max($wordsPerChunk - $overlap, 50);

        while ($i < $totalWords) {
            $slice = array_slice($words, $i, $wordsPerChunk);
            if (!empty($slice)) {
                $chunkStr = implode(' ', $slice);
                $chunks[] = trim($chunkStr);
            }
            $i += $step;
        }

        return $chunks;
    }
}
