<?php
/**
 * @version 24/2/21 1:39 p. m.
 * @author  David Lopez <dleo.lopez@gmail.com>
 */

namespace Hsd\Zello\Tests;


use Hsd\Zello\Api;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    /**
     * @author David Lopez <dlopez@hsd.cl>
     */
    public function setUp(): void
    {
        parent::setUp();
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    }

    /**
     * @version 24/2/21
     * @author  David Lopez <dlopez@hsd.cl>
     */
    public function testCanLogin()
    {
        $api = new Api($_ENV['ZELLO_URL'], $_ENV['ZELLO_API_KEY']);
        $this->assertInstanceOf(Api::class, $api);

        return $api;
    }

    /**
     * @param Api $api
     * @depends testCanLogin
     * @version 24/2/21
     * @author  David Lopez <dlopez@hsd.cl>
     */
    public function testCanAuth(Api $api)
    {
        $api->auth($_ENV['ZELLO_USERNAME'], $_ENV['ZELLO_PASSWORD']);
        $this->assertIsString($api->sid);

        return $api;
    }

    /**
     * @param Api $api
     * @author  David Lopez <dleo.lopez@gmail.com>
     * @depends testCanAuth
     */
    public function testCanGetLocation(Api $api)
    {
        $northeast = [
            "-30.708945",
            "-70.899361"
        ];
        $southwest = [
            "-30.720996",
            "-70.916227"
        ];
        $this->assertTrue($api->getLocations($northeast, $southwest));
        var_dump(json_encode($api->data));
    }
}