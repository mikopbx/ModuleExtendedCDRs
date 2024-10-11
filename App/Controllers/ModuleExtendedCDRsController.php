<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 11 2018
 */
namespace Modules\ModuleExtendedCDRs\App\Controllers;
use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\Common\Models\CallQueues;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Models\Sip;
use MikoPBX\Common\Providers\PBXConfModulesProvider;
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
use Modules\ModuleUsersGroups\Models\GroupMembers;
use Modules\ModuleUsersGroups\Models\UsersGroups;
use DateTime;

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
        $this->view->logoImagePath = "{$this->url->get()}assets/img/cache/{$this->moduleUniqueID}/logo.svg";
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

        try {
            $searchSettings = json_decode($settings->searchSettings, true, 512, JSON_THROW_ON_ERROR);
        }catch (\Exception $e){
            $searchSettings = [];
        }
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
                        'name'   => $user['callerid'],
                        'number' => $number
                    ];
                }
            }
        }
        $this->view->dateRangeSelector      = $searchSettings['dateRangeSelector']??'';
        $this->view->additionalFilter       = $additionalFilter;
        $this->view->additionalFilterString = implode(',',array_column($additionalFilter, 'number'));
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

    public function saveSearchSettingsAction(): void
    {
        $searchPhrase = $this->request->getPost('search');
        $this->view->searchPhrase = $searchPhrase['value'];
        $settings = ModuleExtendedCDRs::findFirst();
        if(!$settings){
            $settings = new ModuleExtendedCDRs();
        }
        $settings->searchSettings = $searchPhrase['value']??'';
        $settings->save();
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
        if($this->request->get('export-type') === 'pdf'){
            echo 'test';
            return;
        }
        $this->view->draw = $this->request->get('draw');
        $searchPhrase     = $this->request->get('search');
        $gr = new GetReport();
        $view = $gr->history($searchPhrase['value']??'',  $this->request->get('start'), $this->request->get('length'));
        foreach ($view as $key => $value) {
            $this->view->$key = $value;
        }
    }
}