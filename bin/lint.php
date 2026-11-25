<?php

declare(strict_types=1);

$rootPath=dirname(__DIR__);
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath,FilesystemIterator::SKIP_DOTS));
$failed=false;
foreach($iterator as $file){if($file->getExtension()!=='php'||str_contains($file->getPathname(),DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)){continue;}$command=escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname());exec($command,$output,$status);if($status!==0){$failed=true;echo implode("\n",$output)."\n";}$output=[];}
exit($failed?1:0);
