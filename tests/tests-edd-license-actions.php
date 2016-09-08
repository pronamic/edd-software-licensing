<?php
/**
 * Tests to verify the activate, deactivate, get_version and check_license methods,
 * and their helpers
 */

class Tests_EDD_License_Actions extends WP_UnitTestCase {
	protected $object;

	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test_is_local_url() {
		// Test normal TLDs
		$this->assertFalse( edd_software_licensing()->is_local_url( 'domain.com' ) );
		$this->assertFalse( edd_software_licensing()->is_local_url( 'devworld.com' ) );
		$this->assertFalse( edd_software_licensing()->is_local_url( 'shoplocal.com' ) );

		// Test local TLDs
		$this->assertTrue( edd_software_licensing()->is_local_url( 'domain.dev' ) );
		$this->assertTrue( edd_software_licensing()->is_local_url( 'domain.local' ) );

		// Test IPs
		$this->assertTrue( edd_software_licensing()->is_local_url( '192.168.0.1' ) );
		$this->assertTrue( edd_software_licensing()->is_local_url( '127.0.0.1' ) );

		// Test Local subdomain
		$this->assertTrue( edd_software_licensing()->is_local_url( 'dev.domain.com' ) );
		$this->assertTrue( edd_software_licensing()->is_local_url( 'staging.domain.com' ) );
		$this->assertFalse( edd_software_licensing()->is_local_url( 'staging.com' ) );
	}
}
