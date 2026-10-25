<?php

use PHPUnit\Framework\TestCase;

class ExtractorTest extends TestCase {

    private function used_css(string $html, string $css): string {
        $html = str_replace('</head>', '<style>' . $css . '</style></head>', $html);

        $extractor = new BQUCSS_Extractor('https://example.com');
        return $extractor->process_css($html);
    }

    public function test_single_id_selector_in_html(): void {
        $html = '<html><head></head><body><div class="header"><p id="intro">Hello</p></div></body></html>';
        $css  = '#intro { color: red; } #footer { color: blue; }';

        $used_css = $this->used_css($html, $css);

        $this->assertStringContainsString('#intro', $used_css);
        $this->assertStringNotContainsString('#footer', $used_css);
    }

    public function test_single_class_selector_in_html(): void {
        $html = '<html><head></head><body><div class="header"><p id="intro">Hello</p></div></body></html>';
        $css  = '.header { color: red; } .footer { color: blue; }';

        $used_css = $this->used_css($html, $css);

        $this->assertStringContainsString('.header', $used_css);
        $this->assertStringNotContainsString('.footer', $used_css);
    }

    public function test_next_token_for_single_class_selector(): void {
        $selector = ".header";

        $extractor = new BQUCSS_Extractor('https://example.com');
        $token = $extractor::next_token($selector);
        $expected = [
            'char' => '.',
            'pos' => 0,
        ];
        
        $this->assertSame($expected, $token);
    }
}
