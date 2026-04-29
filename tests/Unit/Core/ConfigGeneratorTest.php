<?php
namespace Amin\CPL_CoreGuard\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Amin\CPL_CoreGuard\Core\Config_Generator;

class ConfigGeneratorTest extends TestCase {

	/**
	 * Test that the generator creates the correct PHP string
	 * with the defined() guards we just added.
	 */
	public function test_get_config_file_contents_has_guards() {
		// Since we aren't loading WP, we might need to mock get_option
		// or define it if your bootstrap doesn't.
		// For this example, let's assume the constants/functions are mocked.

		$generator = new Config_Generator();
		$output    = $generator->get_config_file_contents();

		$this->assertStringContainsString( "defined( 'CPL_SITE_NAME' )", $output );
		$this->assertStringContainsString( "define( 'CPL_SITE_NAME'", $output );
		$this->assertStringContainsString( '<?php', $output );
	}

	public function test_wp_config_modification_logic() {
		$generator = new Config_Generator();
		$original  = "<?php\ndefine('DB_NAME', 'wp');";

		$modified = $generator->get_wp_config_modified_content( $original );

		$this->assertStringContainsString( '/* CPL CoreGuard v1 */', $modified );
		$this->assertStringContainsString( "require_once __DIR__ . '/wp-content/mu-plugins/", $modified );
	}
}
