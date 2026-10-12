<?php

namespace App\Services\Address;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Vietnam's provinces and wards, from the public provinces.open-api.vn API
 * (v2: the two-level structure in force since 01/07/2025 — 34 provinces,
 * wards directly under them, no districts). Results are cached for
 * `services.vn_divisions.cache_days`; when the API can't be reached the
 * methods return null and callers fall back to free-text address fields.
 * A failed call is remembered for a few minutes so a slow or down API
 * doesn't stall every page that renders an address form.
 */
class AdministrativeDivisions
{
    private const FAILURE_PAUSE_SECONDS = 300;

    /**
     * Sorted by name without the "Tỉnh"/"Thành phố" prefix, which is also
     * the label shown in the province picker.
     *
     * @return list<array{code: int, name: string, label: string}>|null
     */
    public function provinces(): ?array
    {
        return $this->remember('vn-divisions:provinces', '/p/', function (array $payload): array {
            $provinces = collect($payload)
                ->filter(fn ($province): bool => is_array($province) && is_int($province['code'] ?? null) && is_string($province['name'] ?? null))
                ->map(fn (array $province): array => [
                    'code' => $province['code'],
                    'name' => $province['name'],
                    'label' => preg_replace('/^(Thành phố|Tỉnh)\s+/u', '', $province['name']),
                ]);

            return $this->sortByLabel($provinces->all());
        });
    }

    /**
     * @return list<array{code: int, name: string}>|null
     */
    public function wards(int $provinceCode): ?array
    {
        return $this->remember("vn-divisions:wards:{$provinceCode}", "/p/{$provinceCode}?depth=2", function (array $payload): array {
            $wards = collect($payload['wards'] ?? [])
                ->filter(fn ($ward): bool => is_array($ward) && is_int($ward['code'] ?? null) && is_string($ward['name'] ?? null))
                ->map(fn (array $ward): array => [
                    'code' => $ward['code'],
                    'name' => $ward['name'],
                    'label' => preg_replace('/^(Phường|Xã|Đặc khu)\s+/u', '', $ward['name']),
                ]);

            return array_map(fn (array $ward): array => ['code' => $ward['code'], 'name' => $ward['name']], $this->sortByLabel($wards->all()));
        });
    }

    /**
     * @return array{code: int, name: string, label: string}|null
     */
    public function findProvince(string $name): ?array
    {
        $needle = $this->normalize($name);

        return collect($this->provinces() ?? [])
            ->first(fn (array $province): bool => in_array($needle, [$this->normalize($province['name']), $this->normalize($province['label'])], true));
    }

    /**
     * @return array{code: int, name: string}|null
     */
    public function findWard(int $provinceCode, string $name): ?array
    {
        $needle = $this->normalize($name);

        return collect($this->wards($provinceCode) ?? [])
            ->first(fn (array $ward): bool => $this->normalize($ward['name']) === $needle);
    }

    public function isEnabled(): bool
    {
        return filled(config('services.vn_divisions.base_url'));
    }

    /**
     * @param  callable(array<mixed>): list<array<string, mixed>>  $map
     * @return list<array<string, mixed>>|null
     */
    private function remember(string $key, string $path, callable $map): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        if (Cache::has($key.':failed')) {
            return null;
        }

        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(5)
                ->get(rtrim((string) config('services.vn_divisions.base_url'), '/').$path);
        } catch (ConnectionException) {
            return $this->failed($key);
        }

        $payload = $response->successful() ? $response->json() : null;
        $data = is_array($payload) ? $map($payload) : [];
        if ($data === []) {
            return $this->failed($key);
        }

        Cache::put($key, $data, now()->addDays(max(1, (int) config('services.vn_divisions.cache_days'))));

        return $data;
    }

    private function failed(string $key): null
    {
        Cache::put($key.':failed', true, self::FAILURE_PAUSE_SECONDS);

        return null;
    }

    /**
     * @template T of array{label: string}
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function sortByLabel(array $items): array
    {
        usort($items, fn (array $a, array $b): int => strcmp($this->sortKey($a['label']), $this->sortKey($b['label'])));

        return $items;
    }

    /**
     * Vietnamese-ish ordering without intl: "Đ" sorts right after "D".
     */
    private function sortKey(string $label): string
    {
        return Str::of($label)->lower()->replace('đ', 'dz')->ascii()->toString();
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }
}
