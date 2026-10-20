<?php

declare(strict_types=1);

use App\Services\CodeSpans;
use App\Services\HighlightedCode;
use App\Services\OpenGraphCanvas;

const WINDOW_X = OpenGraphCanvas::CONTENT_X;

const WINDOW_Y = 60;

const WINDOW_WIDTH = OpenGraphCanvas::CONTENT_RIGHT - OpenGraphCanvas::CONTENT_X;

const WINDOW_HEIGHT = 300;

const CODE_X = WINDOW_X + OpenGraphCanvas::WINDOW_PADDING;

const CODE_Y = WINDOW_Y + OpenGraphCanvas::WINDOW_TITLE_HEIGHT;

const CODE_WIDTH = WINDOW_WIDTH - 2 * OpenGraphCanvas::WINDOW_PADDING;

/**
 * Draws the code block in a window and returns the image.
 */
function drawCodeWindow(string $body): GdImage
{
    [$block] = resolve(CodeSpans::class)->highlight($body);

    assert($block['type'] === 'block');

    $canvas = new OpenGraphCanvas;
    $canvas->window(WINDOW_X, WINDOW_Y, WINDOW_WIDTH, WINDOW_HEIGHT, $block['language']);
    $canvas->code(resolve(HighlightedCode::class)->lines($block['html']), 22, 32, CODE_X, CODE_Y, CODE_WIDTH);

    $image = imagecreatefromstring($canvas->png());

    assert($image instanceof GdImage);

    return $image;
}

/**
 * Every colour in the rectangle, once each.
 *
 * @return list<int>
 */
function colorsIn(GdImage $image, int $x1, int $y1, int $x2, int $y2): array
{
    $colors = [];

    for ($y = $y1; $y < $y2; $y++) {
        for ($x = $x1; $x < $x2; $x++) {
            $colors[(int) imagecolorat($image, $x, $y)] = true;
        }
    }

    return array_keys($colors);
}

it('draws a dark window with three dots in the lanes', function (): void {
    $image = drawCodeWindow("```php\necho 1;\n```");

    expect(imagecolorat($image, WINDOW_X + WINDOW_WIDTH / 2, WINDOW_Y + WINDOW_HEIGHT - 8))->toBe(0x18181A)
        ->and(imagecolorat($image, WINDOW_X + 30, WINDOW_Y + 24))->toBe(0xD9480F)
        ->and(imagecolorat($image, WINDOW_X + 54, WINDOW_Y + 24))->toBe(0xB7791F)
        ->and(imagecolorat($image, WINDOW_X + 78, WINDOW_Y + 24))->toBe(0x2F9E44)
        ->and(imagecolorat($image, WINDOW_X + 1, WINDOW_Y + 1))->toBe(0xF6F7F8);
});

it('names the language in the title bar only when the block has one', function (string $body, bool $isNamed): void {
    $image = drawCodeWindow($body);

    expect(colorsIn($image, WINDOW_X + WINDOW_WIDTH - 120, WINDOW_Y + 8, WINDOW_X + WINDOW_WIDTH - 8, WINDOW_Y + 40) !== [0x18181A])->toBe($isNamed);
})->with([
    'a language' => ["```php\necho 1;\n```", true],
    'no language' => ["```\necho 1;\n```", false],
]);

it('draws each token in its colour', function (): void {
    $image = drawCodeWindow("```php\nreturn \$user;\n```");

    expect(colorsIn($image, CODE_X, CODE_Y, CODE_X + 80, CODE_Y + 32))->toContain(0x7AA2F7)
        ->and(colorsIn($image, CODE_X + 90, CODE_Y, CODE_X + 180, CODE_Y + 32))->toContain(0xF06595);
});

it('cuts a line wider than the window instead of drawing past it', function (): void {
    $image = drawCodeWindow("```\n".str_repeat('x', 500)."\n```");

    expect(colorsIn($image, CODE_X + CODE_WIDTH - 40, CODE_Y, CODE_X + CODE_WIDTH, CODE_Y + 32))->not->toBe([0x18181A])
        ->and(colorsIn($image, CODE_X + CODE_WIDTH + 2, CODE_Y, WINDOW_X + WINDOW_WIDTH - 2, CODE_Y + 32))->toBe([0x18181A]);
});

it('keeps the indentation of each line', function (): void {
    $image = drawCodeWindow("```\nx\n\tx\n```");

    expect(colorsIn($image, CODE_X, CODE_Y + 32, CODE_X + 40, CODE_Y + 64))->toBe([0x18181A])
        ->and(colorsIn($image, CODE_X + 40, CODE_Y + 32, CODE_X + 80, CODE_Y + 64))->not->toBe([0x18181A]);
});

it('draws nothing for emoji, which GeistMono has no glyphs for', function (): void {
    $image = drawCodeWindow("```\n🚀🔥\n```");

    expect(colorsIn($image, CODE_X, CODE_Y, CODE_X + CODE_WIDTH, CODE_Y + 32))->toBe([0x18181A]);
});
