<?php

namespace App\Http\Requests\Storefront\Concerns;

use App\Services\Address\AdministrativeDivisions;
use Illuminate\Validation\Validator;

/**
 * Shared by the address book and checkout's "new address": when the
 * province/ward list is available, the typed province and ward must be
 * real ones (ward inside that province). They are replaced by the official
 * names and the province code before validation, and the district — gone
 * since the 2025 reorganisation — is cleared. When the list can't be loaded
 * the names are kept as typed, so an outage never blocks a customer.
 */
trait ChecksAdministrativeDivisions
{
    private ?string $divisionError = null;

    /**
     * Whether this request carries an address to check.
     */
    abstract protected function hasAddressFields(): bool;

    protected function prepareForValidation(): void
    {
        $this->divisionError = null;

        if (! $this->hasAddressFields()) {
            return;
        }

        $this->merge(['province_code' => null, 'district_name' => null]);

        $provinceName = $this->input('province_name');
        $wardName = $this->input('ward_name');
        $divisions = app(AdministrativeDivisions::class);

        if (! is_string($provinceName) || ! is_string($wardName) || trim($provinceName) === '' || trim($wardName) === '' || $divisions->provinces() === null) {
            return;
        }

        $province = $divisions->findProvince($provinceName);
        if ($province === null) {
            $this->divisionError = 'province_name';

            return;
        }

        $this->merge(['province_code' => (string) $province['code'], 'province_name' => $province['name']]);

        $ward = $divisions->findWard($province['code'], $wardName);
        if ($ward !== null) {
            $this->merge(['ward_name' => $ward['name']]);
        } elseif ($divisions->wards($province['code']) !== null) {
            $this->divisionError = 'ward_name';
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function divisionRules(): array
    {
        return [
            'province_code' => ['nullable', 'string', 'max:20'],
            'district_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->divisionError === null || $validator->errors()->hasAny(['province_name', 'ward_name'])) {
                    return;
                }

                $validator->errors()->add($this->divisionError, $this->divisionError === 'province_name'
                    ? 'Vui lòng chọn Tỉnh/Thành phố trong danh sách.'
                    : 'Phường/Xã không thuộc '.$this->input('province_name').' — vui lòng chọn trong danh sách gợi ý.');
            },
        ];
    }
}
