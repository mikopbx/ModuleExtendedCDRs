<?php
namespace MikoPBX\PBXCoreREST\Controllers\Modules {
    class ModulesControllerBase {public $request;public $response;public $error;public function sendError($code){$this->error=$code;}}
}
namespace Modules\ModuleExtendedCDRs\Lib {
    class MikoPBXVersion {
        public static $root;
        public static function getDefaultDi(){return new class {
            public function getShared($name){return new class {public function path($key){return MikoPBXVersion::$root;}};}
        };}
    }
}
namespace {
    foreach(['RecordingArchiveJobs','RecordingArchiveService','DownloadHeaderPolicy']as$class)require dirname(__DIR__).'/Lib/'.$class.'.php';
    require dirname(__DIR__).'/Lib/RestAPI/Controllers/ApiController.php';
    $root=sys_get_temp_dir().'/archive-transfer-'.bin2hex(random_bytes(6));
    \Modules\ModuleExtendedCDRs\Lib\MikoPBXVersion::$root=$root;
    $jobs=\Modules\ModuleExtendedCDRs\Lib\RecordingArchiveService::jobs();
    $job=$jobs->request('owner',[],'revision',static function(){});
    $jobs->run($job['id'],static function($input,$progress,$target){file_put_contents($target,'native transfer');return [];});
    $controller=new \Modules\ModuleExtendedCDRs\Lib\RestAPI\Controllers\ApiController();
    $controller->request=new class {public $post=[];public function getPost($key){return $this->post[$key]??null;}};
    $controller->response=new class {
        public $headers=[];public $length;
        public function setHeader($key,$value){$this->headers[$key]=$value;}
        public function setContentLength($value){$this->length=$value;}
        public function sendHeaders(){}
    };
    $controller->request->post=['id'=>$job['id'],'ticket'=>'forged'];
    ob_start();$controller->archiveFile();$body=ob_get_clean();
    if($controller->error!==403||$body!=='')throw new RuntimeException('Unauthenticated forged transfer allowed');
    $controller->error=null;$controller->request->post['ticket']=$jobs->ticket($job['id'],'owner');
    ob_start();$controller->archiveFile();$body=ob_get_clean();
    if($body!=='native transfer'||$controller->response->length!==15||$controller->error!==null)throw new RuntimeException('Native transfer failed');
    if($controller->response->headers['Content-Type']!=='application/x-tar'||strpos($controller->response->headers['Content-Disposition'],'attachment;')!==0)throw new RuntimeException('Not a browser download response');
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f){$f->isDir()?rmdir($f):unlink($f);}rmdir($root);
    echo "RecordingArchiveTransferTest: OK (controller ticket gate, body, browser headers)\n";
}
