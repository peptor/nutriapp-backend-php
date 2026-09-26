<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

// El cos dels missatges és HTML escrit per un usuari i mostrat a un altre: cal netejar-lo al
// servidor amb una llista blanca de format mínim i sense cap atribut (mai s'hi fia el que
// envia el navegador).
class MessageHtml
{
    private const ALLOWED = ['p', 'br', 'strong', 'em', 'u', 'h2', 'h3', 'ul', 'ol', 'li'];

    private const RENAMED = [
        'b' => 'strong', 'i' => 'em', 'div' => 'p',
        'h1' => 'h2', 'h4' => 'h3', 'h5' => 'h3', 'h6' => 'h3',
    ];

    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'head', 'template', 'noscript', 'svg', 'math'];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body) {
            return '';
        }

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= self::clean($child);
        }

        return trim($out);
    }

    public static function toPlainText(string $html): string
    {
        $spaced = preg_replace('/<\/(p|h2|h3|li)>|<br>/i', ' ', $html);
        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function clean(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars(str_replace("\u{00A0}", ' ', $node->nodeValue), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (! $node instanceof DOMElement) {
            return '';
        }

        $name = strtolower($node->nodeName);
        if (in_array($name, self::DROPPED, true)) {
            return '';
        }
        $name = self::RENAMED[$name] ?? $name;

        if ($name === 'br') {
            return '<br>';
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= self::clean($child);
        }

        return in_array($name, self::ALLOWED, true) ? "<{$name}>{$inner}</{$name}>" : $inner;
    }
}
