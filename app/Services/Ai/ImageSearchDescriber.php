<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Support\ColorPalette;
use Illuminate\Http\UploadedFile;

/**
 * Turns a shopper's photo into catalog search terms: the garment type, a
 * palette color and a category. The AI only describes the photo; the
 * products themselves are always found by the regular catalog search, so it
 * can never invent one. The photo is read from the upload and never stored.
 */
class ImageSearchDescriber
{
    /**
     * Longest edge sent to the model; plenty to recognise a garment and
     * keeps the request small and fast.
     */
    private const MAX_EDGE = 768;

    private const MAX_KEYWORDS_LENGTH = 60;

    public function __construct(private readonly AiProviderContract $provider) {}

    /**
     * @return array{keywords: string, color: string|null, category: string|null} category is a slug
     *
     * @throws InvalidAiResponseException when the reply is unusable or shows no garment
     */
    public function describe(UploadedFile $image): array
    {
        $categories = $this->categoryPaths();

        $reply = $this->provider->complete([[
            'role' => 'user',
            'content' => [
                ['type' => 'image', 'media_type' => 'image/jpeg', 'data' => base64_encode($this->downscaled($image))],
                ['type' => 'text', 'text' => 'Tìm trong shop món đồ giống trong ảnh này. Trả JSON.'],
            ],
        ]], $this->systemPrompt(array_keys($categories)));

        return $this->parse($reply, $categories);
    }

    /**
     * @param  list<string>  $categoryPaths
     */
    private function systemPrompt(array $categoryPaths): string
    {
        $categories = implode("\n", array_map(fn (string $path): string => "- {$path}", $categoryPaths));
        $colors = implode(', ', ColorPalette::names());

        return <<<PROMPT
            Bạn giúp khách tìm sản phẩm trong một shop quần áo bằng ảnh họ tải lên.
            Nhìn ảnh và nhận diện MỘT món quần áo nổi bật nhất (nếu có người mặc nhiều món, chọn món chiếm nhiều diện tích nhất).
            Chỉ trả về đúng một object JSON, không kèm chữ nào khác:
            {
              "is_clothing": true hoặc false (false nếu ảnh không có quần áo),
              "keywords": "loại đồ bằng tiếng Việt, 1 đến 3 từ, ví dụ: áo khoác jean, hoodie, quần short, chân váy xếp ly, áo polo",
              "color": "nguyên văn MỘT tên trong bảng màu: {$colors}; chọn màu gần nhất",
              "category": "nguyên văn MỘT dòng trong danh sách danh mục bên dưới phù hợp nhất (kể cả Nam/Nữ), hoặc chuỗi rỗng"
            }
            Không đoán thương hiệu, giá hay size.

            Danh mục của shop:
            {$categories}
            PROMPT;
    }

    /**
     * @param  array<string, string>  $categories  path => slug
     * @return array{keywords: string, color: string|null, category: string|null}
     */
    private function parse(string $reply, array $categories): array
    {
        $json = preg_match('/\{.*\}/s', $reply, $match) ? $match[0] : $reply;
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new InvalidAiResponseException('Image search reply is not a JSON object.');
        }

        if (($data['is_clothing'] ?? true) === false) {
            throw new InvalidAiResponseException('No garment in the photo.');
        }

        $keywords = is_string($data['keywords'] ?? null) ? trim($data['keywords']) : '';
        if ($keywords === '') {
            throw new InvalidAiResponseException('Image search reply has no keywords.');
        }

        $category = is_string($data['category'] ?? null) ? trim($data['category']) : '';

        return [
            'keywords' => mb_substr($keywords, 0, self::MAX_KEYWORDS_LENGTH),
            'color' => ColorPalette::match(is_string($data['color'] ?? null) ? $data['color'] : null),
            'category' => $categories[$category] ?? null,
        ];
    }

    /**
     * Active leaf categories as "Nam > Áo nam > Áo thun" => slug; the full
     * path tells the model apart the "Áo thun" under Nam and under Nữ.
     *
     * @return array<string, string>
     */
    private function categoryPaths(): array
    {
        $all = Category::query()->where('is_active', true)->get(['id', 'parent_id', 'name', 'slug'])->keyBy('id');
        $parentIds = $all->pluck('parent_id')->filter()->unique();

        $paths = [];
        foreach ($all->whereNotIn('id', $parentIds) as $leaf) {
            $names = [];
            for ($node = $leaf; $node !== null; $node = $all->get($node->parent_id)) {
                array_unshift($names, $node->name);
            }
            $paths[implode(' > ', $names)] = $leaf->slug;
        }
        ksort($paths);

        return $paths;
    }

    /**
     * Re-encode as a JPEG no larger than MAX_EDGE; the original bytes are
     * sent as-is if GD cannot read the file.
     */
    private function downscaled(UploadedFile $image): string
    {
        $bytes = (string) file_get_contents($image->getRealPath());
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return $bytes;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $resized = imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

        ob_start();
        imagejpeg($resized ?: $source, null, 85);

        return (string) ob_get_clean();
    }
}
