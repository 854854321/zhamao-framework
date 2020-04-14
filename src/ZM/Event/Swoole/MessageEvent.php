<?php


namespace ZM\Event\Swoole;


use Closure;
use Framework\Console;
use Framework\ZMBuf;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use ZM\Annotation\Swoole\SwooleEventAfter;
use ZM\Annotation\Swoole\SwooleEventAt;
use ZM\Connection\ConnectionManager;
use Exception;
use ZM\Event\EventHandler;
use ZM\ModBase;
use ZM\ModHandleType;
use ZM\Utils\ZMUtil;

class MessageEvent implements SwooleEvent
{
    /**
     * @var Server
     */
    public $server;
    /**
     * @var Frame
     */
    public $frame;

    public function __construct(Server $server, Frame $frame) {
        $this->server = $server;
        $this->frame = $frame;
    }

    /**
     * @inheritDoc
     */
    public function onActivate() {
        ZMUtil::checkWait();
        $conn = ConnectionManager::get($this->frame->fd);
        set_coroutine_params(["server" => $this->server, "frame" => $this->frame, "connection" => $conn]);
        try {
            if ($conn->getType() == "qq") {
                $data = json_decode($this->frame->data, true);
                if (isset($data["post_type"])) {
                    set_coroutine_params(["data" => $data, "connection" => $conn]);
                    EventHandler::callCQEvent($data, ConnectionManager::get($this->frame->fd), 0);
                } else
                    EventHandler::callCQResponse($data);
            }
            foreach (ZMBuf::$events[SwooleEventAt::class] ?? [] as $v) {
                if (strtolower($v->type) == "message" && $this->parseSwooleRule($v)) {
                    $c = $v->class;
                    /** @var ModBase $class */
                    $class = new $c(["server" => $this->server, "frame" => $this->frame, "connection" => $conn], ModHandleType::SWOOLE_MESSAGE);
                    call_user_func_array([$class, $v->method], [$conn]);
                    if ($class->block_continue) break;
                }
            }
        } catch (Exception $e) {
            Console::error("出现错误: " . $e->getMessage());
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function onAfter() {
        foreach (ZMBuf::$events[SwooleEventAfter::class] ?? [] as $v) {
            if (strtolower($v->type) == "message" && $this->parseSwooleRule($v) === true) {
                $conn = ConnectionManager::get($this->frame->fd);
                $c = $v->class;
                /** @var ModBase $class */
                $class = new $c(["server" => $this->server, "frame" => $this->frame, "connection" => $conn], ModHandleType::SWOOLE_MESSAGE);
                call_user_func_array([$class, $v->method], []);
                if ($class->block_continue) break;
            }
        }
        return $this;
    }

    private function parseSwooleRule($v) {
        switch (explode(":", $v->rule)[0]) {
            case "connectType": //websocket连接类型
                if ($v->callback instanceof Closure) return call_user_func($v->callback, ConnectionManager::get($this->frame->fd));
                break;
            case "dataEqual": //handle websocket message事件时才能用
                if ($v->callback instanceof Closure) return call_user_func($v->callback, $this->frame->data);
                break;
        }
        return true;
    }
}
