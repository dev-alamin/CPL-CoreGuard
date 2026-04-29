<?php
namespace Amin\CPL_CoreGuard\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use Amin\CPL_CoreGuard\Core\File_System;

class FileSystemTest extends TestCase {
	private $root;
	private $fs;

	protected function setUp(): void {
        // Create a virtual file system with 'wordpress' as the root
        $this->root = vfsStream::setup('wordpress');
        $this->fs   = new \Amin\CPL_CoreGuard\Core\File_System();
    }

    public function test_put_contents_writes_to_disk() {
        // Ensure the path starts with the vfs:// protocol
        $path    = vfsStream::url('wordpress/test-config.php');
        $content = "<?php echo 'hello';";
        
        // Pass 0 to bypass LOCK_EX which vfsStream doesn't support
        $result = $this->fs->put_contents( $path, $content, 0 );

        $this->assertTrue( $result, 'File_System::put_contents should return true' );
        $this->assertTrue( $this->root->hasChild('test-config.php'), 'The virtual root should contain the file' );
    }
}
