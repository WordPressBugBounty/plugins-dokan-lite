<?php

declare(strict_types=1);

namespace Appsero\Tests;

use Appsero\Tests\Stub\ClientStub;
use Appsero\Updater;
use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class UpdaterTest extends TestCase
{
    /**
     * @var ClientStub
     */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when( 'sanitize_key' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'is_multisite' )->justReturn( false );
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( '' );

        $GLOBALS['pagenow'] = 'index.php';

        $this->client = new ClientStub();
    }

    protected function tearDown(): void
    {
        unset( $GLOBALS['pagenow'] );
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Build the version-info object that Updater caches in a transient.
     *
     * @param string $new_version
     * @return object
     */
    private function version_info( string $new_version )
    {
        $info = new \stdClass();
        $info->name        = 'Happy Elementor Addons Pro';
        $info->slug        = 'happy-elementor-addons-pro';
        $info->new_version = $new_version;

        return $info;
    }

    /**
     * The bug: these filters were only registered inside admin_init, so they did
     * not exist during the WP-Cron request that drives auto-updates, leaving the
     * plugin out of the update_plugins transient and blanking its Automatic
     * Updates column.
     *
     * @see https://github.com/getdokan/client-issue/issues/492
     */
    public function test_plugin_update_transient_filter_is_registered_on_every_request(): void
    {
        Filters\expectAdded( 'pre_set_site_transient_update_plugins' )->once();

        new Updater( $this->client );
    }

    public function test_plugins_api_filter_is_registered_on_every_request(): void
    {
        Filters\expectAdded( 'plugins_api' )->once();

        new Updater( $this->client );
    }

    public function test_admin_only_ui_is_still_deferred_to_admin_init(): void
    {
        Actions\expectAdded( 'admin_init' )->once();

        new Updater( $this->client );
    }

    public function test_theme_client_does_not_register_plugin_filters(): void
    {
        $this->client->type = 'theme';

        Filters\expectAdded( 'pre_set_site_transient_update_themes' )->once();
        Filters\expectAdded( 'pre_set_site_transient_update_plugins' )->never();
        Filters\expectAdded( 'plugins_api' )->never();

        new Updater( $this->client );
    }

    public function test_check_plugin_update_adds_plugin_to_response_when_newer_version_exists(): void
    {
        $this->client->project_version = '1.0.0';

        Functions\when( 'get_transient' )->justReturn( $this->version_info( '2.0.0' ) );

        $updater = new Updater( $this->client );

        $result = $updater->check_plugin_update( new \stdClass() );

        $this->assertArrayHasKey( $this->client->basename, (array) $result->response );
        $this->assertSame( '2.0.0', $result->response[ $this->client->basename ]->new_version );
    }

    /**
     * no_update matters as much as response: WP_Plugins_List_Table sets
     * 'update-supported' => true from EITHER bucket, and without it the
     * Automatic Updates column renders as unavailable.
     */
    public function test_check_plugin_update_adds_plugin_to_no_update_when_already_current(): void
    {
        $this->client->project_version = '2.0.0';

        Functions\when( 'get_transient' )->justReturn( $this->version_info( '2.0.0' ) );

        $updater = new Updater( $this->client );

        $result = $updater->check_plugin_update( new \stdClass() );

        $this->assertArrayHasKey( $this->client->basename, (array) $result->no_update );
        $this->assertArrayNotHasKey( $this->client->basename, (array) ( $result->response ?? [] ) );
    }

    public function test_check_plugin_update_tolerates_non_object_transient(): void
    {
        Functions\when( 'get_transient' )->justReturn( $this->version_info( '2.0.0' ) );

        $updater = new Updater( $this->client );

        $result = $updater->check_plugin_update( false );

        $this->assertIsObject( $result );
        $this->assertArrayHasKey( $this->client->basename, (array) $result->response );
    }

    /**
     * AppSero only includes `package` in the version-info payload when the
     * license resolves to ACTIVE (appsero-api ReleaseResponseService); `new_version`
     * is returned regardless. Making cron work (this branch) means a package-less
     * entry now reaches WP_Automatic_Updater, which has no package guard (unlike
     * the admin UI, which does). Without `disable_autoupdate`, should_update()
     * returns true and WordPress emails the admin a failed-auto-update notice on
     * every cron run, forever. The entry must still land in `response` so the
     * update row and license-renewal prompt keep rendering.
     */
    public function test_check_plugin_update_sets_disable_autoupdate_when_package_is_missing(): void
    {
        $this->client->project_version = '1.0.0';

        // Deliberately no `package` - the shape AppSero returns for a premium
        // plugin whose license is expired, inactive, or never entered.
        Functions\when( 'get_transient' )->justReturn( $this->version_info( '2.0.0' ) );

        $updater = new Updater( $this->client );

        $result = $updater->check_plugin_update( new \stdClass() );

        $this->assertArrayHasKey( $this->client->basename, (array) $result->response );
        $this->assertTrue( $result->response[ $this->client->basename ]->disable_autoupdate );
    }

    /**
     * The converse: a licensed site (package present) must keep auto-updating
     * exactly as before. Guards against over-applying the flag.
     */
    public function test_check_plugin_update_does_not_set_disable_autoupdate_when_package_is_present(): void
    {
        $this->client->project_version = '1.0.0';

        $version_info          = $this->version_info( '2.0.0' );
        $version_info->package = 'https://api.appsero.com/download/happy-elementor-addons-pro.zip';

        Functions\when( 'get_transient' )->justReturn( $version_info );

        $updater = new Updater( $this->client );

        $result = $updater->check_plugin_update( new \stdClass() );

        $this->assertArrayHasKey( $this->client->basename, (array) $result->response );
        $this->assertFalse( property_exists( $result->response[ $this->client->basename ], 'disable_autoupdate' ) );
    }

    /**
     * disable_autoupdate is only meaningful on a response entry, so it must not
     * be set when the entry lands in no_update.
     */
    public function test_check_plugin_update_does_not_set_disable_autoupdate_when_entry_is_no_update(): void
    {
        $this->client->project_version = '2.0.0';

        // No package (inactive-license shape) but already current, so this entry
        // goes to no_update, not response.
        Functions\when( 'get_transient' )->justReturn( $this->version_info( '2.0.0' ) );

        $updater = new Updater( $this->client );

        $result = $updater->check_plugin_update( new \stdClass() );

        $this->assertArrayHasKey( $this->client->basename, (array) $result->no_update );
        $this->assertFalse( property_exists( $result->no_update[ $this->client->basename ], 'disable_autoupdate' ) );
    }
}
