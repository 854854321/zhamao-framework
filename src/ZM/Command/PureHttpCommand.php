<?php


namespace ZM\Command;


use Swoole\Atomic;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZM\Config\ZMConfig;
use ZM\Console\Console;
use ZM\Store\ZMBuf;
use ZM\Utils\HttpUtil;

class PureHttpCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'simple-http-server';

    protected function configure() {
        $this->setDescription("Run a simple http server | 启动一个简单的文件 HTTP 服务器");
        $this->setHelp("直接运行可以启动");
        $this->addArgument('dir', InputArgument::OPTIONAL, 'Your directory');
        $this->addOption("host", 'H', InputOption::VALUE_REQUIRED, "启动监听地址");
        $this->addOption("port", 'P', InputOption::VALUE_REQUIRED, "启动监听地址的端口");
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $tty_width = explode(" ", trim(exec("stty size")))[1];
        $global = ZMConfig::get("global");
        $host = $input->getOption("host") ?? $global["host"];
        $port = $input->getOption("port") ?? $global["port"];
        $server = new Server($host, $port);
        $server->set(ZMConfig::get("global", "swoole"));
        Console::init(0, $server);
        ZMBuf::$atomics["request"] = [];
        for ($i = 0; $i < 32; ++$i) {
            ZMBuf::$atomics["request"][$i] = new Atomic(0);
        }
        $index = ["index.html", "index.htm"];
        $server->on("request", function (Request $request, Response $response) use ($input, $index, $server) {
            ZMBuf::$atomics["request"][$server->worker_id]->add(1);
            HttpUtil::handleStaticPage(
                $request->server["request_uri"],
                $response,
                [
                    "document_root" => realpath($input->getArgument('dir') ?? '.'),
                    "document_index" => $index
                ]);
            echo "\r".Coroutine::stats()["coroutine_peak_num"];
        });
        $server->on("start", function ($server) {
            Process::signal(SIGINT, function () use ($server) {
                Console::warning("Server interrupted by keyboard.");
                for ($i = 0; $i < 32; ++$i) {
                    $num = ZMBuf::$atomics["request"][$i]->get();
                    if($num != 0)
                    echo "[$i]: ".$num."\n";
                }
                $server->shutdown();
                $server->stop();
            });
            Console::success("Server started. Use Ctrl+C to stop.");
        });
        $out = [
            "host" => $host,
            "port" => $port,
            "document_root" => realpath($input->getArgument('dir') ?? '.'),
            "document_index" => implode(", ", $index)
        ];
        Console::printProps($out, $tty_width);
        $server->start();
        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;
    }
}
