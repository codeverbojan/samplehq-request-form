<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Integration;

class SubmissionFlowTest extends TestCase {

	public function test_create_submission_with_meta(): void {
		$form_id = $this->create_form();
		$sub_id  = $this->create_submission( $form_id, [
			'email'      => 'john@example.com',
			'first_name' => 'John',
			'last_name'  => 'Doe',
		] );

		$this->assertGreaterThan( 0, $sub_id );

		$sub = $this->submissions->get( $sub_id );
		$this->assertNotNull( $sub );
		$this->assertSame( 'john@example.com', $sub['email'] );
		$this->assertSame( 'John', $sub['first_name'] );
		$this->assertSame( 'new', $sub['status'] );

		// Add meta.
		$this->submission_meta->add( $sub_id, 'phone', '+1234567890' );
		$this->submission_meta->add( $sub_id, 'company', 'Acme Inc' );

		$meta = $this->submission_meta->get_all( $sub_id );
		$this->assertArrayHasKey( 'phone', $meta );
		$this->assertSame( '+1234567890', $meta['phone'] );
		$this->assertSame( 'Acme Inc', $meta['company'] );
	}

	public function test_submissions_count_increments(): void {
		$form_id = $this->create_form();

		$form_before = $this->forms->get( $form_id );
		$this->assertSame( 0, (int) $form_before['submissions_count'] );

		$this->forms->increment_submissions_count( $form_id );
		$this->forms->increment_submissions_count( $form_id );

		$form_after = $this->forms->get( $form_id );
		$this->assertSame( 2, (int) $form_after['submissions_count'] );
	}

	public function test_submission_status_transitions(): void {
		$form_id = $this->create_form();
		$sub_id  = $this->create_submission( $form_id );

		// New -> spam.
		$this->submissions->update_status( $sub_id, 'spam' );
		$sub = $this->submissions->get( $sub_id );
		$this->assertSame( 'spam', $sub['status'] );

		// Spam -> new (restore).
		$this->submissions->update_status( $sub_id, 'new' );
		$sub = $this->submissions->get( $sub_id );
		$this->assertSame( 'new', $sub['status'] );

		// New -> trash.
		$this->submissions->update_status( $sub_id, 'trash' );
		$sub = $this->submissions->get( $sub_id );
		$this->assertSame( 'trash', $sub['status'] );
	}

	public function test_star_and_read_flags(): void {
		$form_id = $this->create_form();
		$sub_id  = $this->create_submission( $form_id );

		// Initially unread, unstarred.
		$sub = $this->submissions->get( $sub_id );
		$this->assertSame( 0, (int) $sub['is_read'] );
		$this->assertSame( 0, (int) $sub['is_starred'] );

		// Star it.
		$this->submissions->set_starred( $sub_id, true );
		$sub = $this->submissions->get( $sub_id );
		$this->assertSame( 1, (int) $sub['is_starred'] );

		// Mark read.
		$this->submissions->set_read( $sub_id, true );
		$sub = $this->submissions->get( $sub_id );
		$this->assertSame( 1, (int) $sub['is_read'] );
	}

	public function test_delete_submission_cascades_meta(): void {
		$form_id = $this->create_form();
		$sub_id  = $this->create_submission( $form_id );
		$this->submission_meta->add( $sub_id, 'key1', 'value1' );
		$this->submission_meta->add( $sub_id, 'key2', 'value2' );

		$this->submission_meta->delete_all( $sub_id );
		$meta = $this->submission_meta->get_all( $sub_id );
		$this->assertEmpty( $meta );
	}

	public function test_rate_limiter(): void {
		$rate_limits = new \SampleHQForm\Database\RateLimitsTable( $GLOBALS['wpdb'] );

		$form_id = $this->create_form();
		$ip      = '192.168.1.100';

		// First 10 should be allowed (DEFAULT_LIMIT = 10).
		for ( $i = 0; $i < 10; $i++ ) {
			$this->assertTrue( $rate_limits->check_and_increment( $ip, $form_id ) );
		}

		// 11th should be denied.
		$this->assertFalse( $rate_limits->check_and_increment( $ip, $form_id ) );
	}
}
