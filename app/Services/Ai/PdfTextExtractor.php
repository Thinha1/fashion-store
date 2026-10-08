<?php

namespace App\Services\Ai;

use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Pulls the text layer out of an uploaded PDF (a supplier catalog, a spec
 * sheet, a price quote) so it can ride along in a chat turn like any other
 * attachment. Pure PHP and size-bounded: a PDF is untrusted input that ends
 * up in an LLM prompt, so it is capped in pages and characters, images are
 * never loaded, and a scan with no text layer is refused with a clear
 * message instead of silently sending the model nothing.
 */
class PdfTextExtractor
{
    public const MAX_PAGES = 30;

    /** Keeps one attachment to a few prompt chunks — the whole conversation is resent every turn. */
    public const MAX_CHARS = 11000;

    private const MIN_USEFUL_CHARS = 20;

    private const DECODE_MEMORY_LIMIT = 32 * 1024 * 1024;

    /**
     * @return array{text: string, pages: int, truncated: bool}
     *
     * @throws ValidationException
     */
    public function extract(string $path): array
    {
        try {
            $config = new Config;
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(self::DECODE_MEMORY_LIMIT);
            $pages = (new Parser([], $config))->parseFile($path)->getPages();
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'Không đọc được file PDF (file hỏng hoặc có đặt mật khẩu).']);
        }

        $text = '';
        $truncated = count($pages) > self::MAX_PAGES;
        foreach (array_slice($pages, 0, self::MAX_PAGES) as $page) {
            try {
                $text .= "\n".$page->getText();
            } catch (Throwable) {
                continue; // one unreadable page should not lose the others
            }
            if (mb_strlen($text) > self::MAX_CHARS * 2) {
                $truncated = true;
                break;
            }
        }

        $text = self::tidy($text);
        if (mb_strlen($text) < self::MIN_USEFUL_CHARS) {
            throw ValidationException::withMessages(['file' => 'PDF này không có chữ để đọc (có thể là bản scan hoặc toàn ảnh). Hãy chụp ảnh các trang cần dùng rồi đính kèm ảnh.']);
        }
        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = rtrim(mb_substr($text, 0, self::MAX_CHARS));
            $truncated = true;
        }

        return ['text' => $text, 'pages' => count($pages), 'truncated' => $truncated];
    }

    private static function tidy(string $text): string
    {
        // Control characters and invalid UTF-8 would make the JSON request to the model fail.
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = (string) preg_replace('/[^\P{C}\n\t]+/u', ' ', $text);
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string) preg_replace('/ ?\n ?/', "\n", $text);

        return trim((string) preg_replace('/\n{3,}/', "\n\n", $text));
    }
}
