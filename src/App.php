<?php

namespace zencodex\ComposerMirror;
use Pheanstalk\Pheanstalk;

class App extends InstanceBase
{
    /** @var $_instance 单例实例 */
    private static $_instance = null;

    /** @var $config 全局配置 */
    private $config;

    /** @var $clientHandler beanstalk连接句柄 */
    private $clientHandler;

    /** @var $cloud 远程云存储 */
    private $cloud;

    /** @var bool $terminated 是否结束 */
    public $terminated = 0;

    /** @var $timestamp 启动时间 */
    public $timestamp;

    /**
     * @return 单例实例|static
     */
    public static function getInstance()
    {
        $instance = self::$_instance;
        if ($instance == null) {
            $instance = new static;
            $instance->config = require(__DIR__ . '/lib/config.php');

            if ($instance->config->cloudsync) {
                $instance->cloud = new Cloud($instance->config);
                $instance->clientHandler = new Pheanstalk('127.0.0.1');
                $instance->clientHandler->useTube('composer');
            }

            $instance->timestamp = time();
            self::$_instance = $instance;
        }
        return $instance;
    }

    /**
     * @return 远程云存储
     */
    protected function getCloud()
    {
        return $this->cloud;
    }

    /**
     * @return beanstalk连接句柄
     */
    protected function getClientHandler()
    {
        return $this->clientHandler;
    }

    /**
     * @return 全局配置
     */
    protected function getConfig()
    {
        return $this->config;
    }

    /**
     * 推送异步任务到 beanstalk
     * @param $data
     * @param string $method
     * @param int $delay
     */
    protected function pushJob2Task($data, $method='pushOneFile', $delay = 0)
    {
        $this->clientHandler->put(
            json_encode([
                'method' => $method,
                'data' => $data
            ]),
            23,      // Give the job a priority of 23.
            $delay,  // Do not wait to put job into the ready queue.
            0        // Give the job 1 minute to run.
        );
    }

}
