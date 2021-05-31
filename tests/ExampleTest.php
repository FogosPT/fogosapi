<?php

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_example()
    {
        $this->get('/');

        $this->assertEquals(
            $this->app->version(),
            $this->response->getContent()
        );
    }
}
