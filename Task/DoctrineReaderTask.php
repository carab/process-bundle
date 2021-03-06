<?php
/*
 * This file is part of the CleverAge/ProcessBundle package.
 *
 * Copyright (C) 2017-2018 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\ProcessBundle\Task;

use CleverAge\ProcessBundle\Model\IterableTaskInterface;
use CleverAge\ProcessBundle\Model\ProcessState;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Psr\Log\LogLevel;
use Doctrine\ORM\Internal\Hydration\IterableResult;

/**
 * Fetch entities from doctrine
 *
 * @author Valentin Clavreul <vclavreul@clever-age.com>
 * @author Vincent Chalnot <vchalnot@clever-age.com>
 */
class DoctrineReaderTask extends AbstractDoctrineQueryTask implements IterableTaskInterface
{
    /** @var IterableResult */
    protected $iterator;

    /**
     * Moves the internal pointer to the next element,
     * return true if the task has a next element
     * return false if the task has terminated it's iteration
     *
     * @param ProcessState $state
     *
     * @throws \LogicException
     *
     * @return bool
     */
    public function next(ProcessState $state)
    {
        if (!$this->iterator instanceof IterableResult) {
            throw new \LogicException('No iterator initialized');
        }
        $this->iterator->next();

        return $this->iterator->valid();
    }

    /**
     * @param ProcessState $state
     *
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\OptionsResolver\Exception\ExceptionInterface
     * @throws \UnexpectedValueException
     */
    public function execute(ProcessState $state)
    {
        $options = $this->getOptions($state);
        if (!$this->iterator) {
            $class = $options['class_name'];
            $entityManager = $this->doctrine->getManagerForClass($class);
            if (!$entityManager instanceof EntityManagerInterface) {
                throw new \UnexpectedValueException("No manager found for class {$class}");
            }
            $repository = $entityManager->getRepository($class);
            if (!$repository instanceof EntityRepository) {
                throw new \UnexpectedValueException("No repository found for class {$class}");
            }
            $this->initIterator($repository, $options);
        }

        $result = $this->iterator->current();

        // Handle empty results
        if (false === $result) {
            $state->log('Empty resultset for query', LogLevel::WARNING, $options['class_name'], $options);
            $state->setStopped(true);

            return;
        }

        $state->setOutput(reset($result));
    }

    /**
     * @param EntityRepository $repository
     * @param array            $options
     *
     * @throws \UnexpectedValueException
     */
    protected function initIterator(EntityRepository $repository, array $options)
    {
        $qb = $this->getQueryBuilder(
            $repository,
            $options['criteria'],
            $options['order_by'],
            $options['limit'],
            $options['offset']
        );

        $this->iterator = $qb->getQuery()->iterate();
        $this->iterator->next(); // Move to first element
    }
}
