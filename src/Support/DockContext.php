<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Support;

use BackedEnum;
use Closure;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Resource;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;

final class DockContext
{
    private ?string $routeName = null;

    /**
     * @var array<string, scalar | null>
     */
    private array $routeParameters = [];

    private ?string $path = null;

    private ?Model $record = null;

    private bool $hasResolvedRecord = false;

    public static function current(): self
    {
        return app(self::class);
    }

    public static function fromRoute(?Route $route, ?string $path = null): self
    {
        $context = new self;

        if (! $route) {
            return $context->set(null, [], $path);
        }

        $parameters = array_map(fn (mixed $value): mixed => match (true) {
            $value instanceof UrlRoutable => $value->getRouteKey(),
            $value instanceof BackedEnum => $value->value,
            is_scalar($value) => $value,
            default => null,
        }, $route->hasParameters() ? $route->parameters() : []);

        return $context->set($route->getName(), $parameters, $path);
    }

    /**
     * @param  array<string, scalar | null>  $parameters
     */
    public function set(?string $routeName, array $parameters, ?string $path = null): self
    {
        $this->routeName = $routeName;
        $this->routeParameters = $parameters;
        $this->path = $path === null ? null : trim($path, '/');
        $this->record = null;
        $this->hasResolvedRecord = false;

        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    /**
     * @return array<string, scalar | null>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @return class-string | null
     */
    public function getPageClass(): ?string
    {
        if (blank($this->routeName)) {
            return null;
        }

        $class = Router::getRoutes()->getByName($this->routeName)?->getControllerClass();

        return is_string($class) && class_exists($class) ? $class : null;
    }

    public static function pathIs(string ...$patterns): bool
    {
        $path = self::current()->getPath();

        return $path !== null && Str::is(array_map(fn (string $pattern): string => trim($pattern, '/'), $patterns), $path);
    }

    public static function routeIs(string ...$patterns): bool
    {
        $routeName = self::current()->getRouteName();

        return filled($routeName) && Str::is($patterns, $routeName);
    }

    /**
     * @param  class-string<resource>  $resource
     * @param  array<string> | null  $pages
     * @return Closure(): bool
     */
    public static function resource(string $resource, ?array $pages = null): Closure
    {
        return function () use ($resource, $pages): bool {
            $base = $resource::getRouteBaseName(Filament::getCurrentOrDefaultPanel());

            $patterns = $pages === null
                ? ["{$base}.*"]
                : array_map(fn (string $page): string => "{$base}.{$page}", $pages);

            return self::routeIs(...$patterns);
        };
    }

    public static function record(): ?Model
    {
        return self::current()->resolveRecord();
    }

    private function resolveRecord(): ?Model
    {
        if ($this->hasResolvedRecord) {
            return $this->record;
        }

        $this->hasResolvedRecord = true;

        $key = $this->routeParameters['record'] ?? null;

        if (blank($this->routeName) || ! (is_string($key) || is_int($key))) {
            return null;
        }

        $page = $this->getPageClass();

        if (! $page || ! in_array(InteractsWithRecord::class, class_uses_recursive($page), true)) {
            return null;
        }

        return $this->record = $page::getResource()::resolveRecordRouteBinding($key);
    }
}
