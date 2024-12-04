<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 11 2018
 */
namespace Modules\ModuleExtendedCDRs\App\Controllers;
use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\AdminCabinet\Controllers\SessionController;
use MikoPBX\Common\Models\CallQueues;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Models\Sip;
use MikoPBX\Common\Providers\PBXConfModulesProvider;
use MikoPBX\Common\Providers\SessionProvider;
use MikoPBX\Core\System\Util;
use MikoPBX\Modules\Config\CDRConfigInterface;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleExtendedCDRs\App\Forms\ModuleExtendedCDRsForm;
use Modules\ModuleExtendedCDRs\bin\ConnectorDB;
use Modules\ModuleExtendedCDRs\Lib\CacheManager;
use Modules\ModuleExtendedCDRs\Lib\GetReport;
use Modules\ModuleExtendedCDRs\Lib\HistoryParser;
use Modules\ModuleExtendedCDRs\Models\CallHistory;
use Modules\ModuleExtendedCDRs\Models\ExportRules;
use Modules\ModuleExtendedCDRs\Models\ModuleExtendedCDRs;
use MikoPBX\Common\Models\Providers;
use Modules\ModuleExtendedCDRs\Models\ReportSettings;
use Modules\ModuleUsersGroups\Models\GroupMembers;
use Modules\ModuleUsersGroups\Models\UsersGroups;
use DateTime;
use Modules\ModuleUsersUI\Lib\Constants;
use Modules\ModuleUsersUI\Models\AccessGroups;
use Modules\ModuleUsersUI\Models\UsersCredentials;

class ModuleExtendedCDRsController extends BaseController
{
    private $moduleUniqueID = 'ModuleExtendedCDRs';
    private $moduleDir;

    /**
     * Basic initial class
     */
    public function initialize(): void
    {
        $this->moduleDir = PbxExtensionUtils::getModuleDir($this->moduleUniqueID);
        $this->view->submitMode = null;
        parent::initialize();
    }

    public function getTablesDescriptionAction(): void
    {
        $this->view->data = $this->getTablesDescription();
    }

    public function getNewRecordsAction(): void
    {
        $currentPage                 = $this->request->getPost('draw');
        $table                       = $this->request->get('table');
        $this->view->draw            = $currentPage;
        $this->view->recordsTotal    = 0;
        $this->view->recordsFiltered = 0;
        $this->view->data            = [];

        $descriptions = $this->getTablesDescription();
        if(!isset($descriptions[$table])){
            return;
        }
        $className = $this->getClassName($table);
        if(!empty($className)){
            $filter = [];
            if(isset($descriptions[$table]['cols']['priority'])){
                $filter = ['order' => 'priority'];
            }
            $allRecords = $className::find($filter)->toArray();
            $records    = [];
            $emptyRow   = [
                'rowIcon'  =>  $descriptions[$table]['cols']['rowIcon']['icon']??'',
                'DT_RowId' => 'TEMPLATE'
            ];
            foreach ($descriptions[$table]['cols'] as $key => $metadata) {
                if('rowIcon' !== $key){
                    $emptyRow[$key] = '';
                }
            }
            $records[] = $emptyRow;
            foreach ($allRecords as $rowData){
                $tmpData = [];
                $tmpData['DT_RowId'] =  $rowData['id'];
                foreach ($descriptions[$table]['cols'] as $key => $metadata){
                    if('rowIcon' === $key){
                        $tmpData[$key] = $metadata['icon']??'';
                    }elseif('delButton' === $key){
                        $tmpData[$key] = '';
                    }elseif(isset($rowData[$key])){
                        $tmpData[$key] =  $rowData[$key];
                    }
                }
                $records[] = $tmpData;
            }
            $this->view->data      = $records;
        }
    }

    /**
     * Index page controller
     */
    public function indexAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-export-records-index.js", true);
        $footerCollection->addJs('js/vendor/jquery.tablednd.min.js', true);
        $footerCollection->addJs('js/vendor/semantic/modal.min.js', true);
        $footerCollection->addJs('js/pbx/CallDetailRecords/call-detail-records-player.js', true);
        $footerCollection->addJS('js/vendor/moment/moment.min.js', true);
        $footerCollection->addJS('js/vendor/datepicker/daterangepicker.js', true);
        $footerCollection->addJs('js/vendor/range/range.min.js', true);
        $footerCollection->addJs('js/vendor/semantic/progress.min.js', true);


        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-export-records.css", true);
        $headerCollectionCSS->addCss('css/vendor/semantic/progress.min.css', true);
        $headerCollectionCSS->addCss('css/vendor/semantic/list.css', true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);
        $headerCollectionCSS->addCss('css/vendor/semantic/modal.min.css', true);

        $headerCollectionCSS->addCss('css/vendor/range/range.min.css', true);
        $headerCollectionCSS->addCss('css/vendor/datepicker/daterangepicker.css', true);

        $settings = ModuleExtendedCDRs::findFirst();
        if ($settings === null) {
            $settings = new ModuleExtendedCDRs();
        }

        $providers = Providers::find();
        $providersList = [];
        foreach ($providers as $provider){
            $providersList[ $provider->uniqid ] = $provider->getRepresent();
        }
        $options['providers']=$providersList;

        $this->view->form = new ModuleExtendedCDRsForm($settings, $options);
        $this->view->pick("{$this->moduleDir}/App/Views/index");

        // Список выбора очередей.
        $this->view->queues = CallQueues::find(['columns' => ['id', 'name']]);
        $this->view->groups = [];

        if(class_exists('\Modules\ModuleUsersGroups\Models\UsersGroups')){
            $this->view->groups = UsersGroups::find(['columns' => ['id', 'name']])->toArray();
        }
        $filterNumbers = [];
        PBXConfModulesProvider::hookModulesMethod(CDRConfigInterface::APPLY_ACL_FILTERS_TO_CDR_QUERY, [&$filterNumbers]);
        $filteredExtensions = $filterNumbers['bind']['filteredExtensions']??[];
        if(!empty($filteredExtensions)){
            $filterUsers = [
                "number IN ({filteredExtensions:array}) AND type = 'SIP'",
                'columns' => ['number', 'callerid', 'userid'],
                'bind' => ['filteredExtensions' => $filteredExtensions],
                'order' => 'number'
            ];
            $this->view->users  = Extensions::find($filterUsers)->toArray();
        }else{
            $this->view->users  = Extensions::find(["type = 'SIP'", 'order' => 'number DESC', 'columns' => ['number', 'callerid', 'userid']])->toArray();
        }
        $this->view->rules = ExportRules::find()->toArray();

        $searchSettings = [];
        $this->getVariantsReports($searchSettings);
        $filterNumbers = explode(' ', $searchSettings['additionalFilter']??'');
        $additionalFilter = [];
        foreach ($this->view->groups as $group){
            foreach ($filterNumbers as $number){
                if('group_'.$group['id'] === $number){
                    $additionalFilter[] = [
                        'name'   => $group['name'],
                        'number' => $number
                    ];
                }
            }
        }

        foreach ($this->view->users as $user){
            foreach ($filterNumbers as $number){
                if($user['number'] === $number){
                    $additionalFilter[] = [
                        'name'   => $user['callerid']."($number)",
                        'number' => $number
                    ];
                }
            }
        }
        $this->view->dateRangeSelector      = $searchSettings['dateRangeSelector']??'';
        $this->view->additionalFilter       = $additionalFilter;
        $this->view->additionalFilterString = implode(',',array_column($additionalFilter, 'number'));
        $this->view->accessData  = $this->getUserData();

    }

    private function getVariantsReports(&$searchSettings)
    {
        $mainReports = [
            ReportSettings::REPORT_MAIN => [
                'searchText' => '{}',
                'minBillSec' => 0,
                'isMain' => 1,
                'variantName' => Util::translate('BreadcrumbModuleExtendedCDRs')
            ],
            ReportSettings::REPORT_OUTGOING_EMPLOYEE_CALLS => [
                'searchText' => '{}',
                'minBillSec' => 0,
                'isMain' => 0,
                'variantName' => Util::translate('repModuleExtendedCDRs_'.ReportSettings::REPORT_OUTGOING_EMPLOYEE_CALLS)
            ],
        ];
        $filterVariantReport = [
            'userID=:userID:',
            'bind' => ['userID' => $this->getUserData()['userId']],
            'order'=> 'isMain DESC,id ASC,variantId ASC'
        ];
        $variants = ReportSettings::find($filterVariantReport)->toArray();
        $currentReportNameID = ReportSettings::REPORT_MAIN;
        $currentVariantId    = null;
        $resultVariants      = [
            ReportSettings::REPORT_MAIN => [],
            ReportSettings::REPORT_OUTGOING_EMPLOYEE_CALLS => [],
        ];
        foreach ($variants as $variant){
            $variant['searchText'] = rawurlencode($variant['searchText']);
            if((int)$variant['isMain'] === 1){
                foreach (array_keys($mainReports) as $reportNameID){
                    $mainReports[$reportNameID]['isMain'] = false;
                }
                $currentReportNameID = $variant['reportNameID'];
                $currentVariantId    = $variant['variantId'];
            }
            if(empty($variant['variantId'])){
                $mainReports[$variant['reportNameID']]['searchText'] = $variant['searchText'];
                $mainReports[$variant['reportNameID']]['minBillSec'] = $variant['minBillSec'];
                $mainReports[$variant['reportNameID']]['isMain']     = $variant['isMain'];
                continue;
            }
            $resultVariants[$variant['reportNameID']][] = $variant;
            if($currentVariantId !== null){
                continue;
            }
            try {
                $searchSettings      = json_decode($variant['searchText'], true, 512, JSON_THROW_ON_ERROR);
            }catch (\Exception $e){
                unset($e);
            }
        }
        $this->view->mainReports = $mainReports;
        $this->view->variants = $resultVariants;
        $this->view->currentReportNameID    = $currentReportNameID;
        $this->view->currentVariantId       = $currentVariantId;
    }

    private function getUserData():array
    {
        $accessData = (object)[
            'userId' => 0,
            'userNumber' => '',
            'roleId' => '',
            'roleName' => '',
            'fullAccess' => '1',
            'username' => '',
        ];

        if(PbxExtensionUtils::isEnabled('ModuleUsersUI')){
            $session = $this->getDI()->get(SessionProvider::SERVICE_NAME)->get(SessionController::SESSION_ID);
            $roleId  = str_replace(Constants::MODULE_ROLE_PREFIX,'', $session[SessionController::ROLE]??'');

            $accessData->roleId     = $roleId;
            $roleData = AccessGroups::findFirst(['id=:id:', 'bind' => ['id' => $roleId]]);
            if($roleData){
                $accessData->roleName   = $roleData->name;
                $accessData->fullAccess = $roleData->fullAccess;
            }
            $accessData->username = $session[SessionController::USER_NAME]??'';
            $filterCred = [
                'user_login=:user_login:',
                'bind' => [
                    'user_login' => $session[SessionController::USER_NAME]??""
                ]
            ];
            $credUI = UsersCredentials::findFirst($filterCred);
            if($credUI){
                $accessData->userId = $credUI->user_id;
                $filterExten = [
                    'type=:type: AND userid=:userid:',
                    'bind' => [
                        'type'   => Extensions::TYPE_SIP,
                        'userid' => $credUI->user_id,
                    ]
                ];
                $ext = Extensions::findFirst($filterExten);
                if($ext){
                    $accessData->userNumber = $ext->number;
                    $accessData->cid = $ext->callerid;
                }
            }
        }
        return (array)$accessData;
    }

    /**
     * Save settings AJAX action
     */
    public function saveAction() :void
    {
        $data       = $this->request->getPost();
        $ruleSaveResult = [];
        if(isset($data['rules'])){
            foreach ($data['rules'] as $ruleData ){
                $rule = ExportRules::findFirst("id='{$ruleData['id']}'");
                if(!$rule){
                    $rule = new ExportRules();
                }
                $rule->name = $ruleData['name'];
                $rule->users = $ruleData['users'];
                $rule->dstUrl = $ruleData['dstUrl'];
                $rule->headers = $ruleData['headers'];
                if($rule->save()){
                    $ruleSaveResult[$ruleData['id']] = $rule->id;
                }
            }
        }
        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->success = true;
        $this->view->ruleSaveResult = $ruleSaveResult;
    }

    /**
     * Delete phonebook record
     */
    public function deleteAction(): void
    {
        $table     = $this->request->get('table');
        $className = $this->getClassName($table);
        if(empty($className)) {
            $this->view->success = false;
            return;
        }
        $id     = $this->request->get('id');
        $record = $className::findFirstById($id);
        if ($record !== null && ! $record->delete()) {
            $this->flash->error(implode('<br>', $record->getMessages()));
            $this->view->success = false;
            return;
        }
        $this->view->success = true;
    }

    /**
     * Возвращает метаданные таблицы.
     * @return array
     */
    private function getTablesDescription():array
    {
        $description['PhoneBook'] = [
            'cols' => [
                'rowIcon'    => ['header' => '',                        'class' => 'collapsing', 'icon' => 'user'],
                'priority'   => ['header' => '',                        'class' => 'collapsing'],
                'call_id'    => ['header' => 'Представление абонента',  'class' => 'ten wide'],
                'number_rep' => ['header' => 'Номер телефона',          'class' => 'four wide'],
                'queueId'    => ['header' => 'Номер числом',            'class' => 'collapsing', 'select' => 'queues-list'],
                'delButton'  => ['header' => '',                        'class' => 'collapsing']
            ],
            'ajaxUrl' => '/getNewRecords',
            'icon' => 'user',
            'needDelButton' => true
        ];
        return $description;
    }

    /**
     * Обновление данных в таблице.
     */
    public function saveTableDataAction():void
    {
        $data       = $this->request->getPost();
        $tableName  = $data['pbx-table-id']??'';

        $className = $this->getClassName($tableName);
        if(empty($className)){
            return;
        }
        $rowId      = $data['pbx-row-id']??'';
        if(empty($rowId)){
            $this->view->success = false;
            return;
        }
        $this->db->begin();
        /** @var PhoneBook $rowData */
        $rowData = $className::findFirst('id="'.$rowId.'"');
        if(!$rowData){
            $rowData = new $className();
        }
        foreach ($rowData as $key => $value) {
            if($key === 'id'){
                continue;
            }
            if (array_key_exists($key, $data)) {
                $rowData->writeAttribute($key, $data[$key]);
            }
        }
        // save action
        if ($rowData->save() === FALSE) {
            $errors = $rowData->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();
            return;
        }
        $this->view->data = ['pbx-row-id'=>$rowId, 'newId'=>$rowData->id, 'pbx-table-id' => $data['pbx-table-id']];
        $this->view->success = true;
        $this->db->commit();

    }

    /**
     * Получение имени класса по имени таблицы
     * @param $tableName
     * @return string
     */
    private function getClassName($tableName):string
    {
        if(empty($tableName)){
            return '';
        }
        $className = "Modules\ModuleExtendedCDRs\Models\\$tableName";
        if(!class_exists($className)){
            $className = '';
        }
        return $className;
    }

    /**
     * Changes rules priority
     *
     */
    public function changePriorityAction(): void
    {
        $this->view->disable();
        $result = true;

        if ( ! $this->request->isPost()) {
            return;
        }
        $priorityTable = $this->request->getPost();
        $tableName     = $this->request->get('table');
        $className = $this->getClassName($tableName);
        if(empty($className)){
            echo "table not found -- ы$tableName --";
            return;
        }
        $rules = $className::find();
        foreach ($rules as $rule){
            if (array_key_exists ( $rule->id, $priorityTable)){
                $rule->priority = $priorityTable[$rule->id];
                $result         .= $rule->update();
            }
        }
        echo json_encode($result);
    }

    public function saveMainVariantReportAction(): void
    {
        $reportNameID = $this->request->getPost('reportNameID')??ReportSettings::REPORT_MAIN;
        $variantId    = $this->request->getPost('variantId')??'';
        $accessData   = $this->getUserData();
        $this->view->accessData   = $accessData;

        $filter = [
            'userID=:userID:',
            'bind' => [
                'userID' => $accessData['userId'],
            ]
        ];
        $reportsData = ReportSettings::find($filter);

        $mainFound = false;
        foreach ($reportsData as $reportSettings) {
            if($reportNameID === $reportSettings->reportNameID && $reportSettings->variantId === $variantId){
                $reportSettings->isMain = true;
                $mainFound = true;
            }else{
                $reportSettings->isMain = false;
            }
            $reportSettings->save();
        }
        if($mainFound === false){
            $this->view->wasAddNewReport = 1;
            $report = new ReportSettings();
            $report->reportNameID = $reportNameID;
            $report->userID = $accessData['userId'];
            $report->variantId = $variantId;
            $report->isMain = true;
            $report->sendingScheduledReport = 0;
            $report->searchText = '{"dateRangeSelector":"cal_ThisMonth","globalSearch":"","typeCall":"all-calls","additionalFilter":""}';
            $report->save();
        }
    }
    public function removeVariantReportAction(): void{
        $reportNameID = $this->request->getPost('reportNameID')??ReportSettings::REPORT_MAIN;
        $variantId    = $this->request->getPost('variantId')??'';
        $accessData   = $this->getUserData();
        $this->view->accessData   = $accessData;

        $filter = [
            'userID=:userID: AND reportNameID=:reportNameID: AND variantId=:variantId:',
            'bind' => [
                'userID' => $accessData['userId'],
                'reportNameID' => $reportNameID,
                'variantId' => $variantId
            ]
        ];
        $reportsData = ReportSettings::findFirst($filter);
        if($reportsData){
            $reportsData->delete();
        }
    }
    public function saveSearchSettingsAction(): void
    {
        $searchPhrase = $this->request->getPost('search');
        $reportNameID = $this->request->getPost('reportNameID')??ReportSettings::REPORT_MAIN;
        $variantId    = $this->request->getPost('variantId')??'';
        $variantName  = $this->request->getPost('variantName')??'';
        $accessData   = $this->getUserData();
        $this->view->searchPhrase = $searchPhrase['value'];
        $this->view->accessData   = $accessData;

        $filter = [
            'userID=:userID: AND reportNameID=:reportNameID: AND variantId=:variantId:',
            'bind' => [
                'userID' => $accessData['userId'],
                'reportNameID' => $reportNameID,
                'variantId' => $variantId
            ]
        ];
        $reportData = ReportSettings::findFirst($filter);
        if(!$reportData){
            $reportData = new ReportSettings();
            $reportData->userID = $accessData['userId'];
            $reportData->reportNameID = $reportNameID;
            $reportData->variantId = $variantId;
        }
        $reportData->variantName = $variantName;
        $reportData->searchText = $searchPhrase['value']??'';

        $reportData->minBillSec = $this->request->getPost('minBillSec')??'';
        $reportData->sendingScheduledReport = $this->request->getPost('sendingScheduledReport')??'0';
        $reportData->dateMonth = $this->request->getPost('dateMonth')??'';
        $reportData->day = $this->request->getPost('day')??'';
        $reportData->time = $this->request->getPost('time')??'';
        $reportData->email = $this->request->getPost('email')??'';

        $this->view->resSave = $reportData->save();
    }

    /**
     * Returns the synchronization progress of the call history.
     * @return void
     */
    public function getStateAction(): void
    {
        $this->view->stateData = CacheManager::getCacheData(HistoryParser::CDR_SYNC_PROGRESS_KEY);
    }

    /**
     * Requests new package of call history records for DataTable JSON.
     *
     * @return void
     */
    public function getHistoryAction(): void
    {
        $this->view->draw = $this->request->get('draw');
        $searchPhrase     = $this->request->get('search');
        $length           = is_numeric($this->request->get('length'))?(int)$this->request->get('length'):null;

        $gr = new GetReport();
        $view = $gr->history($searchPhrase['value']??'',  $this->request->get('start'), $length);
        foreach ($view as $key => $value) {
            $this->view->$key = $value;
        }
    }

    /**
     * Запрос отвчета по исходящим сотрудников
     * @return void
     */
    public function getOutgoingEmployeeCallsAction(): void
    {
        $this->view->draw = $this->request->get('draw');
        $searchPhrase     = $this->request->get('search');
        $length           = is_numeric($this->request->get('length'))?(int)$this->request->get('length'):null;
        $gr = new GetReport();
        $view = $gr->outgoingEmployeeCalls($searchPhrase['value']??'',  $this->request->get('start'), $length);
        foreach ($view as $key => $value) {
            $this->view->$key = $value;
        }
    }
}