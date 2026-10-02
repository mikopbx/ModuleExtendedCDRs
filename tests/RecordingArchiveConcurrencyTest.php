<?php
require dirname(__DIR__).'/Lib/RecordingArchiveJobs.php';
use Modules\ModuleExtendedCDRs\Lib\RecordingArchiveJobs;
$mode=$argv[1]??'';
$root=$argv[2]??(sys_get_temp_dir().'/archive-concurrency-'.bin2hex(random_bytes(6)));
$jobs=new RecordingArchiveJobs($root);
if($mode==='request'){
    $job=$jobs->request('owner',['search'=>'same'],'revision',static function($id)use($root){file_put_contents($root.'/launches',$id."\n",FILE_APPEND);usleep(100000);});
    echo $job['id'];exit;
}
if($mode==='build'){
    $jobs->run($argv[3],static function($input,$progress,$path)use($root){
        $active=fopen($root.'/active','x');
        if($active===false)throw new RuntimeException('Concurrent disk-heavy builders');
        fclose($active);
        file_put_contents($root.'/builds',"build\n",FILE_APPEND);usleep(100000);file_put_contents($path,'TAR');unlink($root.'/active');return [];
    });exit;
}
function children($mode,$root,$count,$id=''){
    $running=[];
    for($i=0;$i<$count;$i++){
        $command=[PHP_BINARY,'-n',__FILE__,$mode,$root,is_array($id)?$id[$i]:$id];
        $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]);$running[]=[$process,$pipes];
    }
    $outputs=[];
    foreach($running as [$process,$pipes]){
        $outputs[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0)throw new RuntimeException($error);
    }
    return $outputs;
}
$ids=children('request',$root,6);
if(count(array_unique($ids))!==1||count(file($root.'/launches'))!==1)throw new RuntimeException('Concurrent requests spawned duplicate jobs');
children('build',$root,3,$ids[0]);
if(count(file($root.'/builds'))!==1)throw new RuntimeException('Concurrent workers built duplicate archives');
$other=[];
foreach(['two','three']as$owner){$other[]=$jobs->request($owner,['search'=>$owner],'revision',static function(){})['id'];}
children('build',$root,2,$other);
foreach($other as $i=>$id){if($jobs->status($id,['two','three'][$i])['state']!=='ready')throw new RuntimeException('Different jobs built concurrently');}

foreach(glob($root.'/*')as$f)unlink($f);rmdir($root);
echo "RecordingArchiveConcurrencyTest: OK (6 clients, 3 workers, 1 archive)\n";
