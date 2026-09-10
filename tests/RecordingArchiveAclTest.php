<?php
namespace MikoPBX\Common\Providers {
    class PBXConfModulesProvider {
        public static $context;
        public static function hookModulesMethod($method,$args) {
            self::$context=$args[1];
            $args[0]['conditions']='src_num = :archiveAclEmployee:';
            $args[0]['bind']=['archiveAclEmployee'=>'204'];
        }
    }
}
namespace MikoPBX\Modules\Config {interface CDRConfigInterface {const APPLY_ACL_FILTERS_TO_CDR_QUERY='applyACLFiltersToCDRQuery';}}
namespace MikoPBX\PBXCoreREST\Controllers\Modules {class ModulesControllerBase {public $request;}}
namespace Modules\ModuleExtendedCDRs\bin {class ConnectorDB {
    public static $parameters;
    public static function invoke($method,$args){self::$parameters=$args[0];return [];}
}}
namespace {
    require dirname(__DIR__).'/Lib/RestAPI/Controllers/ApiController.php';
    require dirname(__DIR__).'/Lib/GetReport.php';
    $controller=new \Modules\ModuleExtendedCDRs\Lib\RestAPI\Controllers\ApiController();
    $controller->request=new class {public function getJwtPayload(){return ['userId'=>'alice','role'=>'ModuleUsersUI_1','exp'=>123];}};
    $method=new ReflectionMethod($controller,'archiveAcl');$method->setAccessible(true);
    $acl=$method->invoke($controller);
    $context=\MikoPBX\Common\Providers\PBXConfModulesProvider::$context;
    if($context['user_name']!=='alice'||$context['session_id']!=='alice')throw new RuntimeException('Core ACL identity context lost');
    $report=new \Modules\ModuleExtendedCDRs\Lib\GetReport($acl);
    $select=new ReflectionMethod($report,'selectCDRRecordsWithFilters');$select->setAccessible(true);
    $select->invoke($report,['conditions'=>'linkedid = :ids:','bind'=>['ids'=>'call1']]);
    $params=\Modules\ModuleExtendedCDRs\bin\ConnectorDB::$parameters;
    if(strpos($params['conditions'],'src_num = :archiveAclEmployee:')===false||$params['bind']['archiveAclEmployee']!=='204'||$params['bind']['ids']!=='call1')throw new RuntimeException('CLI archive escaped captured ACL');
    echo "RecordingArchiveAclTest: OK\n";
}
