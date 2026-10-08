<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

const LANG_PATH = __DIR__.'/../../resources/lang';

/** @var array<string, list<string>> keys that may legitimately read the same as English, per locale */
const IDENTICAL_TO_ENGLISH = [];

/** @return list<string> */
function shippedLocales(): array
{
    return collect(scandir(LANG_PATH))
        ->reject(fn (string $locale): bool => str_starts_with($locale, '.'))
        ->values()
        ->all();
}

/** @return array<string, string> */
function translationsFor(string $locale): array
{
    return Arr::dot(require LANG_PATH."/{$locale}/dock.php");
}

/** @return list<string> */
function placeholdersIn(string $value): array
{
    preg_match_all('/:[A-Za-z_]+/', $value, $matches);

    return collect($matches[0])->unique()->sort()->values()->all();
}

dataset('locales', fn (): array => array_values(array_diff(shippedLocales(), ['en'])));

it('ships every locale Filament users expect', function (): void {
    expect(shippedLocales())->toBe(['ar', 'cs', 'de', 'en', 'es', 'fa', 'fr', 'he', 'hi', 'id', 'it', 'ja', 'ko', 'nl', 'pl', 'pt', 'pt_BR', 'ru', 'tr', 'uk', 'vi', 'zh_CN', 'zh_TW']);
});

it('has exactly the same keys as English', function (string $locale): void {
    expect(array_keys(translationsFor($locale)))->toBe(array_keys(translationsFor('en')));
})->with('locales');

it('keeps the same placeholders as English', function (string $locale): void {
    $translated = translationsFor($locale);

    foreach (translationsFor('en') as $key => $english) {
        expect(placeholdersIn($translated[$key]))->toBe(placeholdersIn($english), "{$locale}.{$key}");
    }
})->with('locales');

it('has no empty values', function (string $locale): void {
    foreach (translationsFor($locale) as $key => $value) {
        expect(trim($value))->not->toBeEmpty("{$locale}.{$key}");
    }
})->with('locales');

it('translates every value instead of copying English', function (string $locale): void {
    $english = translationsFor('en');
    $allowed = IDENTICAL_TO_ENGLISH[$locale] ?? [];

    foreach (translationsFor($locale) as $key => $value) {
        if (in_array($key, $allowed, true)) {
            continue;
        }

        expect($value)->not->toBe($english[$key], "{$locale}.{$key} is still English");
    }
})->with('locales');
