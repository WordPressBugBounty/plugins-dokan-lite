<?php

declare(strict_types=1);

namespace Appsero\Tests\Stub;

/**
 * Minimal stand-in for \Appsero\Client.
 *
 * Updater only reads these public properties and calls license(),
 * send_request() and __trans().
 */
class ClientStub
{
    public $slug = 'happy-elementor-addons-pro';

    public $name = 'Happy Elementor Addons Pro';

    public $type = 'plugin';

    public $basename = 'happy-elementor-addons-pro/happy-elementor-addons-pro.php';

    public $project_version = '1.0.0';

    public $hash = 'test-hash';

    /**
     * Value returned by send_request(). Tests overwrite this.
     *
     * @var mixed
     */
    public $request_response = null;

    /**
     * Number of times send_request() has been called.
     *
     * Lets tests assert that no remote HTTP call was made.
     *
     * @var int
     */
    public $send_request_calls = 0;

    public function license()
    {
        return new class {
            public function get_license(): array
            {
                return [ 'key' => 'TEST-LICENSE-KEY' ];
            }
        };
    }

    public function send_request( $params, $route, $blocking = false )
    {
        $this->send_request_calls++;

        return $this->request_response;
    }

    public function __trans( $text )
    {
        return $text;
    }
}
