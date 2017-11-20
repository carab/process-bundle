<?php
/*
 *    CleverAge/ProcessBundle
 *    Copyright (C) 2017 Clever-Age
 *
 *    This program is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace CleverAge\ProcessBundle\Command;


use CleverAge\ProcessBundle\Configuration\TaskConfiguration;
use CleverAge\ProcessBundle\Model\BlockingTaskInterface;
use CleverAge\ProcessBundle\Model\IterableTaskInterface;
use CleverAge\ProcessBundle\Model\TaskInterface;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Describe a process configuration
 * This is a POC, waiting to evolve properly
 *
 * @author Valentin Clavreul <vclavreul@clever-age.com>
 */
class ProcessHelpCommand extends ContainerAwareCommand
{

    const CHAR_DOWN = '│';
    const CHAR_MERGE = '┘';
    const CHAR_MULTIMERGE = '┴─';
    const CHAR_JUMP = '┿─';
    const CHAR_HORIZ = '──';
    const CHAR_MULTIEXPAND = '┬─';
    const CHAR_EXPAND = '┐';
    const CHAR_RECEIVE = '├─';
    const CHAR_NODE = '■';

    const BRANCH_SIZE = 2;

    /** @var ProcessConfigurationRegistry */
    protected $processConfigRegistry;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('cleverage:process:help');
        $this->addArgument('process_code');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->getFormatter()->setStyle('fire', new OutputFormatterStyle('red'));

        $this->processConfigRegistry = $this->getContainer()->get('cleverage_process.registry.process_configuration');
        $processCode = $input->getArgument('process_code');
        $process = $this->processConfigRegistry->getProcessConfiguration($processCode);

        $output->writeln("<info>The process </info>{$processCode}<info> contains the following tasks:</info>");

        $branches = [];

        foreach ($process->getMainTaskGroup() as $taskCode) {
            $task = $process->getTaskConfiguration($taskCode);
            $branchesToMerge = [];
            $gapBranches = [];
            $origin = null;
            $final = null;

            // Check previous branches
            if (empty($task->getPreviousTasksConfigurations())) {
                $branches[] = $task->getCode();
            } elseif (count($task->getPreviousTasksConfigurations()) === 1) {
                $prevTask = $task->getPreviousTasksConfigurations()[0]->getCode();
                foreach (array_reverse($branches, true) as $i => $branchTask) {
                    if ($branchTask === $prevTask) {
                        $branches[$i] = $taskCode;
                        break;
                    }
                }
            } else {
                foreach ($task->getPreviousTasksConfigurations() as $prevTask) {
                    foreach (array_reverse($branches, true) as $i => $branchTask) {
                        if ($branchTask === $prevTask->getCode()) {
                            $branchesToMerge[] = $i;
                            break;
                        }
                    }
                }

                // Don't touch the 1st branch to merge
                sort($branchesToMerge);

                $gapFrom = null;
                $gapTo = null;
                foreach ($branchesToMerge as $i) {
                    $gapTo = $i;
                    if ($gapFrom !== null) {
                        for ($j = $gapFrom + 1; $j < $gapTo; $j++) {
                            $gapBranches[] = $j;
                        }
                    }
                    $gapFrom = $i;
                }

                $origin = array_shift($branchesToMerge);
                $final = $gapFrom;
                $branches[$origin] = $taskCode;
            }

            // Merge branches
            if (!empty($branchesToMerge)) {
                $this->writeBranches($output, $branches);

                $this->writeBranches($output, $branches, '', function ($taskCode, $i) use ($branchesToMerge, $gapBranches, $origin) {
                    return in_array($i, $branchesToMerge) || in_array($i, $gapBranches) || $i === $origin;
                }, function ($taskCode, $i) use ($gapBranches, $origin, $final, $branches) {
                    if ($i === $origin) {
                        return self::CHAR_RECEIVE;
                    }
                    if (in_array($i, $gapBranches)) {
                        if ($branches[$i] !== null) {
                            return self::CHAR_JUMP;
                        } else {
                            return self::CHAR_HORIZ;
                        }
                    }

                    if ($i === $final) {
                        return self::CHAR_MERGE;
                    }

                    return self::CHAR_MULTIMERGE;
                });

                foreach ($branches as $i => $branchTask) {
                    if (in_array($i, $branchesToMerge)) {
                        $branches[$i] = null;
                    }
                }
                $this->writeBranches($output, $branches);
            }

            // Cleanup empty trailing branches
            foreach (array_reverse($branches, true) as $i => $branchTask) {
                if ($branchTask !== null) {
                    $branches = array_slice($branches, 0, $i + 1);
                    break;
                }
            }

            // Write main line
            $nodeStr = self::CHAR_NODE;
            if ($task->isInErrorBranch()) {
                $nodeStr = "<fire>{$nodeStr}</fire>";
            }

            $this->writeBranches($output, $branches, $this->getTaskDescription($task), function ($branchTask, $i) use ($taskCode) {
                return $branchTask === $taskCode;
            }, $nodeStr);

            // Check next tasks
            $nextTasks = array_map(function (TaskConfiguration $task) {
                return $task->getCode();
            }, array_merge($task->getNextTasksConfigurations(), $task->getErrorTasksConfigurations()));
            if (count($nextTasks) > 1) {
                $this->writeBranches($output, $branches);
                array_shift($nextTasks);
                $origin = array_search($taskCode, $branches, true);
                $expandBranches = [];
                foreach ($nextTasks as $nextTask) {
                    $index = array_search(null, $branches, true);
                    if ($index !== false) {
                        $branches[$index] = $taskCode;
                        $expandBranches[] = $index;
                    } else {
                        $expandBranches[] = count($branches);
                        $branches[] = $taskCode;
                    }
                }
                $gapBranches = [];
                sort($expandBranches);
                $gapFrom = $origin;
                $gapTo = null;

                foreach ($expandBranches as $i) {
                    $gapTo = $i;
                    for ($j = $gapFrom + 1; $j < $gapTo; $j++) {
                        $gapBranches[] = $j;
                    }
                    $gapFrom = $i;
                }
                $final = $gapFrom;

                $this->writeBranches($output, $branches, '', function ($branchTask, $i) use ($origin, $final) {
                    return $i >= $origin && $i <= $final;
                }, function ($branchTask, $i) use ($origin, $branches, $gapBranches, $final) {
                    if ($i === $origin) {
                        return self::CHAR_RECEIVE;
                    }
                    if (in_array($i, $gapBranches)) {
                        if ($branches[$i] !== null) {
                            return self::CHAR_JUMP;
                        } else {
                            return self::CHAR_HORIZ;
                        }
                    }
                    if ($final === $i) {
                        return self::CHAR_EXPAND;
                    }

                    return self::CHAR_MULTIEXPAND;
                });
            }

            if (empty($nextTasks)) {
                foreach ($branches as $i => $branchTask) {
                    if ($branchTask === $taskCode) {
                        $branches[$i] = null;
                    }
                }
            }

            // Cleanup empty trailing branches
            foreach (array_reverse($branches, true) as $i => $branchTask) {
                if ($branchTask !== null) {
                    $branches = array_slice($branches, 0, $i + 1);
                    break;
                }
            }

            $this->writeBranches($output, $branches);
        };
    }

    /**
     * @param OutputInterface $output
     * @param array           $branches
     * @param string          $comment
     * @param callable        $match
     * @param string|callable $char
     */
    protected function writeBranches(OutputInterface $output, $branches, $comment = '', $match = null, $char = null)
    {
        // Merge lines
        foreach ($branches as $i => $branchTask) {
            $str = '';
            if ($match !== null && $match($branchTask, $i)) {
                if (is_string($char)) {
                    $str = $char;
                } elseif (is_callable($char)) {
                    $str = $char($branchTask, $i);
                } else {
                    throw new \InvalidArgumentException("Char must be string|callable");
                }
            } elseif ($branchTask !== null) {
                $str = self::CHAR_DOWN;
            }

            // Str_pad does not work with unicode ?
            $noFormatStrLen = mb_strlen(preg_replace('/<[^>]*>/', '', $str));
            for ($i = $noFormatStrLen; $i < self::BRANCH_SIZE; $i++) {
                $str .= ' ';
            }
            $output->write($str);
        }
        $output->writeln($comment);

    }

    protected function getTaskDescription(TaskConfiguration $task)
    {
        $description = $task->getCode();
        $interfaces = [];
        $taskService = $this->getTaskService($task);

        if ($taskService instanceof IterableTaskInterface) {
            $interfaces[] = 'Iterable';
        }

        if ($taskService instanceof BlockingTaskInterface) {
            $interfaces[] = 'Blocking';
        }

        if (count($interfaces)) {
            $description .= ' <info>(' . implode(', ', $interfaces) . ')</info>';
        }

        return $description;
    }

    protected function getTaskService(TaskConfiguration $taskConfiguration)
    {
        $serviceReference = $taskConfiguration->getServiceReference();
        if (strpos($serviceReference, '@') === 0) {
            $task = $this->getContainer()->get(ltrim($serviceReference, '@'));
        } elseif (class_exists($serviceReference)) {
            $task = new $serviceReference();
        } else {
            throw new \UnexpectedValueException(
                "Unable to resolve service reference for Task '{$taskConfiguration->getCode()}'"
            );
        }
        if (!$task instanceof TaskInterface) {
            throw new \UnexpectedValueException(
                "Service defined in Task '{$taskConfiguration->getCode()}' is not a TaskInterface"
            );
        }

        return $task;
    }

}