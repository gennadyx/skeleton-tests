<?php

/*
 * This file is part of the skeleton package.
 *
 * (c) Gennady Knyazkin <dev@gennadyx.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gennadyx\Skeleton\Tests\Helper;

use Gennadyx\Skeleton\CommandHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

trait FunctionalTestTrait
{
    use EventMockAwareTrait;

    private static $root;

    private static $ideaPathFixture = 'vendor/gennadyx/skeleton-tests/fixtures/.idea/project.iml';

    private $testDirs;

    /**
     * @var Filesystem
     */
    protected $fs;
    
    public static function root()
    {
        if (null === static::$root) {
            static::$root = realpath('.');
        }
        
        return static::$root;
    }

    public static function assertEqualsDirectory(string $expected, string $actual)
    {
        $finder = new Finder();
        $finder->in($expected)
            ->ignoreDotFiles(false)
            ->ignoreUnreadableDirs(false)
            ->ignoreVCS(false);

        foreach ($finder as $item) {
            $target = str_replace($expected, $actual, $item->getRealPath());

            if ($item->isDir()) {
                static::assertDirectoryExists($target);
            } elseif ($item->isFile()) {
                static::assertFileExists($target);
                static::assertFileEquals($item->getRealPath(), $target);
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        $this->createEventMock();
        $this->fs = new Filesystem();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown()
    {
        $this->event = null;
        $this->io = null;
        $this->fs = null;
    }

    protected function createTestDir(string $name, string $chdir, bool $createIdeaPath): string
    {
        $root = sprintf('%s/%s', static::root(), $name);
        $testDir = $root.$chdir;
        $this->fs->mkdir($testDir);

        $finder = new Finder();
        $finder
            ->files()
            ->in(static::root())
            ->exclude(['.idea', $name])
            ->ignoreDotFiles(false);

        foreach ($finder as $item) {
            if (!$this->isTestDir($root, $item->getRealPath())) {
                $this->copyToTestDir($item, $testDir);
            }
        }

        if ($createIdeaPath) {
            $this->createProjectIdeaPath($root);
        }

        return $root;
    }

    protected function isTestDir(string $root, string $path): bool
    {
        return strpos($path, $root) === 0;
    }

    protected function copyToTestDir(SplFileInfo $fileInfo, string $testDir)
    {
        $target = $testDir;

        if ('' !== $fileInfo->getRelativePath()) {
            $target .= '/'.$fileInfo->getRelativePath();
        }

        $this->fs->copy(
            $fileInfo->getRealPath(),
            sprintf('%s/%s', $target, $fileInfo->getBasename())
        );
    }

    protected function createProjectIdeaPath(string $root)
    {
        $this->fs->copy(
            realpath(static::$ideaPathFixture),
            sprintf('%s/.idea/%s.iml', $root, basename($root))
        );
    }

    protected function setEnvironmentVars(string $name)
    {
        $vars = [
            'vendor' => 'test_vendor',
            'name' => 'test_'.$name,
            'author_name' => 'Test Name',
            'author_email' => 'test@test.com',
            'author_website' => ''
        ];

        foreach ($vars as $k => $v) {
            putenv(sprintf('COMPOSER_DEFAULT_%s=%s', strtoupper($k), $v));
        }
    }

    protected function executeTest(string $name, string $chdir = '', $createIdeaPath = false)
    {
        $this->setEnvironmentVars($name);

        $name .= '_test';
        $rootDir = $this->createTestDir($name, $chdir, $createIdeaPath);

        chdir($rootDir.$chdir);
        CommandHandler::handle($this->event);

        $output = $this->io->getOutput();
        static::assertEquals("Build successful!\n", $output, $output);

        //if (is_dir('tests/fixtures/expected/'.$name)) {
        //    static::assertEqualsDirectory('tests/fixtures/expected/'.$name, $rootDir);
        //}
        //
        //$this->fs->remove($rootDir);
    }


}
