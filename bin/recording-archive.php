<?php
require_once 'Globals.php';
use Modules\ModuleExtendedCDRs\Lib\RecordingArchiveService;
$id=$argv[1]??'';
if($id==='cleanup'){
    if(is_dir(RecordingArchiveService::root())) RecordingArchiveService::jobs()->cleanup();
    exit(0);
}
if(!preg_match('/^[a-f0-9]{48}$/D',$id))exit(1);
cli_set_process_title('ModuleExtendedCDRs-recording-archive-'.$id);
RecordingArchiveService::jobs()->run($id,static function ($input,$progress,$target) {
    // The build timeout starts after the queue's global I/O lock has been acquired.
    pcntl_async_signals(true);
    pcntl_signal(SIGALRM,static function () { throw new RuntimeException('archive_worker_timeout'); });
    pcntl_alarm(1800);
    try { return RecordingArchiveService::build($input,$progress,$target); }
    finally { pcntl_alarm(0); }
});
