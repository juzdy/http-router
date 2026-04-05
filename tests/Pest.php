<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific TestCase.
| This TestCase is used to scope helpers and provide a convenient way to interact with
| your application. You can look at the PHPUnit documentation or Pest to learn more.
|
*/

use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{
    // Define any shared test setup here
}

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing
| code specific to your project that you don't want to repeat in every file.
| Here you can also expose helpers as global functions to be used in your tests.
|
*/

function mock(string $class)
{
    return Mockery::mock($class);
}

function spy(object $object)
{
    return Mockery::spy($object);
}

function stub(string $class)
{
    return \PHPUnit\Framework\TestCase::createStub($class);
}
