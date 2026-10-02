<?php
namespace MikoPBX\Core\System {class Directories {const AST_MONITOR_DIR='monitor';public static $root;public static function getDir($name){return self::$root;}}}
namespace Modules\ModuleExtendedCDRs\Lib {
    class GetReport {
        public function __construct($acl){if($acl['bind']['employee']!=='204')throw new \RuntimeException('ACL missing');}
        public function history($search,$offset,$limit,$stats){
            if($limit!==5001||$stats!==false)throw new \RuntimeException('Unbounded/expensive archive selection');
            $root=\MikoPBX\Core\System\Directories::$root;
            return (object)['data'=>[['4'=>[
                ['recordingfile'=>$root.'/204.wav','prettyFilename'=>'204'],
                ['recordingfile'=>$root.'/204.wav','prettyFilename'=>'204 duplicate'],
                ['recordingfile'=>dirname($root).'/outside.wav','prettyFilename'=>'outside'],
            ]]]];
        }
    }
}
namespace {
    foreach(['RecordingPathResult','RecordingPathPolicy','RecordingArchiveResult','RecordingArchiveBuilder','RecordingArchiveJobs','RecordingArchiveService'] as $class)require dirname(__DIR__).'/Lib/'.$class.'.php';
    $root=sys_get_temp_dir().'/archive-service-'.bin2hex(random_bytes(6));mkdir($root.'/monitor',0700,true);
    \MikoPBX\Core\System\Directories::$root=$root.'/monitor';
    file_put_contents($root.'/monitor/204.wav','recording 204');file_put_contents($root.'/outside.wav','not allowed');
    $jobs=new \Modules\ModuleExtendedCDRs\Lib\RecordingArchiveJobs($root.'/jobs');
    $job=$jobs->request('alice',['search'=>'{}','acl'=>['bind'=>['employee'=>'204']]],'revision',static function(){});
    $jobs->run($job['id'],[\Modules\ModuleExtendedCDRs\Lib\RecordingArchiveService::class,'build']);
    $state=$jobs->status($job['id'],'alice');
    if($state['state']!=='ready'||$state['completed']!==1||$state['total']!==1)throw new RuntimeException('Real archive/progress failed: '.json_encode($state));
    $ticket=$jobs->ticket($job['id'],'alice');$fp=$jobs->openDownload($job['id'],$ticket);
    file_put_contents($root.'/received.tar',stream_get_contents($fp));fclose($fp);
    $tar=new PharData($root.'/received.tar');
    if(count($tar)!==1||$tar['204.wav']->getContent()!=='recording 204')throw new RuntimeException('Wrong archive content/deduplication');
    unset($tar);
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f){$f->isDir()?rmdir($f):unlink($f);}rmdir($root);
    echo "RecordingArchiveServiceTest: OK (real TAR, dedup, path policy, progress, ticket transfer)\n";
}
