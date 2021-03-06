<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-22
 * Time: 11:55
 */

namespace Inhere\Console\BuiltIn;

use Inhere\Console\Components\PharCompiler;
use Inhere\Console\Controller;
use Inhere\Console\Utils\Helper;
use Inhere\Console\Utils\Show;

/**
 * Class PharController
 * @package Inhere\Console\BuiltIn
 */
class PharController extends Controller
{
    protected static $name = 'phar';
    protected static $description = 'Pack a project directory to phar or unpack phar to directory';

    /**
     * pack project to a phar package
     * @usage {fullCommand} [--dir DIR] [--output FILE]
     * @options
     *  --dir STRING        Setting the directory for packing.
     *                      - default is current work-dir.(<comment>{workDir}</comment>)
     *  --output STRING     Setting the output file name(<comment>app.phar</comment>)
     *  --refresh BOOL      Whether build vendor folder files on phar file exists(<comment>False</comment>)
     * @param  \Inhere\Console\IO\Input $in
     * @param  \Inhere\Console\IO\Output $out
     * @return int
     */
    public function packCommand($in, $out): int
    {
        $time = microtime(1);
        $workDir = $in->getPwd();
        $dir = $in->getOpt('dir') ?: $workDir;
        $pharFile = $workDir . '/' . $in->getOpt('output', 'app.phar');

        $cpr = $this->configCompiler($dir);

        $counter = null;
        $refresh = $in->boolOpt('refresh');

        $out->liteInfo(
            "Now, will begin building phar package.\n from path: <comment>$workDir</comment>\n" .
            " phar file: <info>$pharFile</info>"
        );

        $out->info('Pack file to Phar: ');

        $cpr->onError(function ($error) {
            $this->output->warning($error);
        });

        if ($in->getOpt('debug')) {
            $cpr->onAdd(function ($path) {
                $this->output->write(" <comment>+</comment> $path");
            });
        } else {
            $counter = Show::counterTxt('Handling ...', 'Done.');

            $cpr->onAdd(function () use($counter) {
                $counter->send(1);
            });
        }

        // packing ...
        $cpr->pack($pharFile, $refresh);

        if ($counter) {
            $counter->send(-1);
        }

        $out->write([
            PHP_EOL . '<success>Phar build completed!</success>',
            " - Phar file: $pharFile",
            ' - Phar size: ' . round(filesize($pharFile) / 1024 / 1024, 2) . ' Mb',
            ' - Pack Time: ' . round(microtime(1) - $time, 3) . ' s',
            ' - Pack File: ' . $cpr->getCounter(),
            ' - Commit ID: ' . $cpr->getVersion(),
        ]);

        return 0;
    }

    /**
     * @param string $dir
     * @return PharCompiler
     */
    protected function configCompiler(string $dir): PharCompiler
    {
        $cpr = new PharCompiler($dir);

        // config
        $cpr
            // ->stripComments(false)
            ->setShebang(true)
            ->addExclude([
                'demo',
                'tests',
                'tmp',
            ])
            ->addFile([
                'LICENSE',
                'composer.json',
                'README.md',
                'tests/boot.php',
            ])
            ->setCliIndex('examples/app')
            // ->setWebIndex('web/index.php')
            // ->setVersionFile('config/config.php')
            ->in($dir)
        ;

        // Command Controller 命令类不去除注释，注释上是命令帮助信息
        $cpr->setStripFilter(function ($file) {
            /** @var \SplFileInfo $file */
            $name = $file->getFilename();

            return false === strpos($name, 'Command.php') && false === strpos($name, 'Controller.php');
        });

        return $cpr;
    }

    /**
     * unpack a phar package to a directory
     * @usage {fullCommand} -f FILE [-d DIR]
     * @options
     *  -f, --file STRING   The packed phar file path
     *  -d, --dir STRING    The output dir on extract phar package.
     *  -y, --yes BOOL      Whether display goon tips message.
     *  --overwrite BOOL    Whether overwrite exists files on extract phar
     * @example {fullCommand} -f myapp.phar -d var/www/app
     * @param  \Inhere\Console\IO\Input $in
     * @param  \Inhere\Console\IO\Output $out
     * @return int
     */
    public function unpackCommand($in, $out): int
    {
        if (!$path = $in->getSameOpt(['f', 'file'])) {
            return $out->error("Please input the phar file path by option '-f|--file'");
        }

        $basePath = $in->getPwd();
        $file = realpath($basePath . '/' . $path);

        if (!file_exists($file)) {
            return $out->error("The phar file not exists. File: $file");
        }

        $dir = $in->getSameOpt(['d', 'dir']) ?: $basePath;
        $overwrite = $in->getBoolOpt('overwrite');

        if (!is_dir($dir)) {
            Helper::mkdir($dir);
        }

        $out->write("Now, begin extract phar file:\n $file \nto dir:\n $dir");

        PharCompiler::unpack($file, $dir, null, $overwrite);

        $out->success("OK, phar package have been extract to the dir: $dir");

        return 0;
    }
}