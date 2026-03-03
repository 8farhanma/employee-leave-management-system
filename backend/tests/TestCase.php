<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        // Cegah segfault akibat GC object Eloquent antar test
        foreach (array_keys(get_object_vars($this)) as $prop) {
            unset($this->$prop);
        }
    }
}