<?php

class WP_Test_U2F extends WP_UnitTestCase {
	public function test_init() {
		$this->assertInstanceOf( 'U2F', U2F::init() );
	}
}
