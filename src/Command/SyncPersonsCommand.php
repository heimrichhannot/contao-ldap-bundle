<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use HeimrichHannot\LdapBundle\HeimrichHannotLdapBundle;
use HeimrichHannot\LdapBundle\Util\LdapUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncPersonsCommand extends Command
{
    /**
     * @var SymfonyStyle
     */
    protected $io;
    /**
     * @var ContaoFramework
     */
    protected $framework;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var array
     */
    protected $bundleConfig;
    /**
     * @var LdapUtil
     */
    protected $ldapUtil;

    public function __construct(
        array $bundleConfig,
        ContaoFramework $contaoFramework,
        LdapUtil $ldapUtil,
        $name = null
    ) {
        $this->bundleConfig = $bundleConfig;
        $this->framework = $contaoFramework;
        $this->ldapUtil = $ldapUtil;
        $this->name = $name;

        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('huh_ldap:sync_persons')->setDescription('Synchronizes persons and groups from an ldap server to the contao instance.');

        $this->addOption('dry-run', null, InputOption::VALUE_OPTIONAL, 'See what the command would do without changing any data.', false);
        $this->addOption('mode', null, InputOption::VALUE_OPTIONAL, 'Limit the command to users or members. Possible values are "user" and "member". Leave empty to do both.');
        $this->addOption('uids', null, InputOption::VALUE_OPTIONAL, 'Limit the command to specific uids by providing a comma-separated list.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->framework->initialize();

        $dryRun = false !== $input->getOption('dry-run');
        $modes = $input->getOption('mode') ? [$input->getOption('mode')] : HeimrichHannotLdapBundle::MODES;
        $uids = array_filter(explode(',', str_replace(' ', '', $input->getOption('uids') ?: '')));

        // check for illegal values
        foreach ($modes as $mode) {
            if (!\in_array($mode, HeimrichHannotLdapBundle::MODES)) {
                $this->io->error('Illegal mode detected: '.$mode);

                return 0;
            }
        }

        // check connection
        foreach ($modes as $mode) {
            try {
                $result = $this->ldapUtil->getConnection($mode, [
                    'throwExceptions' => true,
                ]);
            } catch (\Exception $e) {
                $this->io->error($e->getMessage());

                return 1;
            }

            if (false === $result) {
                $this->io->error('Connection to LPAP server failed. Did you create a correct configuration in your config.yml?');

                return 1;
            }

            $this->ldapUtil->syncPersons($mode, [
                'io' => $this->io,
                'dryRun' => $dryRun,
                'limitUids' => $uids,
            ]);
        }

        return 0;
    }
}
