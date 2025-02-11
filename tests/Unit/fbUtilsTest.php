<?php
declare(strict_types=1);


class fbUtilsTest extends WP_UnitTestCase {
    public function testRemoveHtmlTags() {
        $string = '<p>Hello World!</p>';
        $expectedOutput = 'Hello World!';
        $actualOutput = WC_Facebookcommerce_Utils::clean_string($string, true);
        $this->assertEquals($expectedOutput, $actualOutput);
    } 

    public function testKeepHtmlTags() {
        $string = '<p>Hello World!</p>';
        $expectedOutput = '<p>Hello World!</p>';
        $actualOutput = WC_Facebookcommerce_Utils::clean_string($string, false);
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    public function testReplaceSpecialCharacters() {
        $string = 'Hello &amp; World!';
        $expectedOutput = 'Hello & World!';
        $actualOutput = WC_Facebookcommerce_Utils::clean_string($string, true);
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    public function testEmptyString() {
        $string = '';
        $expectedOutput = '';
        $actualOutput = WC_Facebookcommerce_Utils::clean_string($string, true);
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    public function testNullString() {
        $string = null;
        $expectedOutput = null;
        $actualOutput = WC_Facebookcommerce_Utils::clean_string($string, true);
        $this->assertEquals($expectedOutput, $actualOutput);
    }
}