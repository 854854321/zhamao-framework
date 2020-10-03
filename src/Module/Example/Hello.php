<?php

namespace Module\Example;

use ZM\Annotation\Swoole\OnWorkerStart;
use ZM\Annotation\Swoole\OnTick;
use ZM\ConnectionManager\ConnectionObject;
use ZM\Console\Console;
use ZM\Annotation\CQ\CQCommand;
use ZM\Annotation\Http\Middleware;
use ZM\Annotation\Http\RequestMapping;
use ZM\Annotation\Swoole\SwooleEvent;
use ZM\Store\LightCache;
use ZM\Store\ZMBuf;
use ZM\Utils\ZMUtil;

/**
 * Class Hello
 * @package Module\Example
 * @since 2.0
 */
class Hello
{
    /**
     * 在机器人连接后向终端输出信息
     * @SwooleEvent("open",rule="connectIsQQ()")
     * @param $conn
     */
    public function onConnect(ConnectionObject $conn) {
        Console::info("机器人 " . $conn->getOption("connect_id") . " 已连接！");
    }

    /**
     * 在机器人断开连接后向终端输出信息
     * @SwooleEvent("close",rule="connectIsQQ()")
     * @param ConnectionObject $conn
     */
    public function onDisconnect(ConnectionObject $conn) {
        Console::info("机器人 " . $conn->getOption("connect_id") . " 已断开连接！");
    }

    /**
     * 向机器人发送"你好"，即可回复这句话
     * @CQCommand(match="你好",alias={"你好啊","你是谁"})
     */
    public function hello() {
        return "你好啊，我是由炸毛框架构建的机器人！";
    }

    /**
     * @CQCommand(".reload")
     */
    public function reload() {
        context()->reply("reloading...");
        ZMUtil::reload();
    }

    /**
     * @CQCommand("随机数")
     * @CQCommand(regexMatch="*从*到*的随机数")
     * @param $arg
     */
    public function randNum($arg) {
        // 获取第一个数字类型的参数
        $num1 = ctx()->getArgs($arg, ZM_MATCH_NUMBER, "请输入第一个数字");
        // 获取第二个数字类型的参数
        $num2 = ctx()->getArgs($arg, ZM_MATCH_NUMBER, "请输入第二个数字");
        $a = min(intval($num1), intval($num2));
        $b = max(intval($num1), intval($num2));
        // 回复用户结果
        ctx()->reply("随机数是：" . mt_rand($a, $b));
    }

    /**
     * 中间件测试的一个示例函数
     * @RequestMapping("/httpTimer")
     */
    public function timer() {
        ZMBuf::atomic("_tmp_2")->add(1);
        return "This page is used as testing TimerMiddleware! Do not use it in production.";
    }

    /**
     * 默认示例页面
     * @RequestMapping("/index")
     * @RequestMapping("/")
     */
    public function index() {
        return "Hello Zhamao!";
    }

    /**
     * 使用自定义参数的路由参数
     * @RequestMapping("/whoami/{name}")
     * @param $param
     * @return string
     */
    public function paramGet($param) {
        return "Your name: {$param["name"]}";
    }

    /**
     * 框架会默认关闭未知的WebSocket链接，因为这个绑定的事件，你可以根据你自己的需求进行修改
     * @SwooleEvent(type="open",rule="connectIsDefault()")
     */
    public function closeUnknownConn() {
        Console::info("Unknown connection , I will close it.");
        server()->close(ctx()->getConnection()->getFd());
    }
}
