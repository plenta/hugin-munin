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

namespace Plenta\HuginMunin\Helper;

use Contao\Environment;
use Symfony\Component\HttpFoundation\RequestStack;

class Odin
{
    protected bool $alreadyRan = false;
    
    public function __construct(
        protected RequestStack $requestStack,
        protected PhpExecutableFinder $phpExecutableFinder,
        protected string $env,
        protected string $projectDir,
        protected string $host,
        protected string $scheme
    ) {
    }

    public function sendHuginMunin(string $project, string $version): void
    {
        if (!$this->alreadyRan) {
            $request = $this->requestStack->getMainRequest();
            $phpBin = $this->phpExecutableFinder->getExecutable();

            if ($request) {
                $url = Environment::get('url').$request->getPathInfo();
            } elseif ('localhost' !== $this->host) {
                $url = $this->scheme.'://'.$this->host;
            }
            try {
                $ip = Environment::get('server');
            } catch (\Exception $e) {
                $ip = gethostbyname(gethostname());
            }

            if (empty($url) || empty($ip)) {
                return;
            }

            if (empty($phpBin)) {
                return;
            }

            $command = escapeshellcmd($phpBin).' ';
            $command .= escapeshellarg($this->projectDir.'/vendor/bin/contao-console').' huginmunin:heimdall ';
            $command .= escapeshellarg($url).' ';
            $command .= escapeshellarg($ip).' ';
            $command .= escapeshellarg($project).' ';
            $command .= escapeshellarg($version).' ';
            $command .= escapeshellarg('--env='.$this->env).' ';
            $command .= '> /dev/null 2>&1 &';

            exec($command);
            
            $this->alreadyRan = true;
        }
    }
}
