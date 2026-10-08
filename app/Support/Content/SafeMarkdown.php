<?php

namespace App\Support\Content;

use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Renders editor text (Markdown) to safe HTML. Temporary content format until
 * the controlled block editor exists (docs/content-model.md#content-safety).
 *
 * - Raw HTML is escaped, never passed through (no <script>, no on*= handlers).
 * - Unsafe link schemes (javascript:, vbscript:, file:, data:) are removed.
 * - Images are NOT rendered (their alt text is shown instead): no external
 *   requests, no untracked media; images will come from the media library.
 * - Headings start at h2 (the page title is the only h1).
 */
final class SafeMarkdown
{
    private static ?MarkdownConverter $converter = null;

    public static function toHtml(?string $markdown): HtmlString
    {
        if ($markdown === null || trim($markdown) === '') {
            return new HtmlString('');
        }

        return new HtmlString((string) self::converter()->convert($markdown));
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter !== null) {
            return self::$converter;
        }

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);

        $environment->addRenderer(Image::class, new class implements NodeRendererInterface
        {
            public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
            {
                return new HtmlElement('span', ['class' => 'content-image-placeholder'], $childRenderer->renderNodes($node->children()));
            }
        }, 100);

        $environment->addRenderer(Heading::class, new class implements NodeRendererInterface
        {
            public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
            {
                /** @var Heading $node */
                return new HtmlElement('h'.max(2, $node->getLevel()), [], $childRenderer->renderNodes($node->children()));
            }
        }, 100);

        return self::$converter = new MarkdownConverter($environment);
    }
}
