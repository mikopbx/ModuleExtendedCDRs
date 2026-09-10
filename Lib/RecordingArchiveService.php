<?php

declare(strict_types=1);
namespace Modules\ModuleExtendedCDRs\Lib;

use MikoPBX\Core\System\Directories;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\System\Processes;
use RuntimeException;

/** PBX-specific selection and process integration around the testable job/cache store. */
final class RecordingArchiveService
{
    public static function root(): string
    {
        return MikoPBXVersion::getDefaultDi()->getShared('config')->path('core.tempDir').'/ModuleExtendedCDRsArchiveJobs';
    }
    public static function jobs(): RecordingArchiveJobs { return new RecordingArchiveJobs(self::root()); }

    public static function launch(string $id): void
    {
        $command = escapeshellarg(Util::which('php')).' '.escapeshellarg(dirname(__DIR__).'/bin/recording-archive.php')
            .' '.escapeshellarg($id);
        Processes::mwExecBg($command);
    }

    public static function revision(): string
    {
        $files=glob(dirname(__DIR__).'/db/*.db*')?:[];
        $groups=dirname(__DIR__,2).'/ModuleUsersGroups/db';
        foreach(glob($groups.'/*.db*')?:[] as $path) $files[]=$path;
        $data=[];
        foreach($files as $path){
            clearstatcache(true,$path);$s=@stat($path);
            if($s!==false)$data[$path]=[$s['ino'],$s['size'],$s['mtime'],$s['ctime']];
        }
        return hash('sha256',json_encode($data).filemtime(__FILE__));
    }

    public static function build(array $input, callable $progress, string $target): array
    {
        $report=new GetReport($input['acl']);
        // No report totals are needed for an archive. Limit selection before materializing all history.
        $view=$report->history($input['search'],null,5001,false);
        if(count($view->data)>5000)throw new RuntimeException('archive_too_large');
        $records=[];
        foreach($view->data as $call){
            foreach(($call['4']??[]) as $leg){
                if(!is_array($leg)||empty($leg['recordingfile']))continue;
                if(count($records)>=5000)throw new RuntimeException('archive_too_large');
                $records[]=['path'=>(string)$leg['recordingfile'],'name'=>(string)($leg['prettyFilename']??'recording')];
            }
        }
        $policy=new RecordingPathPolicy(Directories::getDir(Directories::AST_MONITOR_DIR));
        $manifest=[];$unique=[];
        foreach($records as $record){
            $allowed=$policy->validate($record['path']);
            if(!$allowed->isAllowed()||$allowed->path()===null)continue;
            $path=$allowed->path();
            if(isset($manifest[$path]))continue;
            $stat=@stat($path);if($stat===false)continue;
            $manifest[$path]=[$stat['ino'],$stat['size'],$stat['mtime'],$stat['ctime']];
            $unique[]=['path'=>$path,'name'=>$record['name']];
        }
        // Temporary build files are isolated so a killed worker's partial archive can be removed later.
        $dir=$target.'.work';
        $archive=(new RecordingArchiveBuilder($policy,$dir))->build($unique,$progress);
        if(!rename($archive->path(),$target))throw new RuntimeException('archive_build_failed');
        @rmdir($dir);
        return $manifest;
    }
}
