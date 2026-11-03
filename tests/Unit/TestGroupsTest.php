<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Every feature test class sits in exactly one area group, so a slice of the
 * suite can run alone while working on that area:
 *   php artisan test --group=orders
 * The full `php artisan test` still runs everything (CI does).
 */
class TestGroupsTest extends TestCase
{
    /** accounts: sign-in, roles, staff, customers. admin: shared admin screens, reports, audit, Excel. */
    private const GROUPS = ['accounts', 'admin', 'ai', 'catalog', 'inventory', 'mcp', 'orders', 'pricing', 'reviews', 'system'];

    public function test_every_feature_test_class_belongs_to_exactly_one_known_group(): void
    {
        $problems = [];

        foreach ($this->featureTestClasses() as $class) {
            $groups = $this->groupsOf($class);

            if (count($groups) !== 1 || ! in_array($groups[0], self::GROUPS, true)) {
                $problems[] = $class.' has '.($groups === [] ? 'no group' : 'groups ['.implode(', ', $groups).']');
            }
        }

        $this->assertSame([], $problems, "Put exactly one #[Group('<area>')] on each feature test class; areas: ".implode(', ', self::GROUPS).'.');
    }

    public function test_every_listed_group_has_tests(): void
    {
        $used = array_unique(array_merge(...array_map($this->groupsOf(...), $this->featureTestClasses())));
        sort($used);

        $this->assertSame(self::GROUPS, $used);
    }

    /**
     * @return list<class-string>
     */
    private function featureTestClasses(): array
    {
        $root = dirname(__DIR__).'/Feature';
        $classes = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with($file->getFilename(), 'Test.php')) {
                $relative = substr($file->getPathname(), strlen($root) + 1, -4);
                $classes[] = 'Tests\\Feature\\'.str_replace(['/', '\\'], '\\', $relative);
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @param  class-string  $class
     * @return list<string>
     */
    private function groupsOf(string $class): array
    {
        return array_map(
            fn ($attribute): string => $attribute->newInstance()->name(),
            (new ReflectionClass($class))->getAttributes(Group::class),
        );
    }
}
