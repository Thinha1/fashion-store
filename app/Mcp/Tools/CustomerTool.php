<?php

namespace App\Mcp\Tools;

use App\Models\User;

/**
 * A read-only MCP tool about customers. Everything personal stays masked: the
 * name is shortened to initials (LatestOrdersTool::maskName) and no email,
 * full phone number or address is returned; staff open the admin page, given
 * as `admin_url`, for the details. Needs customers.view.
 */
abstract class CustomerTool extends ShopTool
{
    protected function permissions(): array
    {
        return ['customers.view'];
    }

    /**
     * The fields every customer row of these tools shares.
     *
     * @return array{id: int, name: string, phone_last3: ?string, admin_url: string}
     */
    protected function identify(User $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => LatestOrdersTool::maskName((string) $customer->name),
            'phone_last3' => $this->lastDigits($customer->phone),
            'admin_url' => route('admin.customers.show', $customer),
        ];
    }

    /** The last three digits of a phone number, the only part shown to tell two customers apart. */
    protected function lastDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits === '' ? null : '***'.substr($digits, -3);
    }

    protected function money(mixed $amount): int
    {
        return (int) round((float) $amount);
    }
}
