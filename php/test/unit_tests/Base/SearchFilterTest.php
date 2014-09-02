<?php

class SearchFilterIntTest extends PHPUnit_Framework_TestCase {
    public function testParseNonNumeric() {
        $sfi = new Base\SearchFilterInt("filter", 1, -1, 10);
        
        $ret = $sfi->parseValue("x");
        $this->assertThat(
            $ret,
            $this->equalTo(1)
        );
    }
}
