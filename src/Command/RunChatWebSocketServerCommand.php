<?php

namespace App\Command;

use App\WebSocket\ChatMessageNotifier;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\SocketServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:chat:websocket', description: 'Run chat websocket server for real-time messaging')]
final class RunChatWebSocketServerCommand extends Command
{
    public function __construct(
        private ChatMessageNotifier $notifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Websocket host', '0.0.0.0')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Websocket port', '8081');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');

        $loop = LoopFactory::create();
        $loop->addPeriodicTimer(0.1, function (): void {
            $this->notifier->tick();
        });

        $socket = new SocketServer(sprintf('%s:%d', $host, $port), [], $loop);
        new IoServer(
            new HttpServer(new WsServer($this->notifier)),
            $socket,
            $loop,
        );

        $output->writeln(sprintf('<info>Chat WebSocket server started on ws://%s:%d</info>', $host, $port));
        $output->writeln('<comment>Press Ctrl+C to stop.</comment>');

        $loop->run();

        return Command::SUCCESS;
    }
}
