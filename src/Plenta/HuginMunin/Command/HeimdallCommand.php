<?php

declare(strict_types=1);

/**
 * Hugin and Munin
 *
 * @copyright     Copyright (c) 2023, Plenta.io
 * @author        Plenta.io <https://plenta.io>
 * @link          https://jobboerse-software.de
 * @license       proprietary
 */

namespace Plenta\HuginMunin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'huginmunin:heimdall', description: 'Phone home')]
class HeimdallCommand extends Command
{
    public function __construct(protected HttpClientInterface $httpClient, protected Filesystem $filesystem)
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        if ($this->filesystem->exists('assets/heimdall_'.$type)) {
            $content = file_get_contents('assets/heimdall_'.$type);
            if ((int) $content > time() - 24 * 3600) {
                return Command::SUCCESS;
            }
        }
        $this->filesystem->dumpFile('assets/heimdall_'.$type, time());
        $io = new SymfonyStyle($input, $output);
        $env = $input->getOptions()['env'];
        $this->httpClient->request('POST', 'https://license.plenta.io/_plenta/api/v1/account/greetings', [
            'body' => [
                'url' => $input->getArgument('url'),
                'ip' => $input->getArgument('ip'),
                'type' => $type,
                'version' => $input->getArgument('version'),
            ],
            'verify_host' => 'prod' === $env,
            'verify_peer' => 'prod' === $env,
        ]);
        $io->success(ucfirst($type).' phone home');

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED)
            ->addArgument('ip', InputArgument::REQUIRED)
            ->addArgument('type', InputArgument::REQUIRED)
            ->addArgument('version', InputArgument::REQUIRED)
        ;
    }
}
