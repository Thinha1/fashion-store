<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurrencyInputTest extends TestCase
{
    public static function fieldValues(): array
    {
        return [
            'required decimal' => [true, '1234.56'],
            'required zero' => [true, '0'],
            'required empty' => [true, ''],
            'optional empty' => [false, ''],
            'optional decimal' => [false, '1234.56'],
        ];
    }

    #[DataProvider('fieldValues')]
    public function test_native_input_preserves_required_and_raw_value_without_javascript(bool $required, string $value): void
    {
        $html = Blade::render('<x-currency-input id="price" name="price" :value="$value" :required="$required" class="mt-1" />', compact('value', 'required'));
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $input = $xpath->query('//input[@id="price"]')->item(0);

        $this->assertSame($required, $input->hasAttribute('required'));
        $this->assertSame($value, $input->getAttribute('value'));
        $this->assertFalse($input->hasAttribute('disabled'));
        $this->assertSame(1, $xpath->query('//input[@name="price"]')->length);
        $this->assertStringContainsString('field', $input->getAttribute('class'));
        $this->assertStringContainsString('mt-1', $input->getAttribute('class'));
    }
}
