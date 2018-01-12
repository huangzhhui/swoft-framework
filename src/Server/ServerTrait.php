<?php

namespace Swoft\Server;
use Swoft\Bean\BeanFactory;
use Swoft\Core\ApplicationContext;
use Swoft\Core\InitApplicationContext;
use Swoft\Crontab\TableCrontab;
use Swoft\Event\AppEvent;
use Swoft\Event\Events\BeforeTaskEvent;
use Swoft\Helper\ProcessHelper;
use Swoft\Process\Process;
use Swoft\Task\Task;
use Swoole\Server;

/**
 * the trait of Server
 *
 * @uses      ServerTrait
 * @version   2018年01月07日
 * @author    stelin <phpcrazy@126.com>
 * @copyright Copyright 2010-2016 swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
trait ServerTrait
{
    /**
     * master进程启动前初始化
     *
     * @param Server $server
     */
    public function onStart(Server $server)
    {
        file_put_contents($this->serverSetting['pfile'], $server->master_pid);
        file_put_contents($this->serverSetting['pfile'], ',' . $server->manager_pid, FILE_APPEND);
        ProcessHelper::setProcessTitle($this->serverSetting['pname'] . " master process (" . $this->scriptFile . ")");
    }

    /**
     * mananger进程启动前初始化
     *
     * @param Server $server
     */
    public function onManagerStart(Server $server)
    {
        ProcessHelper::setProcessTitle($this->serverSetting['pname'] . " manager process");
    }

    /**
     * worker进程启动前初始化
     *
     * @param Server $server   server
     * @param int    $workerId workerId
     */
    public function onWorkerStart(Server $server, int $workerId)
    {
        // worker和task进程初始化
        $setting = $server->setting;
        if ($workerId >= $setting['worker_num']) {
            ApplicationContext::setContext(ApplicationContext::TASK);
            ProcessHelper::setProcessTitle($this->serverSetting['pname'] . " task process");
        } else {
            ApplicationContext::setContext(ApplicationContext::WORKER);
            ProcessHelper::setProcessTitle($this->serverSetting['pname'] . " worker process");
        }

        // reload重新加载文件
        $this->beforeOnWorkerStart($server, $workerId);
    }

    /**
     * 管道消息处理
     *
     * @param Server $server
     * @param int    $fromWorkerId
     * @param string $message
     */
    public function onPipeMessage(Server $server, int $fromWorkerId, string $message)
    {
        list($type, $data) = PipeMessage::unpack($message);
        if ($type == PipeMessage::TYPE_TASK) {
            $this->onPipeMessageTask($data);
        }
    }

    /**
     * Tasker进程回调
     *
     * @param Server $server
     * @param int    $taskId
     * @param int    $workerId
     * @param mixed  $data
     *
     * @return mixed
     *
     */
    public function onTask(Server $server, int $taskId, int $workerId, $data)
    {
        // 设置taskId
        Task::setId($taskId);

        // 用户自定义的任务，不是字符串
        if (!is_string($data)) {
            return parent::onTask($server, $taskId, $workerId, $data);
        }

        // 用户自定义的任务，不是序列化字符串
        $task = @unserialize($data);
        if ($task === false) {
            return parent::onTask($server, $taskId, $workerId, $data);
        }

        // 用户自定义的任务，不存在类型
        if (!isset($task['type'])) {
            return parent::onTask($server, $taskId, $workerId, $data);
        }

        $name = $task['name'];
        $type = $task['type'];
        $method = $task['method'];
        $params = $task['params'];
        $logid = $task['logid'] ?? uniqid();
        $spanid = $task['spanid'] ?? 0;

        $event = new BeforeTaskEvent(AppEvent::BEFORE_TASK, $logid, $spanid, $name, $method, $type);
        App::trigger($event);
        $result = Task::run($name, $method, $params);
        App::trigger(AppEvent::AFTER_TASK, null, $type);

        if ($type == Task::TYPE_CRON) {
            return $result;
        }
        $server->finish($result);
    }

    /**
     * worker收到tasker消息的回调函数
     *
     * @param Server $server
     * @param int    $taskId
     * @param mixed  $data
     */
    public function onFinish(Server $server, int $taskId, $data)
    {
        //        var_dump($data, '----------((((((9999999999');
    }

    /**
     * @param string $scriptFile
     */
    public function setScriptFile(string $scriptFile)
    {
        $this->scriptFile = $scriptFile;
    }

    /**
     * swoole server start之前运行
     */
    protected function beforeStart()
    {
        // 添加共享内存表
        $this->addShareMemory();
        // 添加用户自定义进程
        $this->addUserProcesses();
    }

    /**
     * 添加共享内存表
     */
    private function addShareMemory()
    {
        // 初始化定时任务共享内存表
        if (isset($this->serverSetting['cronable']) && (int)$this->serverSetting['cronable'] === 1) {
            $this->initCrontabMemoryTable();
        }
    }

    /**
     * 初始化crontab共享内存表
     */
    private function initCrontabMemoryTable()
    {
        $taskCount = isset($this->crontabSetting['task_count']) && $this->crontabSetting['task_count'] > 0 ? $this->crontabSetting['task_count']
            : null;
        $taskQueue = isset($this->crontabSetting['task_queue']) && $this->crontabSetting['task_queue'] > 0 ? $this->crontabSetting['task_queue']
            : null;

        TableCrontab::init($taskCount, $taskQueue);
    }

    /**
     * 任务类型的管道消息
     *
     * @param array $data 数据
     */
    private function onPipeMessageTask(array $data)
    {
        // 任务信息
        $type = $data['type'];
        $taskName = $data['name'];
        $params = $data['params'];
        $timeout = $data['timeout'];
        $methodName = $data['method'];

        // 投递任务
        Task::deliver($taskName, $methodName, $params, $type, $timeout);
    }

    /**
     * 添加自定义进程
     */
    private function addUserProcesses()
    {
        foreach ($this->processSetting as $name => $processClassName) {
            $userProcess = Process::create($this, $name, $processClassName);
            if ($userProcess === null) {
                continue;
            }
            $this->server->addProcess($userProcess);
        }
    }

    /**
     * worker start之前运行
     *
     * @param Server $server   server
     * @param int    $workerId workerId
     */
    private function beforeOnWorkerStart(Server $server, int $workerId)
    {
        // 加载bean
        $this->reloadBean();
    }

    /**
     * reload bean
     */
    protected function reloadBean()
    {
        BeanFactory::reload();
        $initApplicationContext = new InitApplicationContext();
        $initApplicationContext->init();
    }
}