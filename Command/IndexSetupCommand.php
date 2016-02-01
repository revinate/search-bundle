<?php

namespace Revinate\SearchBundle\Command;

use Revinate\SearchBundle\Service\RevinateSearch;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IndexSetupCommand
 *
 * Update the revinate search schemas, including the update for both of indices and templates
 *
 * @package Revinate\SearchBundle\Command
 */
class IndexSetupCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'revinate:search:schema-update';

    /** @var RevinateSearch */
    protected $revinateSearch;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Set up all the indices');
    }

    /**
     * @inheritdoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->revinateSearch = $this->getContainer()->get('revinate_search');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mappingManager = $this->revinateSearch->getMappingManager();
        $mappingManager->update();
    }
}