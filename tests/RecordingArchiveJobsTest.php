<?php
require_once dirname(__DIR__).'/Lib/RecordingArchiveJobs.php';
use Modules\ModuleExtendedCDRs\Lib\RecordingArchiveJobs;
function expectJob($ok,$message){if(!$ok)throw new RuntimeException($message);}
$root=sys_get_temp_dir().'/archive-jobs-'.bin2hex(random_bytes(6));
$jobs=new RecordingArchiveJobs($root,60);
$launches=0;$launch=function($id)use(&$launches){$launches++;};
$input=['search'=>'{"dateRangeSelector":"01/01/2026 - 02/01/2026"}','acl'=>['conditions'=>'1=1','bind'=>[]]];
$a=$jobs->request('alice',$input,'revision1',$launch);
$b=$jobs->request('alice',$input,'revision2',$launch);
expectJob($a['id']===$b['id']&&$launches===1,'Concurrent requests must reuse queued job even if CDR changes');
try{$jobs->status($a['id'],'bob');throw new LogicException('Owner check absent');}catch(RuntimeException $e){}
$r=$jobs->run($a['id'],function($input,$progress,$path){$progress(1,2);file_put_contents($path,'TAR');return [];});
expectJob($r===true,'Reserved worker runs');
expectJob(!$jobs->run($a['id'],function(){throw new LogicException('Second build');}),'Duplicate worker must not rebuild');
$ready=$jobs->status($a['id'],'alice');expectJob($ready['state']==='ready','Ready status');
$jobs->request('alice',$input,'revision1',$launch);expectJob($launches===1,'Ready result reused');
$ticket=$jobs->ticket($a['id'],'alice');$fp=$jobs->openDownload($a['id'],$ticket);
expectJob(stream_get_contents($fp)==='TAR','Valid ticket downloads ready file');fclose($fp);
try{$jobs->openDownload($a['id'],$ticket.'x');throw new LogicException('Bad ticket allowed');}catch(RuntimeException $e){}
$c=$jobs->request('alice',$input,'revision2',$launch);expectJob($c['id']!==$a['id']&&$launches===2,'Changed input revision replaces cached result');
$jobs->run($c['id'],function(){throw new RuntimeException('archive_has_no_valid_entries');});
expectJob($jobs->status($c['id'],'alice')['error']==='archive_has_no_valid_entries','Failure visible');
$jobs->request('alice',$input,'revision2',$launch);expectJob($launches===3,'Failed request can retry');

// Changed/deleted source recordings invalidate a ready result even with an unchanged CDR revision.
$source=$root.'/source.wav';file_put_contents($source,'original');
$manifestJob=$jobs->request('manifest',$input,'same',$launch);
$jobs->run($manifestJob['id'],function($input,$progress,$target)use($source){
    file_put_contents($target,'TAR');$s=stat($source);
    return [$source=>[$s['ino'],$s['size'],$s['mtime'],$s['ctime']]];
});
file_put_contents($source,'changed size');
$changed=$jobs->request('manifest',$input,'same',$launch);
expectJob($changed['id']!==$manifestJob['id'],'Modified recording must invalidate ready cache');
// Simulate a worker killed after creating a partial archive, then retry.
$meta=$root.'/'.$changed['id'].'.json';$data=json_decode(file_get_contents($meta),true);
$data['state']='building';file_put_contents($meta,json_encode($data));
expectJob($jobs->status($changed['id'],'manifest')['error']==='archive_worker_stopped','Dead worker is visible immediately');
// Expired capabilities and cross-job capabilities cannot authorize a file.
try{$jobs->openDownload($a['id'],'1.'.str_repeat('0',64));throw new LogicException('Expired ticket allowed');}catch(RuntimeException $e){}
try{$jobs->openDownload($manifestJob['id'],$ticket);throw new LogicException('Cross-job ticket allowed');}catch(RuntimeException $e){}
$meta=$root.'/'.$a['id'].'.json';$data=json_decode(file_get_contents($meta),true);
$data['expires']=time()-1;file_put_contents($meta,json_encode($data));
$jobs->cleanup();expectJob(!file_exists($root.'/'.$a['id'].'.tar'),'Expired archive cleaned');
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f){$f->isDir()?rmdir($f):unlink($f);}rmdir($root);
echo "RecordingArchiveJobsTest: OK\n";
