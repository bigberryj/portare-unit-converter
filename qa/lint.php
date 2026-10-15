<?php
/** Run native PHP syntax checks for every project PHP file. */
$root = dirname( __DIR__ );
$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$count = 0; $failed = 0;
foreach ( $files as $file ) {
    if ( ! $file->isFile() || 'php' !== $file->getExtension() || false !== strpos( $file->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ) ) { continue; }
    $status = 0;
    passthru( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file->getPathname() ), $status );
    ++$count; if ( 0 !== $status ) { ++$failed; }
}
echo "PHP lint: {$count} files, {$failed} failures\n";
exit( $failed ? 1 : 0 );
