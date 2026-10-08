<?php

declare(strict_types=1);

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyPlaygroundOptions
{
    /**
     * @var array<int, string>
     */
    public const array OPTIONS = ['qad-style', 'qad-layout', 'qad-position', 'qad-locale', 'qad-dir', 'qad-labels', 'qad-poll', 'qad-mobile', 'qad-demo'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::OPTIONS as $option) {
            if ($request->query->has($option)) {
                $request->session()->put($option, (string) $request->query($option));
            }
        }

        $locale = self::get('qad-locale');

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    public static function get(string $option): ?string
    {
        $value = session($option);

        return filled($value) ? (string) $value : null;
    }
}
