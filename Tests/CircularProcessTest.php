<?php
/*
 * This file is part of the CleverAge/ProcessBundle package.
 *
 * Copyright (C) 2017-2018 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\ProcessBundle\Tests;

/**
 * Assert circular dependencies are correctly checked
 */
class CircularProcessTest extends AbstractProcessTest
{
    /**
     * @expectedException \CleverAge\ProcessBundle\Exception\CircularProcessException
     */
    public function testCircularProcess()
    {
        $this->processManager->execute('test.circular_process');
    }
}
