<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Collection;

/**
 * How the role form lays out the permission catalog: groups in a fixed order
 * with Vietnamese headings, and a note on the permissions whose admin
 * screen does not exist yet (granting them changes nothing today).
 */
class PermissionGroups
{
    public const LABELS = [
        'system' => 'Hệ thống',
        'catalog' => 'Sản phẩm & catalog',
        'inventory' => 'Kho & cung ứng',
        'orders' => 'Đơn hàng',
        'customers' => 'Khách hàng',
    ];

    /** Permissions reserved for screens that are not built yet. */
    public const NOT_BUILT = ['returns.manage', 'reviews.manage', 'coupons.manage', 'customers.view'];

    /**
     * @return Collection<string, Collection<int, Permission>> group label => permissions
     */
    public static function grouped(): Collection
    {
        $order = array_flip(array_keys(self::LABELS));

        return Permission::query()->orderBy('code')->get()
            ->sortBy(fn (Permission $permission): int => $order[$permission->group] ?? count($order))
            ->groupBy(fn (Permission $permission): string => self::LABELS[$permission->group] ?? 'Khác');
    }
}
