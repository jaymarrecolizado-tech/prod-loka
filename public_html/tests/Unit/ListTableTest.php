<?php

/**
 * Unit tests for shared list table UI helpers.
 */

if (!function_exists('e')) {
    function e(?string $string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../../includes/list_table.php';

class ListTableTest extends PHPUnit\Framework\TestCase
{
    public function testListSearchFieldHtmlIncludesValueAndPlaceholder(): void
    {
        $html = listSearchFieldHtml('abc', 'Purpose, requester...');
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('value="abc"', $html);
        $this->assertStringContainsString('placeholder="Purpose, requester..."', $html);
        $this->assertStringContainsString('Search', $html);
    }

    public function testListSearchFieldHtmlEscapesContent(): void
    {
        $html = listSearchFieldHtml('"><script>', 'a"b');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&quot;', $html);
    }
}
