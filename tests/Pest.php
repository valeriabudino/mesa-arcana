<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Los tests de Feature escritos con Pest extienden TestCase (tienen la app
| de Laravel arrancada) y usan RefreshDatabase sobre la base "testing".
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
