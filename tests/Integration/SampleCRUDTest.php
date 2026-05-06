<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Integration;

class SampleCRUDTest extends TestCase {

	public function test_create_sample(): void {
		$id = $this->create_sample( [ 'name' => 'Blue Widget', 'sku' => 'BW-001' ] );
		$this->assertGreaterThan( 0, $id );

		$sample = $this->samples->get( $id );
		$this->assertNotNull( $sample );
		$this->assertSame( 'Blue Widget', $sample['name'] );
		$this->assertSame( 'BW-001', $sample['sku'] );
		$this->assertSame( 'active', $sample['status'] );
	}

	public function test_update_sample(): void {
		$id = $this->create_sample( [ 'name' => 'Original' ] );
		$this->samples->update( $id, [ 'name' => 'Updated' ] );

		$sample = $this->samples->get( $id );
		$this->assertSame( 'Updated', $sample['name'] );
	}

	public function test_archive_sample(): void {
		$id = $this->create_sample();
		$this->samples->update( $id, [ 'status' => 'archived' ] );

		$sample = $this->samples->get( $id );
		$this->assertSame( 'archived', $sample['status'] );
	}

	public function test_delete_sample(): void {
		$id = $this->create_sample();
		$this->samples->delete( $id );

		$sample = $this->samples->get( $id );
		$this->assertNull( $sample );
	}

	public function test_category_assignment_round_trip(): void {
		$sample_id = $this->create_sample();
		$cat_id    = $this->create_category( 'Textiles' );

		$this->category_map->add( $sample_id, $cat_id );

		$cats = $this->category_map->get_categories_for_sample( $sample_id );
		$this->assertCount( 1, $cats );
		$this->assertEquals( $cat_id, $cats[0] );

		// Remove and verify.
		$this->category_map->remove( $sample_id, $cat_id );
		$cats = $this->category_map->get_categories_for_sample( $sample_id );
		$this->assertCount( 0, $cats );
	}

	public function test_custom_fields_round_trip(): void {
		$fields = json_encode( [ [ 'key' => 'weight', 'value' => '250g' ] ] );
		$id     = $this->create_sample( [ 'name' => 'With Fields', 'custom_fields' => $fields ] );

		$sample = $this->samples->get( $id );
		$this->assertNotNull( $sample['custom_fields'] );

		$decoded = json_decode( $sample['custom_fields'], true );
		$this->assertCount( 1, $decoded );
		$this->assertSame( 'weight', $decoded[0]['key'] );
		$this->assertSame( '250g', $decoded[0]['value'] );
	}

	public function test_list_filters_by_status(): void {
		$this->create_sample( [ 'name' => 'Active One' ] );
		$this->create_sample( [ 'name' => 'Archived One', 'status' => 'archived' ] );

		$active = $this->samples->list_all( [ 'status' => 'active' ] );
		$this->assertCount( 1, $active );
		$this->assertSame( 'Active One', $active[0]['name'] );
	}

	public function test_count_matches_list(): void {
		$this->create_sample();
		$this->create_sample();
		$this->create_sample( [ 'status' => 'archived' ] );

		$count = $this->samples->count( [ 'status' => 'active' ] );
		$this->assertSame( 2, $count );
	}

	public function test_unique_sku_enforced(): void {
		$this->create_sample( [ 'sku' => 'UNIQUE-1' ] );

		$this->expectException( \RuntimeException::class );
		$this->create_sample( [ 'sku' => 'UNIQUE-1' ] );
	}
}
