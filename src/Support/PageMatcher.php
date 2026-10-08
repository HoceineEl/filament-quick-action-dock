<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Support;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Support\Str;

final class PageMatcher
{
    /**
     * Rules: route name or URL path patterns, Resource or Page classes, or [Resource::class, ['edit']] pairs.
     *
     * @param  array<int | string, mixed>  $rules
     */
    public static function matches(array $rules, DockContext $context): bool
    {
        foreach ($rules as $rule) {
            if (self::matchesRule($rule, $context)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesRule(mixed $rule, DockContext $context): bool
    {
        if (is_array($rule)) {
            [$resource, $pages] = array_pad(array_values($rule), 2, null);

            return is_string($resource)
                && is_subclass_of($resource, Resource::class)
                && self::matchesResource($resource, (array) $pages, $context);
        }

        if (! is_string($rule) || blank($rule)) {
            return false;
        }

        if (is_subclass_of($rule, Resource::class)) {
            return self::matchesResource($rule, [], $context);
        }

        if (is_subclass_of($rule, Page::class)) {
            $page = $context->getPageClass();

            return $page !== null && is_a($page, $rule, true);
        }

        if (str_contains($rule, '/')) {
            $path = $context->getPath();

            return $path !== null && Str::is(trim($rule, '/'), $path);
        }

        $routeName = $context->getRouteName();

        return filled($routeName) && Str::is($rule, $routeName);
    }

    /**
     * @param  class-string<resource>  $resource
     * @param  array<string>  $pages
     */
    private static function matchesResource(string $resource, array $pages, DockContext $context): bool
    {
        $routeName = $context->getRouteName();

        if (blank($routeName)) {
            return false;
        }

        $base = $resource::getRouteBaseName(Filament::getCurrentOrDefaultPanel());
        $patterns = filled($pages)
            ? array_map(fn (string $page): string => "{$base}.{$page}", $pages)
            : ["{$base}.*"];

        return Str::is($patterns, $routeName);
    }
}
