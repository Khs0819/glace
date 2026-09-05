<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Allowlist sanitiser for dashboard-authored HTML (handoff 16 · 17).
 *
 * Both pages are rendered by the storefront through dangerouslySetInnerHTML.
 * The frontend runs DOMPurify over them and does not assume this pass happened;
 * this pass does not assume theirs will either. Defence in depth, as the
 * handoff asks for by name.
 *
 * Allowlist, never denylist: anything not named here is removed, so a tag or
 * attribute nobody thought of fails closed instead of sailing through.
 */
class HtmlSanitizer
{
    /** Exactly what handoff 16/17 says the pages are built from. */
    private const ALLOWED_TAGS = [
        'h3', 'h4', 'p', 'ul', 'ol', 'li', 'a', 'strong', 'em', 'b', 'i', 'br', 'span',
    ];

    /** Per tag. Everything else — every on* handler, style, id, class — is dropped. */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
    ];

    /**
     * Schemes a link may use. `javascript:` and `data:` are the two that turn a
     * href into script execution, so only these three are permitted, plus the
     * relative paths the site's own links use.
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    public static function clean(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        // The fragment is not a document and has no encoding declaration, so it
        // is wrapped in one — otherwise DOMDocument assumes ISO-8859-1 and every
        // Arabic character comes back mangled.
        $wrapped = '<?xml encoding="UTF-8"?><body>' . $html . '</body>';

        $previous = libxml_use_internal_errors(true);

        // Malformed markup is the normal case for hand-edited HTML, and the
        // parser's complaints about it are not interesting — the allowlist pass
        // below is what actually decides the output.
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        // Comments can carry conditional-comment payloads and are never content.
        foreach (iterator_to_array($xpath->query('//comment()') ?: []) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body) {
            return '';
        }

        self::scrub($body);

        $out = '';

        foreach ($body->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /** Depth-first, over a snapshot: the list mutates as nodes are unwrapped. */
    private static function scrub(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::scrub($child);
                self::apply($child);

                continue;
            }

            // Text is kept; anything else at this level (CDATA, processing
            // instructions) is not content this system produces.
            if ($child->nodeType !== XML_TEXT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function apply(DOMElement $element): void
    {
        $tag = strtolower($element->nodeName);

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            // The tag goes, its text stays. A disallowed <div> should not take
            // a paragraph of terms down with it — except for <script>/<style>,
            // whose text content IS the payload.
            in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)
                ? $element->parentNode?->removeChild($element)
                : self::unwrap($element);

            return;
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name    = strtolower($attribute->nodeName);
            $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if ($name === 'href' && ! self::safeHref($attribute->nodeValue ?? '')) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        // A link opening a new tab without this hands the opener to the target
        // page via window.opener.
        if ($tag === 'a' && $element->getAttribute('target') !== '') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private static function safeHref(string $href): bool
    {
        $href = trim($href);

        if ($href === '') {
            return false;
        }

        // Relative and anchor links carry no scheme and cannot execute.
        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return true;
        }

        // Control characters and entities are how "java&#09;script:" gets past a
        // naive prefix check, so the scheme is read from the decoded, stripped
        // string rather than the raw one.
        $normalized = strtolower(preg_replace('/[\x00-\x20\x7F]/u', '', html_entity_decode($href, ENT_QUOTES, 'UTF-8')) ?? '');

        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/', $normalized, $matches)) {
            return true; // relative path such as "contact"
        }

        return in_array($matches[1], self::ALLOWED_SCHEMES, true);
    }
}
