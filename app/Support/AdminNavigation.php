<?php

namespace App\Support;

use App\Models\User;

/**
 * The admin sidebar: each link with the permission that opens its page, so
 * a staff member only sees what their role lets them use. Also picks the
 * page to land on after sign-in for someone who cannot see the dashboard.
 */
class AdminNavigation
{
    /**
     * Group label => list of [route name, route pattern for the active state, icon, label, permission].
     *
     * @var array<string, list<array{0: string, 1: string, 2: string, 3: string, 4: string}>>
     */
    private const GROUPS = [
        'Điều hành' => [
            ['admin.dashboard', 'admin.dashboard', 'grid', 'Tổng quan', 'dashboard.view'],
        ],
        'Bán hàng' => [
            ['admin.orders.index', 'admin.orders.*', 'orders', 'Đơn hàng', 'orders.view'],
        ],
        'Sản phẩm & thương hiệu' => [
            ['admin.products.index', 'admin.products.*', 'shirt', 'Sản phẩm', 'products.manage'],
            ['admin.categories.index', 'admin.categories.*', 'layers', 'Danh mục', 'catalog.manage'],
            ['admin.brands.index', 'admin.brands.*', 'tag', 'Thương hiệu', 'catalog.manage'],
            ['admin.size-charts.index', 'admin.size-charts.*', 'table', 'Bảng size', 'catalog.manage'],
            ['admin.discounts.index', 'admin.discounts.*', 'percent', 'Giảm giá', 'discounts.manage'],
        ],
        'Kho & cung ứng' => [
            ['admin.suppliers.index', 'admin.suppliers.*', 'truck', 'Nhà cung cấp', 'suppliers.manage'],
            ['admin.goods-receipts.index', 'admin.goods-receipts.*', 'box', 'Phiếu nhập hàng', 'inventory.manage'],
        ],
        'Nhân sự & phân quyền' => [
            ['admin.staff.index', 'admin.staff.*', 'user', 'Nhân viên', 'staff.manage'],
            ['admin.roles.index', 'admin.roles.*', 'key', 'Vai trò & quyền', 'roles.manage'],
        ],
        'Hệ thống' => [
            ['admin.settings.ai.edit', 'admin.settings.ai.*', 'settings', 'Cấu hình AI', 'settings.ai'],
            ['admin.settings.payment.edit', 'admin.settings.payment.*', 'revenue', 'Thanh toán QR', 'settings.payment'],
            ['admin.audit-logs.index', 'admin.audit-logs.*', 'clock', 'Nhật ký hệ thống', 'audit.view'],
        ],
    ];

    /**
     * The groups and links this user may open; empty groups are left out.
     *
     * @return array<string, list<array{route: string, pattern: string, icon: string, label: string}>>
     */
    public static function for(User $user): array
    {
        $groups = [];
        foreach (self::GROUPS as $group => $links) {
            foreach ($links as [$route, $pattern, $icon, $label, $permission]) {
                if ($user->hasPermission($permission)) {
                    $groups[$group][] = ['route' => $route, 'pattern' => $pattern, 'icon' => $icon, 'label' => $label];
                }
            }
        }

        return $groups;
    }

    /**
     * Where /admin sends a user: the dashboard when they may see it, else
     * their first page, null when they can open nothing at all.
     */
    public static function landingRoute(User $user): ?string
    {
        foreach (self::for($user) as $links) {
            return $links[0]['route'];
        }

        return null;
    }
}
