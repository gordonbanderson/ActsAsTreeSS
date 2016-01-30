<?php

class ActsAsTreeExtensionTest extends SapphireTest {

    public static $fixture_file = "acts_as_tree/tests/acts_as_tree.yml";

    public function setUp() {
        parent::setUp();
        $page = $this->objFromFixture('Page', 'homepage');
        $page->requireDefaultRecords();
    }

	public function testOnBeforeWrite() {
		$this->markTestSkipped('TODO');
	}

	public function testOnAfterWrite() {
		$this->markTestSkipped('TODO');
	}

	public function testPaddedNumber() {
		$this->markTestSkipped('TODO');
	}

	public function testRequireDefaultRecords() {
		$this->markTestSkipped('TODO');
	}

}
