<?php

/*
 * This file is part of the EloquentSerializer package.
 * 
 * (c) Théo FIDRY <theo.fidry@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fidry\EloquentSerializer\Symfony;

use Fidry\EloquentSerializer\TestCaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
class EloquentModelNormalizerTest extends KernelTestCase
{
    use TestCaseTrait;

    public function setUp()
    {
        parent::setUp();
        static::bootKernel();
        static::$kernel->getContainer()->get('wouterj_eloquent.initializer')->initialize();

        $this->serializer = static::$kernel->getContainer()->get('serializer');
    }
}
