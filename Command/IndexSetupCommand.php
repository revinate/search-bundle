<?php

namespace Revinate\SearchBundle\Command;

use Revinate\SearchBundle\Lib\Search\ElasticSearch\MappingManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IndexSetupCommand
 *
 * Update the schemas, including the update for both indices and templates
 *
 * @package Revinate\SearchBundle\Command
 */
class IndexSetupCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'revinate:search:schema-update';

    /** @var MappingManager */
    protected $mappingManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Set up all the indices and templates');
    }

    /**
     * @inheritdoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->mappingManager = $this->getContainer()->get('revinate_search.mapping_manager');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->mappingManager->update();
    }
}