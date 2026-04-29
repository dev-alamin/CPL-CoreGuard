<?php
namespace Amin\CPL_CoreGuard\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use Amin\CPL_CoreGuard\Core\File_System;

class FileSystemTest extends TestCase {
	private $root;
	private $fs;

	protected function setUp(): void {
		// Create a virtual file system
		$this->root = vfsStream::setup( 'WordPress' );
		$this->fs   = new File_System();
	}

	public function test_put_contents_writes_to_disk() {
		$path    = vfsStream::url( 'wordpress/test-config.php' );
		$content = "<?php echo 'hello';";

		$this->fs->put_contents( $path, $content );

		$this->assertTrue( $this->root->hasChild( 'test-config.php' ) );
		$this->assertEquals( $content, file_get_contents( $path ) );
	}
}
