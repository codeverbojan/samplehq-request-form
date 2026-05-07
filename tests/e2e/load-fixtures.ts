import * as fs from 'fs';

export interface FixtureData {
	ready: boolean;
	categories: Record< string, string >;
	sampleIds: string[];
	sampleNames: string[];
	formIds: Record< string, string >;
	pageUrls: Record< string, string >;
}

let cache: FixtureData | null = null;

export function loadFixtures(): FixtureData {
	if ( ! cache ) {
		cache = JSON.parse(
			fs.readFileSync( 'tests/e2e/.fixtures.json', 'utf-8' )
		) as FixtureData;
	}
	return cache;
}
