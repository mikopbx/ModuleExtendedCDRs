<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

/*
 * https://docs.phalcon.io/4.0/en/db-models
 *
 */

namespace Modules\ModuleExtendedCDRs\Models;

use MikoPBX\Common\Models\CallDetailRecordsBase;
use Modules\ModuleExtendedCDRs\Lib\Providers\CdrDbProvider;
use Phalcon\Mvc\Model\ResultsetInterface;
use Phalcon\Mvc\Model\Resultset\Simple;

/**
 * Class CallDetailRecords
 *
 * @package MikoPBX\Common\Models
 *
 * @Indexes(
 *     [name='start', columns=['start'], type=''],
 *     [name='srcIndex', columns=['srcIndex'], type=''],
 *     [name='dstIndex', columns=['dstIndex'], type=''],
 *     [name='UNIQUEID', columns=['UNIQUEID'], type=''],
 *     [name='typeCall', columns=['typeCall'], type=''],
 *     [name='typeCall_start', columns=['typeCall', 'start'], type=''],
 *     [name='line', columns=['line'], type=''],
 *     [name='linkedid', columns=['linkedid'], type='']
 * )
 */
class CallHistory extends CallDetailRecordsBase
{

    public const CALL_TYPE_INNER    = '0';
    public const CALL_TYPE_OUTGOING = '1';
    public const CALL_TYPE_INCOMING = '2';
    public const CALL_TYPE_MISSED   = '3';

    public const CALL_STATE_OK            = '1';
    public const CALL_STATE_TRANSFER      = '2';
    public const CALL_STATE_MISSED        = '3';
    public const CALL_STATE_OUTGOING_FAIL = '4';
    public const CALL_STATE_RECALL_CLIENT = '5';
    public const CALL_STATE_RECALL_USER   = '6';
    public const CALL_STATE_APPLICATION   = '7';

    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Time when the call starts.
     * @Column(type="string", nullable=true)
     */
    public ?string $start = '';

    /**
     * Time when the call ends.
     * @Column(type="string", nullable=true)
     */
    public ?string $endtime = '';

    /**
     * Answer status of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $answer = '';

    /**
     * Source channel of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $src_chan = '';

    /**
     * Source number of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $src_num = '';

    /**
     * Destination channel of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $dst_chan = '';

    /**
     * Destination number of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $dst_num = '';

    /**
     * Unique ID of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $UNIQUEID = '';

    /**
     * Linked ID of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $linkedid = '';

    /**
     * DID (Direct Inward Dialing) of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $did = '';

    /**
     * Disposition of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $disposition = '';

    /**
     * Recording file of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $recordingfile = '';

    /**
     *  Source account of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $from_account = '';

    /**
     * Destination account of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $to_account = '';

    /**
     * Dial status of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $dialstatus = '';

    /**
     * Application name of the call.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $appname = '';

    /**
     *  Transfer status of the call.
     *
     * @Column(type="integer", nullable=true)
     */
    public ?string $transfer = '';

    /**
     * Indicator if the call is associated with an application.
     *
     * @Column(type="string", length=1, nullable=true)
     */
    public ?string $is_app = '';

    /**
     * Duration of the call in seconds.
     *
     * @Column(type="integer", nullable=true)
     */
    public ?string $duration = '';

    /**
     * Duration of the call in billing seconds.
     *
     * @Column(type="integer", nullable=true)
     */
    public ?string $billsec = '';

    /**
     * Indicator if the work is completed.
     *
     * @Column(type="string", length=1, nullable=true)
     */
    public ?string $work_completed = '';

    /**
     * Source call ID.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $src_call_id = '';

    /**
     * Destination call ID.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $dst_call_id = '';

    /**
     *  Verbose call ID.
     *
     * @Column(type="string", nullable=true)
     */
    public ?string $verbose_call_id = '';

    /**
     *  Indicator if the call is a transferred call.
     *
     * @Column(type="integer", nullable=true)
     */
    public ?string $a_transfer = '0';


    /**
     * Additional
     */

    /**
     * @Column(type="string", nullable=true)
     */
    public ?string $srcIndex = '0';

    /**
     * @Column(type="string", nullable=true)
     */
    public ?string $dstIndex = '0';
    /**
     * @Column(type="integer", nullable=true)
     */
    public ?string $typeCall = '0';
    /**
     * @Column(type="integer", nullable=true)
     */
    public ?string $answered = '0';
    /**
     * @Column(type="integer", nullable=true)
     */
    public ?string $waitTime = '0';

    /**
     * @Column(type="integer", nullable=true)
     */
    public ?string $stateCall = '0';

    /**
     * @Column(type="string", nullable=true)
     */
    public ?string $line = '';

    /**
     * Переопределяем find() для обхода проблемы с makeBeforeEnableTest().
     *
     * MikoPBX Core при enable/disable модуля вызывает find() на всех моделях
     * для проверки broken references. Для CallHistory (миллионы записей) это
     * вызывает deadlock с ConnectorDB. Поскольку у модели нет relations,
     * проверка всё равно пропускается — возвращаем пустой результат.
     *
     * @param mixed $parameters Query parameters
     * @return ResultsetInterface
     */
    public static function find($parameters = null): ResultsetInterface
    {
        // Только для вызовов без параметров (как в makeBeforeEnableTest)
        if ($parameters === null && self::isEnableCheckContext()) {
            // Возвращаем пустой Resultset — проверка relations пропустится
            return new Simple(
                ['id'],
                new self(),
                null  // null resource = пустой результат
            );
        }

        return parent::find($parameters);
    }

    /**
     * Проверяет, вызван ли find() из контекста проверки enable/disable модуля.
     *
     * @return bool
     */
    private static function isEnableCheckContext(): bool
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';
            $function = $frame['function'] ?? '';

            // Проверка enable/disable модуля
            if ($class === 'MikoPBX\\Modules\\PbxExtensionState'
                && in_array($function, ['makeBeforeEnableTest', 'makeBeforeDisableTest'], true)) {
                return true;
            }
        }
        return false;
    }

    public function initialize(): void
    {
        $this->setSource('cdr_general');
        parent::initialize();
        $this->useDynamicUpdate(true);
        if (!$this->di->has(CdrDbProvider::SERVICE_NAME)) {
            $this->di->register(new CdrDbProvider());
        }
        $this->setConnectionService(CdrDbProvider::SERVICE_NAME);
    }

}