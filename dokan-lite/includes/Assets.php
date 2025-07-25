<?php

namespace WeDevs\Dokan;

use Automattic\WooCommerce\Internal\Admin\WCAdminAssets;
use WeDevs\Dokan\Admin\Notices\Helper;
use WeDevs\Dokan\ProductCategory\Helper as CategoryHelper;
use WeDevs\Dokan\ReverseWithdrawal\SettingsHelper;
use WeDevs\Dokan\Utilities\OrderUtil;
use WeDevs\Dokan\Utilities\ReportUtil;

class Assets {

    /**
     * The constructor
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_all_scripts' ], 10 );
        add_filter( 'dokan_localized_args', [ $this, 'conditional_localized_args' ] );

        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ], 10 );
            add_action( 'admin_enqueue_scripts', [ $this, 'load_dokan_admin_notices_scripts' ], 8 );
            add_action( 'admin_enqueue_scripts', [ $this, 'load_dokan_global_scripts' ], 5 );
        } else {
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front_scripts' ] );
            add_action( 'wp_enqueue_scripts', [ $this, 'load_dokan_global_scripts' ], 5 );
            add_action( 'init', [ $this, 'register_wc_admin_scripts' ] );
        }
    }

    /**
     * Load global admin and promo notices scripts
     *
     * @since 3.3.6
     *
     * @return void
     */
    public function load_dokan_admin_notices_scripts() {
        wp_enqueue_script( 'dokan-promo-notice-js' );
        wp_enqueue_script( 'dokan-admin-notice-js' );
        $vue_localize_script = apply_filters(
            'dokan_promo_notice_localize_script', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'rest'    => [
                    'root'    => esc_url_raw( get_rest_url() ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                    'version' => 'dokan/v1',
                ],
                'urls'    => [
                    'assetsUrl' => DOKAN_PLUGIN_ASSEST,
                ],
            ]
        );
        wp_localize_script( 'dokan-promo-notice-js', 'dokan_promo', $vue_localize_script );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        global $post, $wp_version, $typenow;

        // load vue app inside the parent menu only
        if ( 'toplevel_page_dokan' === $hook ) {
            $localize_script           = $this->get_admin_localized_scripts();
            $vue_admin_localize_script = $this->get_vue_admin_localized_scripts();

            // Load common styles and scripts
            wp_enqueue_script( 'dokan-tinymce' );
            wp_enqueue_style( 'dokan-admin-css' );
            wp_enqueue_script( 'underscore' );
            wp_enqueue_media();

            wp_enqueue_script( 'dokan-tooltip' );
            wp_enqueue_script( 'dokan-admin' );

            // load styles
            wp_enqueue_style( 'dokan-vue-vendor' );
            wp_enqueue_style( 'dokan-vue-admin' );
            wp_enqueue_style( 'dokan-tinymce' );

            // load vue libraries and bootstrap the app
            wp_enqueue_script( 'dokan-accounting' );
            wp_enqueue_script( 'dokan-chart' );
            wp_enqueue_script( 'dokan-vue-vendor' );
            wp_localize_script( 'dokan-vue-vendor', 'dokan', $localize_script );
            wp_enqueue_script( 'dokan-vue-bootstrap' );
            $this->load_gmap_script();

            // allow other plugins to load scripts before the main app loads
            // @codingStandardsIgnoreLine
            do_action( 'dokan-vue-admin-scripts' );

            // fire the admin app
            wp_enqueue_script( 'dokan-vue-admin' );
            wp_localize_script( 'dokan-vue-vendor', 'dokanAdmin', $vue_admin_localize_script );

            if ( version_compare( $wp_version, '5.3', '<' ) ) {
                wp_enqueue_style( 'dokan-wp-version-before-5-3' );
            }

            wp_enqueue_style( 'dokan-fontawesome' );

            // load wooCommerce select2 styles
            wp_enqueue_style( 'woocommerce_select2', WC()->plugin_url() . '/assets/css/select2.css', [], WC_VERSION );
        }

        if ( 'dokan_page_dokan-modules' === $hook ) {
            wp_enqueue_style( 'dokan-admin-css' );
            wp_enqueue_script( 'underscore' );
            wp_enqueue_media();
            wp_enqueue_script( 'dokan-tooltip' );
            wp_enqueue_script( 'dokan-admin' );
        }

        if ( get_post_type( $post ) === 'dokan_slider' ) {
            wp_enqueue_script( 'media-upload' );
            wp_enqueue_script( 'thickbox' );
            wp_enqueue_style( 'thickbox' );
        }

        if ( get_post_type( $post ) === 'post' || get_post_type( $post ) === 'page' ) {
            wp_enqueue_script( 'dokan-tooltip' );
            wp_enqueue_script( 'dokan-admin' );
        }

        if ( 'plugins.php' === $hook ) {
            wp_enqueue_style( 'dokan-plugin-list-css' );
        }

        if ( 'product' === $typenow ) {
            wp_enqueue_script( 'dokan-admin-product' );
            wp_enqueue_style( 'dokan-admin-product' );
            wp_localize_script( 'dokan-admin-product', 'dokan_admin_product', $this->admin_product_localize_scripts() );
        }

        do_action( 'dokan_enqueue_admin_scripts', $hook );
    }

    /**
     * Load admin product localize data.
     *
     * @since 3.7.1
     *
     * @return array
     */
    public function admin_product_localize_scripts() {
        return apply_filters(
            'dokan_admin_product_localize_scripts', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'dokan_admin_product' ),
                'i18n'    => [
                    'error_loading'   => esc_html__( 'Could not find any vendor.', 'dokan-lite' ),
                    'searching'       => esc_html__( 'Searching vendors', 'dokan-lite' ),
                    'input_too_short' => esc_html__( 'Search vendors', 'dokan-lite' ),
                    'confirm_delete'  => esc_html__( 'Are you sure ?', 'dokan-lite' ),
                ],
            ]
        );
    }

    public function get_localized_price() {
        return [
            'precision' => wc_get_price_decimals(),
            'symbol'    => html_entity_decode( get_woocommerce_currency_symbol() ),
            'decimal'   => esc_attr( wc_get_price_decimal_separator() ),
            'thousand'  => esc_attr( wc_get_price_thousand_separator() ),
            'position'  => esc_attr( get_option( 'woocommerce_currency_pos' ) ),
            'format'    => esc_attr( str_replace( [ '%1$s', '%2$s' ], [ '%s', '%v' ], get_woocommerce_price_format() ) ), // For accounting JS
        ];
    }

    /**
     * SPA Routes
     *
     * @return array
     */
    public function get_vue_admin_routes() {
        $routes = [
            [
                'path'      => '/',
                'name'      => 'Dashboard',
                'component' => 'Dashboard',
            ],
            [
                'path'      => '/withdraw',
                'name'      => 'Withdraw',
                'component' => 'Withdraw',
            ],
            [
                'path'      => '/reverse-withdrawal',
                'name'      => 'ReverseWithdrawal',
                'component' => 'ReverseWithdrawal',
            ],
            [
                'path'      => '/reverse-withdrawal/store/:store_id',
                'name'      => 'ReverseWithdrawalTransactions',
                'component' => 'ReverseWithdrawalTransactions',
            ],
            [
                'path'      => '/premium',
                'name'      => 'Premium',
                'component' => 'Premium',
            ],
            [
                'path'      => '/help',
                'name'      => 'Help',
                'component' => 'Help',
            ],
            [
                'path'      => '/settings',
                'name'      => 'Settings',
                'component' => 'Settings',
            ],
            [
                'path'      => '/vendors',
                'name'      => 'Vendors',
                'component' => 'Vendors',
            ],
            [
                'path'      => '/vendors/:id',
                'name'      => 'VendorSingle',
                'component' => 'VendorSingle',
            ],
            [
                'path'      => '/dummy-data',
                'name'      => 'DummyData',
                'component' => 'DummyData',
            ],
            [
                'path'      => '/vendor-capabilities',
                'name'      => 'VendorCapabilities',
                'component' => 'VendorCapabilities',
            ],
            [
                'path'      => '/changelog',
                'name'      => 'ChangeLog',
                'component' => 'ChangeLog',
            ],
        ];

        // @codingStandardsIgnoreLine
        return apply_filters( 'dokan-admin-routes', $routes );
    }

    public function get_vue_frontend_routes() {
        $routes = [];

        // @codingStandardsIgnoreLine
        return apply_filters( 'dokan-frontend-routes', $routes );
    }

    /**
     * Register all Dokan scripts and styles
     */
    public function register_all_scripts() {
        $styles  = $this->get_styles();
        $scripts = $this->get_scripts();

        $this->register_styles( $styles );
        $this->register_scripts( $scripts );

        do_action( 'dokan_register_scripts' );
    }

    /**
     * Get registered styles
     *
     * @return array
     */
    public function get_styles() {
        $styles = [
            'dokan-style'                   => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/style.css',
                'version' => filemtime( DOKAN_DIR . '/assets/css/style.css' ),
            ],
            'dokan-tinymce'                 => [
                'src'     => site_url( '/wp-includes/css/editor.css' ),
                'deps'    => [],
                'version' => time(),
            ],
            'jquery-ui'                     => [
                'src' => DOKAN_PLUGIN_ASSEST . '/vendors/jquery-ui/jquery-ui-1.10.0.custom.css',
            ],
            'dokan-fontawesome'             => [
                'src' => DOKAN_PLUGIN_ASSEST . '/vendors/font-awesome/css/font-awesome.min.css',
            ],
            'dokan-modal'                   => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/vendors/izimodal/iziModal.min.css',
                'version' => filemtime( DOKAN_DIR . '/assets/vendors/izimodal/iziModal.min.css' ),
            ],
            'dokan-minitoggle'              => [
                'src' => DOKAN_PLUGIN_ASSEST . '/vendors/minitoggle/minitoggle.css',
            ],
            'dokan-select2-css'             => [
                'src' => DOKAN_PLUGIN_ASSEST . '/vendors/select2/select2.css',
            ],
            'dokan-rtl-style'               => [
                'src' => DOKAN_PLUGIN_ASSEST . '/css/rtl.css',
            ],
            'dokan-plugin-list-css'         => [
                'src' => DOKAN_PLUGIN_ASSEST . '/css/plugin.css',
            ],
            'dokan-timepicker'              => [
                'src' => DOKAN_PLUGIN_ASSEST . '/vendors/jquery-ui/timepicker/timepicker.min.css',
            ],
            'dokan-date-range-picker'       => [
                'src' => DOKAN_PLUGIN_ASSEST . '/vendors/date-range-picker/daterangepicker.min.css',
            ],
            'dokan-admin-css'               => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/admin.css',
                'version' => filemtime( DOKAN_DIR . '/assets/css/admin.css' ),
            ],
            'dokan-vue-vendor'              => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/vue-vendor.css',
                'version' => filemtime( DOKAN_DIR . '/assets/css/vue-vendor.css' ),
            ],
            'dokan-vue-bootstrap'           => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/vue-bootstrap.css',
                'deps'    => [ 'dokan-vue-vendor' ],
                'version' => filemtime( DOKAN_DIR . '/assets/css/vue-bootstrap.css' ),
            ],
            'dokan-sf-pro-text'             => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/font/sf-pro-text/sf-pro-text.css',
                'version' => filemtime( DOKAN_DIR . '/assets/font/sf-pro-text/sf-pro-text.css' ),
            ],
            'dokan-vue-admin'               => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/vue-admin.css',
                'deps'    => [ 'dokan-vue-vendor', 'dokan-vue-bootstrap', 'dokan-tailwind' ],
                'version' => filemtime( DOKAN_DIR . '/assets/css/vue-admin.css' ),
            ],
            'dokan-vue-frontend'            => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/vue-frontend.css',
                'version' => filemtime( DOKAN_DIR . '/assets/css/vue-frontend.css' ),
            ],
            'dokan-wp-version-before-5-3'   => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/wp-version-before-5-3.css',
                'version' => filemtime( DOKAN_DIR . '/assets/css/wp-version-before-5-3.css' ),
            ],
            'dokan-global-admin-css'        => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/global-admin.css',
                'deps'    => [ 'dokan-sf-pro-text' ],
                'version' => filemtime( DOKAN_DIR . '/assets/css/global-admin.css' ),
            ],
            'dokan-product-category-ui-css' => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/dokan-product-category-ui.css',
                'version' => filemtime( DOKAN_DIR . '/assets/css/dokan-product-category-ui.css' ),
            ],
            'dokan-reverse-withdrawal'      => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/reverse-withdrawal-style.css',
                'version' => filemtime( DOKAN_DIR . '/assets/css/reverse-withdrawal-style.css' ),
            ],
            'dokan-admin-product'           => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/dokan-admin-product-style.css',
                'version' => filemtime( DOKAN_DIR . '/assets/css/dokan-admin-product-style.css' ),
            ],
            'dokan-tailwind'                => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/dokan-tailwind.css',
                'version' => filemtime( DOKAN_DIR . '/assets/css/dokan-tailwind.css' ),
            ],
            'dokan-react-frontend'   => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/frontend.css',
                'deps'    => [ 'dokan-react-components' ],
                'version' => filemtime( DOKAN_DIR . '/assets/css/frontend.css' ),
            ],
            'dokan-react-components' => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/css/components.css',
                'deps'    => [ 'wp-components' ],
                'version' => filemtime( DOKAN_DIR . '/assets/css/components.css' ),
            ],
        ];

        return $styles;
    }

    /**
     * Get all registered scripts
     *
     * @return array
     */
    public function get_scripts() {
        global $wp_version;

        $frontend_shipping_asset = require DOKAN_DIR . '/assets/js/frontend.asset.php';

        $suffix         = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
        $asset_url      = DOKAN_PLUGIN_ASSEST;
        $asset_path     = DOKAN_DIR . '/assets/';
        $bootstrap_deps = [ 'dokan-vue-vendor', 'wp-i18n', 'wp-hooks' ];

        $scripts = [
            // Remove `dokan-i18n-jed` in next release.
            'dokan-i18n-jed' => [
                'src'  => $asset_url . '/vendors/i18n/jed.js',
                'deps' => [ 'jquery', 'wp-i18n' ],
            ],
            'dokan-accounting'          => [
                'src'  => WC()->plugin_url() . '/assets/js/accounting/accounting.min.js',
                'deps' => [ 'jquery' ],
            ],
            'dokan-tinymce'             => [
                'src'  => site_url( '/wp-includes/js/tinymce/tinymce.min.js' ),
                'deps' => [],
            ],
            'dokan-tinymce-plugin'      => [
                'src'     => DOKAN_PLUGIN_ASSEST . '/vendors/tinymce/code/plugin.min.js',
                'deps'    => [ 'dokan-tinymce' ],
                'version' => time(),
            ],
            'dokan-chart'               => [
                'src'  => $asset_url . '/vendors/chart/Chart.min.js',
                'deps' => [ 'moment', 'jquery' ],
            ],
            'dokan-tabs'                => [
                'src'  => $asset_url . '/vendors/easytab/jquery.easytabs.min.js',
                'deps' => [ 'jquery' ],
            ],
            'dokan-modal'               => [
                'src'  => $asset_url . '/vendors/izimodal/iziModal.min.js',
                'deps' => [ 'jquery' ],
            ],
            'dokan-minitoggle'          => [
                'src'  => $asset_url . '/vendors/minitoggle/minitoggle.js',
                'deps' => [ 'jquery' ],
            ],
            'dokan-tooltip'             => [
                'src'  => $asset_url . '/vendors/tooltips/tooltips.js',
                'deps' => [ 'jquery' ],
            ],
            'dokan-form-validate'       => [
                'src'  => $asset_url . '/vendors/form-validate/form-validate.js',
                'deps' => [ 'jquery' ],
            ],
            'dokan-select2-js'          => [
                'src'  => $asset_url . '/vendors/select2/select2.full.min.js',
                'deps' => [ 'jquery' ],
            ],
            'dokan-timepicker'          => [
                'src'       => $asset_url . '/vendors/jquery-ui/timepicker/timepicker.min.js',
                'deps'      => [ 'jquery' ],
                'in_footer' => false,
            ],
            'dokan-date-range-picker'   => [
                'src'  => $asset_url . '/vendors/date-range-picker/daterangepicker.min.js',
                'deps' => [ 'jquery', 'moment', 'dokan-util-helper' ],
            ],
            'dokan-google-recaptcha'    => [
                'src'       => 'https://www.google.com/recaptcha/api.js?render=' . dokan_get_option( 'recaptcha_site_key', 'dokan_appearance' ),
                'deps'      => [ 'dokan-util-helper' ],
                'in_footer' => false,
            ],

            // customize scripts
            'customize-base'            => [
                'src'  => site_url( 'wp-includes/js/customize-base.js' ),
                'deps' => [ 'jquery', 'json2', 'underscore' ],
            ],
            'customize-model'           => [
                'src'  => site_url( 'wp-includes/js/customize-models.js' ),
                'deps' => [ 'underscore', 'backbone' ],
            ],

            // Register core scripts
            'dokan-flot-main'           => [
                'src'  => WC()->plugin_url() . '/assets/js/jquery-flot/jquery.flot' . $suffix . '.js',
                'deps' => [ 'jquery' ],
            ],
            'dokan-flot-resize'         => [
                'src'  => WC()->plugin_url() . '/assets/js/jquery-flot/jquery.flot.resize' . $suffix . '.js',
                'deps' => [ 'dokan-flot-main' ],
            ],
            'dokan-flot-time'           => [
                'src'  => WC()->plugin_url() . '/assets/js/jquery-flot/jquery.flot.time' . $suffix . '.js',
                'deps' => [ 'dokan-flot-main' ],
            ],
            'dokan-flot-pie'            => [
                'src'  => WC()->plugin_url() . '/assets/js/jquery-flot/jquery.flot.pie' . $suffix . '.js',
                'deps' => [ 'dokan-flot-main' ],
            ],
            'dokan-flot'                => [
                'src'  => WC()->plugin_url() . '/assets/js/jquery-flot/jquery.flot.stack' . $suffix . '.js',
                'deps' => [ 'dokan-flot-main', 'dokan-flot-pie', 'dokan-flot-time' ],
            ],
            'speaking-url'              => [
                'src'  => $asset_url . '/vendors/speakingurl/speakingurl.min.js',
                'deps' => [ 'jquery' ],
            ],
            'dokan-admin'               => [
                'src'     => $asset_url . '/js/dokan-admin.js',
                'deps'    => [ 'jquery', 'wp-i18n' ],
                'version' => filemtime( $asset_path . 'js/dokan-admin.js' ),
            ],
            'dokan-vendor-registration' => [
                'src'     => $asset_url . '/js/vendor-registration.js',
                'deps'    => [ 'dokan-form-validate', 'jquery', 'speaking-url', 'wp-i18n' ],
                'version' => filemtime( $asset_path . 'js/vendor-registration.js' ),
            ],
            'dokan-script'              => [
                'src'     => $asset_url . '/js/dokan.js',
                'deps'    => [ 'imgareaselect', 'customize-base', 'customize-model', 'wp-i18n', 'jquery-tiptip', 'moment', 'dokan-date-range-picker', 'dokan-accounting' ],
                'version' => filemtime( $asset_path . 'js/dokan.js' ),
            ],
            'dokan-vue-vendor'          => [
                'src'     => $asset_url . '/js/vue-vendor.js',
                'version' => filemtime( $asset_path . 'js/vue-vendor.js' ),
                'deps'    => [ 'wp-i18n', 'dokan-tinymce-plugin', 'dokan-chart' ],
            ],
            'dokan-vue-bootstrap'       => [
                'src'     => $asset_url . '/js/vue-bootstrap.js',
                'deps'    => $bootstrap_deps,
                'version' => filemtime( $asset_path . 'js/vue-bootstrap.js' ),
            ],
            'dokan-vue-admin'           => [
                'src'     => $asset_url . '/js/vue-admin.js',
                'deps'    => [ 'jquery', 'jquery-ui-datepicker', 'wp-i18n', 'dokan-vue-vendor', 'dokan-vue-bootstrap', 'selectWoo' ],
                'version' => filemtime( $asset_path . 'js/vue-admin.js' ),
            ],
            'dokan-vue-frontend'        => [
                'src'     => $asset_url . '/js/vue-frontend.js',
                'deps'    => [ 'jquery', 'wp-i18n', 'dokan-vue-vendor', 'dokan-vue-bootstrap' ],
                'version' => filemtime( $asset_path . 'js/vue-frontend.js' ),
            ],
            'dokan-login-form-popup'    => [
                'src'     => $asset_url . '/js/login-form-popup.js',
                'deps'    => [ 'dokan-modal', 'wp-i18n' ],
                'version' => filemtime( $asset_path . 'js/login-form-popup.js' ),
            ],
            'dokan-sweetalert2'         => [
                'src'     => $asset_url . '/vendors/sweetalert2/sweetalert2.all.min.js',
                'deps'    => [ 'dokan-modal', 'wp-i18n' ],
                'version' => filemtime( $asset_path . 'vendors/sweetalert2/sweetalert2.all.min.js' ),
            ],
            'dokan-util-helper'         => [
                'src'       => $asset_url . '/js/helper.js',
                'deps'      => [ 'jquery', 'dokan-sweetalert2', 'moment' ],
                'version'   => filemtime( $asset_path . 'js/helper.js' ),
                'in_footer' => false,
            ],
            'dokan-promo-notice-js'     => [
                'src'     => $asset_url . '/js/dokan-promo-notice.js',
                'deps'    => [ 'jquery', 'dokan-vue-vendor' ],
                'version' => filemtime( $asset_path . 'js/dokan-promo-notice.js' ),
            ],
            'dokan-admin-notice-js'     => [
                'src'     => $asset_url . '/js/dokan-admin-notice.js',
                'deps'    => [ 'jquery', 'dokan-vue-vendor' ],
                'version' => filemtime( $asset_path . 'js/dokan-admin-notice.js' ),
            ],
            'dokan-reverse-withdrawal'  => [
                'src'     => $asset_url . '/js/reverse-withdrawal.js',
                'deps'    => [ 'jquery', 'dokan-util-helper', 'dokan-vue-vendor', 'dokan-date-range-picker' ],
                'version' => filemtime( $asset_path . 'js/reverse-withdrawal.js' ),
            ],
            'product-category-ui'       => [
                'src'     => $asset_url . '/js/product-category-ui.js',
                'deps'    => [ 'jquery', 'dokan-vue-vendor' ],
                'version' => filemtime( $asset_path . 'js/product-category-ui.js' ),
            ],
            'dokan-vendor-address'      => [
                'src'     => $asset_url . '/js/vendor-address.js',
                'deps'    => [ 'jquery', 'wc-address-i18n' ],
                'version' => filemtime( $asset_path . 'js/vendor-address.js' ),
            ],
            'dokan-admin-product'       => [
                'src'       => $asset_url . '/js/dokan-admin-product.js',
                'deps'      => [ 'jquery', 'dokan-vue-vendor', 'selectWoo' ],
                'version'   => filemtime( $asset_path . 'js/dokan-admin-product.js' ),
                'in_footer' => false,
            ],
            'dokan-frontend'            => [
                'src'     => $asset_url . '/js/dokan-frontend.js',
                'deps'    => [ 'jquery' ],
                'version' => filemtime( $asset_path . 'js/dokan-frontend.js' ),
            ],
            'dokan-react-frontend'      => [
                'src'     => $asset_url . '/js/frontend.js',
                'deps'    => array_merge( $frontend_shipping_asset['dependencies'], [ 'wp-core-data', 'dokan-react-components' ] ),
                'version' => $frontend_shipping_asset['version'],
            ],
            'dokan-utilities'           => [
                'deps'    => [],
                'src'     => $asset_url . '/js/utilities.js',
                'version' => filemtime( $asset_path . 'js/utilities.js' ),
            ],
            'dokan-hooks'               => [
                'deps'    => [],
                'src'     => $asset_url . '/js/react-hooks.js',
                'version' => filemtime( $asset_path . 'js/react-hooks.js' ),
            ],
        ];

        $require_dompurify = version_compare( WC()->version, '10.0.2', '>' );

        if ( $require_dompurify && ! wp_script_is( 'dompurify', 'registered' ) ) {
            $scripts['dompurify'] = [
                'src'  => WC()->plugin_url() . '/assets/js/dompurify/purify' . $suffix . '.js',
                'deps' => [],
            ];
        }

        if ( ! wp_script_is( 'jquery-tiptip', 'registered' ) ) {
            $scripts['jquery-tiptip'] = [
                'src'  => WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js',
                'deps' => $require_dompurify ? [ 'jquery', 'dompurify' ] : [ 'jquery' ],
            ];
        }

        $components_asset_file = DOKAN_DIR . '/assets/js/components.asset.php';
        if ( file_exists( $components_asset_file ) ) {
            $components_asset = require $components_asset_file;

            // Register React components.
            $scripts['dokan-react-components'] = [
                'version' => $components_asset['version'],
                'src'     => $asset_url . '/js/components.js',
                'deps'    => array_merge(
                    $components_asset['dependencies'],
                    [ 'dokan-utilities', 'dokan-hooks' ]
                ),
            ];
        }

        $core_store_asset_file = DOKAN_DIR . '/assets/js/core-store.asset.php';
        if ( file_exists( $core_store_asset_file ) ) {
            $core_store_asset = require $core_store_asset_file;

            // Register React components.
            $scripts['dokan-stores-core'] = [
                'version' => $core_store_asset['version'],
                'src'     => $asset_url . '/js/core-store.js',
                'deps'    => $core_store_asset['dependencies'],
            ];
        }
        $product_store_asset_file = DOKAN_DIR . '/assets/js/products-store.asset.php';
        if ( file_exists( $product_store_asset_file ) ) {
            $stores_asset = require $product_store_asset_file;

            // Register Product stores.
            $scripts['dokan-stores-products'] = [
                'version' => $stores_asset['version'],
                'src'     => $asset_url . '/js/products-store.js',
                'deps'    => $stores_asset['dependencies'],
            ];
        }
        $product_category_asset_file = DOKAN_DIR . '/assets/js/product-categories-store.asset.php';
        if ( file_exists( $product_category_asset_file ) ) {
            $stores_asset = require $product_category_asset_file;

            // Register Product stores.
            $scripts['dokan-stores-product-categories'] = [
                'version' => $stores_asset['version'],
                'src'     => $asset_url . '/js/product-categories-store.js',
                'deps'    => $stores_asset['dependencies'],
            ];
        }

        return $scripts;
    }

    /**
     * Registers WooCommerce Admin scripts for the React-based Dokan Vendor dashboard.
     *
     * This function ensures that the necessary WooCommerce Admin assets are registered
     * for use in the Dokan Vendor dashboard. It temporarily suppresses "doing it wrong"
     * warnings during the registration process.
     *
     * @return void
     */
    public function register_wc_admin_scripts() {
        // Register WooCommerce Admin Assets for the React-base Dokan Vendor ler dashboard.
        if ( ! function_exists( 'get_current_screen' ) ) {
            require_once ABSPATH . '/wp-admin/includes/screen.php';
        }

        add_filter( 'doing_it_wrong_trigger_error', [ $this, 'desable_doing_it_wrong_error' ] );

        $wc_instance = WCAdminAssets::get_instance();
        $wc_instance->register_scripts();

        remove_filter( 'doing_it_wrong_trigger_error', [ $this, 'desable_doing_it_wrong_error' ] );
    }

    /**
     * Disable "doing it wrong" error
     *
     * @return bool
     */
    public function desable_doing_it_wrong_error() {
        return false;
    }

    /**
     * Enqueue front-end scripts
     */
    public function enqueue_front_scripts() {
        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        // load dokan style on every pages. requires for shortcodes in other pages
        if ( DOKAN_LOAD_STYLE ) {
            wp_enqueue_style( 'dokan-style' );
            wp_enqueue_style( 'dokan-modal' );
            if ( 'off' === dokan_get_option( 'disable_dokan_fontawesome', 'dokan_appearance', 'off' ) ) {
                wp_enqueue_style( 'dokan-fontawesome' );
            }

            if ( is_rtl() ) {
                wp_enqueue_style( 'dokan-rtl-style' );
            }
        }

        $vendor = dokan()->vendor->get( dokan_get_current_user_id() );
        $commision_settings = $vendor->get_commission_settings();

        $default_script = [
            'ajaxurl'                      => admin_url( 'admin-ajax.php' ),
            'nonce'                        => wp_create_nonce( 'dokan_reviews' ),
            'order_nonce'                  => wp_create_nonce( 'dokan_view_order' ),
            'product_edit_nonce'           => wp_create_nonce( 'dokan_edit_product_nonce' ),
            'ajax_loader'                  => DOKAN_PLUGIN_ASSEST . '/images/ajax-loader.gif',
            'seller'                       => [
                'available'    => __( 'Available', 'dokan-lite' ),
                'notAvailable' => __( 'Not Available', 'dokan-lite' ),
            ],
            'delete_confirm'               => __( 'Are you sure?', 'dokan-lite' ),
            'wrong_message'                => __( 'Something went wrong. Please try again.', 'dokan-lite' ),
            'vendor_percentage'            => $commision_settings->get_percentage(),
            'commission_type'              => $commision_settings->get_type(),
            'rounding_precision'           => wc_get_rounding_precision(),
            'mon_decimal_point'            => wc_get_price_decimal_separator(),
            'currency_format_num_decimals' => wc_get_price_decimals(),
            'currency_format_symbol'       => get_woocommerce_currency_symbol(),
            'currency_format_decimal_sep'  => esc_attr( wc_get_price_decimal_separator() ),
            'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
            'currency_format'              => esc_attr( str_replace( [ '%1$s', '%2$s' ], [ '%s', '%v' ], get_woocommerce_price_format() ) ), // For accounting JS
            'round_at_subtotal'            => get_option( 'woocommerce_tax_round_at_subtotal', 'no' ),
            'product_types'                => apply_filters( 'dokan_product_types', [ 'simple' ] ),
            'loading_img'                  => DOKAN_PLUGIN_ASSEST . '/images/loading.gif',
            'store_product_search_nonce'   => wp_create_nonce( 'dokan_store_product_search_nonce' ),
            'i18n_download_permission'     => __( 'Are you sure you want to revoke access to this download?', 'dokan-lite' ),
            'i18n_download_access'         => __( 'Could not grant access - the user may already have permission for this file or billing email is not set. Ensure the billing email is set, and the order has been saved.', 'dokan-lite' ),
            /**
             * Filter of maximun a vendor can add tags.
             *
             * @since 3.3.7
             *
             * @param integer default -1
             */
            'maximum_tags_select_length'   => apply_filters( 'dokan_product_tags_select_max_length', - 1 ),  // Filter of maximun a vendor can add tags
            'modal_header_color'           => 'var(--dokan-button-background-color, #7047EB)',
        ];

        $localize_script     = apply_filters( 'dokan_localized_args', $default_script );
        $vue_localize_script = apply_filters(
            'dokan_frontend_localize_script', [
                'rest'            => [
                    'root'    => esc_url_raw( get_rest_url() ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                    'version' => 'dokan/v1',
                ],
                'api'             => null,
                'libs'            => [],
                'routeComponents' => [ 'default' => null ],
                'routes'          => $this->get_vue_frontend_routes(),
                'urls'            => [
                    'assetsUrl'    => DOKAN_PLUGIN_ASSEST,
                    'dashboardUrl' => dokan_get_navigation_url() . ( ReportUtil::is_analytics_enabled() ? '?path=%2Fanalytics%2FOverview' : '' ),
                    'storeUrl'     => dokan_get_store_url( dokan_get_current_user_id() ),
                ],
            ]
        );

        $localize_data = array_merge( $localize_script, $vue_localize_script );

        // Remove `dokan-i18n-jed` in next release.
        wp_localize_script( 'dokan-i18n-jed', 'dokan', $localize_data );
        wp_localize_script( 'dokan-util-helper', 'dokan', $localize_data );
        //        wp_localize_script( 'dokan-vue-bootstrap', 'dokan', $localize_data );
        //        wp_localize_script( 'dokan-script', 'dokan', $localize_data );

        // localized vendor-registration script
        wp_localize_script(
            'dokan-vendor-registration',
            'dokanRegistrationI18n',
            [
                'defaultRole' => dokan_get_seller_registration_default_role(),
            ]
        );

        // load only in dokan dashboard and product edit page
        if ( ( dokan_is_seller_dashboard() || ( get_query_var( 'edit' ) && is_singular( 'product' ) ) ) || apply_filters( 'dokan_forced_load_scripts', false ) ) {
            $this->dokan_dashboard_scripts();
        }

        // Load category ui css in product add, edit and list page.
        global $wp;
        if ( ( dokan_is_seller_dashboard() && isset( $wp->query_vars['products'] ) ) || ( isset( $wp->query_vars['products'], $_GET['product_id'] ) ) || ( dokan_is_seller_dashboard() && isset( $wp->query_vars['new-product'] ) ) ) { // phpcs:ignore
            CategoryHelper::enqueue_and_localize_dokan_multistep_category();
        }

        // store and my account page
        if (
            dokan_is_store_page()
            || dokan_is_store_review_page()
            || is_account_page()
            || is_product()
            || dokan_is_store_listing()
        ) {
            if ( DOKAN_LOAD_STYLE ) {
                wp_enqueue_style( 'dokan-select2-css' );
            }

            if ( DOKAN_LOAD_SCRIPTS ) {
                $this->load_gmap_script();

                wp_enqueue_script( 'jquery-ui-sortable' );
                wp_enqueue_script( 'jquery-ui-datepicker' );
                wp_enqueue_script( 'dokan-tooltip' );
                wp_enqueue_script( 'dokan-form-validate' );
                wp_enqueue_script( 'speaking-url' );
                wp_enqueue_script( 'dokan-script' );
                wp_enqueue_script( 'dokan-select2-js' );
            }
        }

        if ( is_account_page() && ! is_user_logged_in() ) {
            wp_enqueue_script( 'dokan-vendor-registration' );
            wp_enqueue_script( 'dokan-vendor-address' );
        }

        if ( dokan_is_seller_dashboard() && isset( $wp->query_vars['settings'] ) && 'store' === $wp->query_vars['settings'] ) {
            wp_enqueue_script( 'dokan-vendor-address' );
        }

        // Scripts for contact form widget google recaptcha
        if ( dokan_is_store_page() || is_product() ) {
            // Checks if recaptcha site key and secret key exist
            if ( dokan_get_recaptcha_site_and_secret_keys( true ) ) {
                $recaptcha_keys = dokan_get_recaptcha_site_and_secret_keys();

                wp_enqueue_script( 'dokan-google-recaptcha' );

                // Localized script for recaptcha
                wp_localize_script( 'dokan-google-recaptcha', 'dokan_google_recaptcha', [ 'recaptcha_sitekey' => $recaptcha_keys['site_key'] ] );
            }
        }

        // localized form validate script
        self::load_form_validate_script();

        do_action( 'dokan_enqueue_scripts' );
    }

    /**
     * Enqueue Dokan Helper Script
     *
     * @since 3.2.7
     */
    public function load_dokan_global_scripts() {
        // Dokan helper JS file, need to load this file
        wp_enqueue_script( 'dokan-util-helper' );

        $localize_data = apply_filters(
            'dokan_helper_localize_script',
            [
                'i18n_date_format'       => wc_date_format(),
                'i18n_time_format'       => wc_time_format(),
                'week_starts_day'        => intval( get_option( 'start_of_week', 0 ) ),
                'reverse_withdrawal'     => [
                    'enabled' => SettingsHelper::is_enabled(),
                ],
                'timepicker_locale'      => [
                    'am'   => _x( 'am', 'time constant', 'dokan-lite' ),
                    'pm'   => _x( 'pm', 'time constant', 'dokan-lite' ),
                    'AM'   => _x( 'AM', 'time constant', 'dokan-lite' ),
                    'PM'   => _x( 'PM', 'time constant', 'dokan-lite' ),
                    'hr'   => _x( 'hr', 'time constant', 'dokan-lite' ),
                    'hrs'  => _x( 'hrs', 'time constant', 'dokan-lite' ),
                    'mins' => _x( 'mins', 'time constant', 'dokan-lite' ),
                ],
                'daterange_picker_local' => [
                    'toLabel'          => __( 'To', 'dokan-lite' ),
                    'firstDay'         => intval( get_option( 'start_of_week', 0 ) ),
                    'fromLabel'        => __( 'From', 'dokan-lite' ),
                    'separator'        => __( ' - ', 'dokan-lite' ),
                    'weekLabel'        => __( 'W', 'dokan-lite' ),
                    'applyLabel'       => __( 'Apply', 'dokan-lite' ),
                    'cancelLabel'      => __( 'Clear', 'dokan-lite' ),
                    'customRangeLabel' => __( 'Custom', 'dokan-lite' ),
                    'daysOfWeek'       => [
                        __( 'Su', 'dokan-lite' ),
                        __( 'Mo', 'dokan-lite' ),
                        __( 'Tu', 'dokan-lite' ),
                        __( 'We', 'dokan-lite' ),
                        __( 'Th', 'dokan-lite' ),
                        __( 'Fr', 'dokan-lite' ),
                        __( 'Sa', 'dokan-lite' ),
                    ],
                    'monthNames'       => [
                        __( 'January', 'dokan-lite' ),
                        __( 'February', 'dokan-lite' ),
                        __( 'March', 'dokan-lite' ),
                        __( 'April', 'dokan-lite' ),
                        __( 'May', 'dokan-lite' ),
                        __( 'June', 'dokan-lite' ),
                        __( 'July', 'dokan-lite' ),
                        __( 'August', 'dokan-lite' ),
                        __( 'September', 'dokan-lite' ),
                        __( 'October', 'dokan-lite' ),
                        __( 'November', 'dokan-lite' ),
                        __( 'December', 'dokan-lite' ),
                    ],
                ],
                'sweetalert_local'       => [
                    'cancelButtonText'     => __( 'Cancel', 'dokan-lite' ),
                    'closeButtonText'      => __( 'Close', 'dokan-lite' ),
                    'confirmButtonText'    => __( 'OK', 'dokan-lite' ),
                    'denyButtonText'       => __( 'No', 'dokan-lite' ),
                    'closeButtonAriaLabel' => __( 'Close this dialog', 'dokan-lite' ),
                ],
            ]
        );

        wp_localize_script( 'dokan-util-helper', 'dokan_helper', $localize_data );

        $dokan_frontend = [
            'currency' => dokan_get_container()->get( 'scripts' )->get_localized_price(),
        ];

        // localize dokan frontend script
        wp_localize_script(
            'dokan-react-frontend',
            'dokanFrontend',
            apply_filters( 'dokan_react_frontend_localized_args', $dokan_frontend ),
        );
    }

    /**
     * Load form validate script args
     *
     * @since 2.5.3
     */
    public static function load_form_validate_script() {
        $form_validate_messages = [
            'required'        => __( 'This field is required', 'dokan-lite' ),
            'remote'          => __( 'Please fix this field.', 'dokan-lite' ),
            'email'           => __( 'Please enter a valid email address.', 'dokan-lite' ),
            'url'             => __( 'Please enter a valid URL.', 'dokan-lite' ),
            'date'            => __( 'Please enter a valid date.', 'dokan-lite' ),
            'dateISO'         => __( 'Please enter a valid date (ISO).', 'dokan-lite' ),
            'number'          => __( 'Please enter a valid number.', 'dokan-lite' ),
            'digits'          => __( 'Please enter only digits.', 'dokan-lite' ),
            'creditcard'      => __( 'Please enter a valid credit card number.', 'dokan-lite' ),
            'equalTo'         => __( 'Please enter the same value again.', 'dokan-lite' ),
            'maxlength_msg'   => __( 'Please enter no more than {0} characters.', 'dokan-lite' ),
            'minlength_msg'   => __( 'Please enter at least {0} characters.', 'dokan-lite' ),
            'rangelength_msg' => __( 'Please enter a value between {0} and {1} characters long.', 'dokan-lite' ),
            'range_msg'       => __( 'Please enter a value between {0} and {1}.', 'dokan-lite' ),
            'max_msg'         => __( 'Please enter a value less than or equal to {0}.', 'dokan-lite' ),
            'min_msg'         => __( 'Please enter a value greater than or equal to {0}.', 'dokan-lite' ),
        ];

        // @codingStandardsIgnoreLine
        wp_localize_script( 'dokan-form-validate', 'DokanValidateMsg', apply_filters( 'DokanValidateMsg_args', $form_validate_messages ) );
    }

    /**
     * Load Dokan Dashboard Scripts
     *
     * @since 2.5.3
     *
     * @global type $wp
     */
    public function dokan_dashboard_scripts() {
        global $wp;

        if ( DOKAN_LOAD_STYLE ) {
            wp_enqueue_style( 'jquery-ui' );
            wp_enqueue_style( 'woocommerce-general' );
            wp_enqueue_style( 'dokan-select2-css' );

            if (
                isset( $wp->query_vars['products'] ) ||
                isset( $wp->query_vars['withdraw'] ) ||
                isset( $wp->query_vars['withdraw-requests'] ) ||
                isset( $wp->query_vars['products-search'] )
            ) {
                wp_enqueue_style( 'dokan-modal' );
            }

            if (
                isset( $wp->query_vars['products'] ) ||
                isset( $wp->query_vars['orders'] ) ||
                isset( $wp->query_vars['coupons'] ) ||
                isset( $wp->query_vars['reports'] ) ||
                ( isset( $wp->query_vars['settings'] ) && in_array( $wp->query_vars['settings'], [ 'store', 'shipping' ], true ) )
            ) {
                wp_enqueue_style( 'dokan-timepicker' );
                wp_enqueue_style( 'dokan-date-range-picker' );
            }
        }

        if ( DOKAN_LOAD_SCRIPTS ) {
            self::load_form_validate_script();
            $this->load_gmap_script();

            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'jquery-ui' );
            wp_enqueue_script( 'jquery-ui-autocomplete' );
            wp_enqueue_script( 'jquery-ui-datepicker' );
            wp_enqueue_script( 'underscore' );
            wp_enqueue_script( 'post' );
            wp_enqueue_script( 'dokan-date-range-picker' );

            wp_enqueue_script( 'dokan-tooltip' );

            wp_enqueue_script( 'dokan-form-validate' );

            if ( isset( $wp->query_vars['products'] ) || isset( $wp->query_vars['tools'] ) ) {
                wp_enqueue_script( 'dokan-tabs' );
            }

            if (
                isset( $wp->query_vars['page'] ) ||
                isset( $wp->query_vars['reports'] )
            ) {
                wp_enqueue_script( 'dokan-chart' );
                wp_enqueue_script( 'dokan-flot' );
            }

            wp_enqueue_script( 'dokan-select2-js' );
            wp_enqueue_media();
            wp_enqueue_script( 'dokan-accounting' );
            wp_enqueue_script( 'serializejson' );

            if (
                isset( $wp->query_vars['products'] ) ||
                isset( $wp->query_vars['withdraw'] ) ||
                isset( $wp->query_vars['withdraw-requests'] )
            ) {
                wp_enqueue_style( 'dokan-modal' );
                wp_enqueue_script( 'dokan-modal' );
            }

            wp_enqueue_script( 'wc-password-strength-meter' );
            wp_enqueue_script( 'dokan-script' );
        }

        if ( isset( $wp->query_vars['settings'] ) && $wp->query_vars['settings'] === 'store' ) {
            wp_enqueue_script( 'wc-country-select' );
            wp_enqueue_script( 'dokan-timepicker' );
        }
    }

    /**
     * Load google map script
     *
     * @since 2.5.3
     */
    public function load_gmap_script() {
        $script_src = null;
        $source     = dokan_get_option( 'map_api_source', 'dokan_appearance', 'google_maps' );

        if ( 'google_maps' === $source ) {
            $api_key = dokan_get_option( 'gmap_api_key', 'dokan_appearance', false );

            if ( $api_key ) {
                $query_args = apply_filters(
                    'dokan_google_maps_script_query_args', [
                        'key'      => $api_key,
                        'callback' => 'Function.prototype',
                    ]
                );

                $script_src = add_query_arg( $query_args, 'https://maps.googleapis.com/maps/api/js' );

                wp_enqueue_script( 'dokan-maps', $script_src, [], DOKAN_PLUGIN_VERSION, true );
            }
        } elseif ( 'mapbox' === $source ) {
            $access_token = dokan_get_option( 'mapbox_access_token', 'dokan_appearance', null );

            if ( $access_token ) {
                wp_enqueue_style( 'dokan-mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v1.4.1/mapbox-gl.css', [], DOKAN_PLUGIN_VERSION );
                wp_enqueue_style( 'dokan-mapbox-gl-geocoder', 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.2.0/mapbox-gl-geocoder.css', [ 'dokan-mapbox-gl' ], DOKAN_PLUGIN_VERSION );

                wp_enqueue_script( 'dokan-mapbox-gl-geocoder', 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.2.0/mapbox-gl-geocoder.min.js', [], DOKAN_PLUGIN_VERSION, true );
                wp_enqueue_script( 'dokan-maps', 'https://api.mapbox.com/mapbox-gl-js/v1.4.1/mapbox-gl.js', [ 'dokan-mapbox-gl-geocoder' ], DOKAN_PLUGIN_VERSION, true );
            }
        }

        // Backward compatibility script handler
        wp_register_script( 'google-maps', DOKAN_PLUGIN_ASSEST . '/js/dokan-maps-compat.js', [ 'dokan-maps' ], DOKAN_PLUGIN_VERSION, true );
    }

    /**
     * Filter 'dokan' localize script's arguments
     *
     * @since  2.5.3
     *
     * @param array $default_args
     *
     * @return $default_args
     */
    public function conditional_localized_args( $default_args ) {
        if ( dokan_is_seller_dashboard()
            || ( get_query_var( 'edit' ) && is_singular( 'product' ) )
            || dokan_is_store_page()
            || is_account_page()
            || is_product()
            || dokan_is_store_listing()
            || apply_filters( 'dokan_force_load_extra_args', false )
        ) {
            $general_settings = get_option( 'dokan_general', [] );

            $decimal         = wc_get_price_decimal_separator();
            $banner_width    = dokan_get_vendor_store_banner_width();
            $banner_height   = dokan_get_vendor_store_banner_height();
            $has_flex_width  = ! empty( $general_settings['store_banner_flex_width'] ) ? $general_settings['store_banner_flex_width'] : true;
            $has_flex_height = ! empty( $general_settings['store_banner_flex_height'] ) ? $general_settings['store_banner_flex_height'] : true;

            $custom_args = [
                'i18n_choose_featured_img'                 => __( 'Upload featured image', 'dokan-lite' ),
                'i18n_choose_file'                         => __( 'Choose a file', 'dokan-lite' ),
                'i18n_choose_gallery'                      => __( 'Add Images to Product Gallery', 'dokan-lite' ),
                'i18n_choose_featured_img_btn_text'        => __( 'Set featured image', 'dokan-lite' ),
                'i18n_choose_file_btn_text'                => __( 'Insert file URL', 'dokan-lite' ),
                'i18n_choose_gallery_btn_text'             => __( 'Add to gallery', 'dokan-lite' ),
                'duplicates_attribute_messg'               => __( 'Sorry, this attribute option already exists, Try a different one.', 'dokan-lite' ),
                'variation_unset_warning'                  => __( 'Warning! This product will not have any variations if this option is not checked.', 'dokan-lite' ),
                'new_attribute_prompt'                     => __( 'Enter a name for the new attribute term:', 'dokan-lite' ),
                'remove_attribute'                         => __( 'Remove this attribute?', 'dokan-lite' ),
                'dokan_placeholder_img_src'                => wc_placeholder_img_src(),
                'add_variation_nonce'                      => wp_create_nonce( 'add-variation' ),
                'link_variation_nonce'                     => wp_create_nonce( 'link-variations' ),
                'delete_variations_nonce'                  => wp_create_nonce( 'delete-variations' ),
                'load_variations_nonce'                    => wp_create_nonce( 'load-variations' ),
                'save_variations_nonce'                    => wp_create_nonce( 'save-variations' ),
                'bulk_edit_variations_nonce'               => wp_create_nonce( 'bulk-edit-variations' ),
                /* translators: %d: max linked variation. */
                'i18n_link_all_variations'                 => esc_js( sprintf( __( 'Are you sure you want to link all variations? This will create a new variation for each and every possible combination of variation attributes (max %d per run).', 'dokan-lite' ), defined( 'WC_MAX_LINKED_VARIATIONS' ) ? WC_MAX_LINKED_VARIATIONS : 50 ) ),
                'i18n_enter_a_value'                       => esc_js( __( 'Enter a value', 'dokan-lite' ) ),
                'i18n_enter_menu_order'                    => esc_js( __( 'Variation menu order (determines position in the list of variations)', 'dokan-lite' ) ),
                'i18n_enter_a_value_fixed_or_percent'      => esc_js( __( 'Enter a value (fixed or %)', 'dokan-lite' ) ),
                'i18n_delete_all_variations'               => esc_js( __( 'Are you sure you want to delete all variations? This cannot be undone.', 'dokan-lite' ) ),
                'i18n_last_warning'                        => esc_js( __( 'Last warning, are you sure?', 'dokan-lite' ) ),
                'i18n_choose_image'                        => esc_js( __( 'Choose an image', 'dokan-lite' ) ),
                'i18n_set_image'                           => esc_js( __( 'Set variation image', 'dokan-lite' ) ),
                'i18n_variation_added'                     => esc_js( __( 'variation added', 'dokan-lite' ) ),
                'i18n_variations_added'                    => esc_js( __( 'variations added', 'dokan-lite' ) ),
                'i18n_no_variations_added'                 => esc_js( __( 'No variations added', 'dokan-lite' ) ),
                'i18n_remove_variation'                    => esc_js( __( 'Are you sure you want to remove this variation?', 'dokan-lite' ) ),
                'i18n_scheduled_sale_start'                => esc_js( __( 'Sale start date (YYYY-MM-DD format or leave blank)', 'dokan-lite' ) ),
                'i18n_scheduled_sale_end'                  => esc_js( __( 'Sale end date (YYYY-MM-DD format or leave blank)', 'dokan-lite' ) ),
                'i18n_edited_variations'                   => esc_js( __( 'Save changes before changing page?', 'dokan-lite' ) ),
                'i18n_variation_count_single'              => esc_js( __( '%qty% variation', 'dokan-lite' ) ),
                'i18n_variation_count_plural'              => esc_js( __( '%qty% variations', 'dokan-lite' ) ),
                'i18n_no_result_found'                     => esc_js( __( 'No Result Found', 'dokan-lite' ) ),
                'i18n_sales_price_error'                   => esc_js( __( 'Please insert value less than the regular price!', 'dokan-lite' ) ),
                /* translators: %s: decimal */
                'i18n_decimal_error'                       => sprintf( __( 'Please enter with one decimal point (%s) without thousand separators.', 'dokan-lite' ), $decimal ),
                /* translators: %s: price decimal separator */
                'i18n_mon_decimal_error'                   => sprintf( __( 'Please enter with one monetary decimal point (%s) without thousand separators and currency symbols.', 'dokan-lite' ), wc_get_price_decimal_separator() ),
                'i18n_country_iso_error'                   => __( 'Please enter in country code with two capital letters.', 'dokan-lite' ),
                'i18n_sale_less_than_regular_error'        => __( 'Please enter in a value less than the regular price.', 'dokan-lite' ),
                'i18n_delete_product_notice'               => __( 'This product has produced sales and may be linked to existing orders. Are you sure you want to delete it?', 'dokan-lite' ),
                'i18n_remove_personal_data_notice'         => __( 'This action cannot be reversed. Are you sure you wish to erase personal data from the selected orders?', 'dokan-lite' ),
                'decimal_point'                            => $decimal,
                'mon_decimal_point'                        => wc_get_price_decimal_separator(),
                'variations_per_page'                      => absint( apply_filters( 'dokan_product_variations_per_page', 10 ) ),
                'store_banner_dimension'                   => [
                    'width'       => $banner_width,
                    'height'      => $banner_height,
                    'flex-width'  => $has_flex_width,
                    'flex-height' => $has_flex_height,
                ],
                'selectAndCrop'                            => __( 'Select and Crop', 'dokan-lite' ),
                'chooseImage'                              => __( 'Choose Image', 'dokan-lite' ),
                'product_title_required'                   => __( 'Product title is required', 'dokan-lite' ),
                'product_category_required'                => __( 'Product category is required', 'dokan-lite' ),
                'product_created_response'                 => __( 'Product created successfully', 'dokan-lite' ),
                'search_products_nonce'                    => wp_create_nonce( 'search-products' ),
                'search_products_tags_nonce'               => wp_create_nonce( 'search-products-tags' ),
                'search_products_brands_nonce'             => wp_create_nonce( 'search-products-brands' ),
                'search_customer_nonce'                    => wp_create_nonce( 'search-customer' ),
                'i18n_matches_1'                           => __( 'One result is available, press enter to select it.', 'dokan-lite' ),
                'i18n_matches_n'                           => __( '%qty% results are available, use up and down arrow keys to navigate.', 'dokan-lite' ),
                'i18n_no_matches'                          => __( 'No matches found', 'dokan-lite' ),
                'i18n_ajax_error'                          => __( 'Loading failed', 'dokan-lite' ),
                'i18n_input_too_short_1'                   => __( 'Please enter 1 or more characters', 'dokan-lite' ),
                'i18n_input_too_short_n'                   => __( 'Please enter %qty% or more characters', 'dokan-lite' ),
                'i18n_input_too_long_1'                    => __( 'Please delete 1 character', 'dokan-lite' ),
                'i18n_input_too_long_n'                    => __( 'Please delete %qty% characters', 'dokan-lite' ),
                'i18n_selection_too_long_1'                => __( 'You can only select 1 item', 'dokan-lite' ),
                'i18n_selection_too_long_n'                => __( 'You can only select %qty% items', 'dokan-lite' ),
                'i18n_load_more'                           => __( 'Loading more results&hellip;', 'dokan-lite' ),
                'i18n_searching'                           => __( 'Searching&hellip;', 'dokan-lite' ),
                'i18n_calculating'                         => __( 'Calculating', 'dokan-lite' ),
                'i18n_ok_text'                             => __( 'OK', 'dokan-lite' ),
                'i18n_cancel_text'                         => __( 'Cancel', 'dokan-lite' ),
                'i18n_attribute_label'                     => __( 'Attribute Name', 'dokan-lite' ),
                'i18n_date_format'                         => get_option( 'date_format' ),
                'dokan_banner_added_alert_msg'             => __( 'Are you sure? You have uploaded banner but didn\'t click the Update Settings button!', 'dokan-lite' ),
                'update_settings'                          => __( 'Update Settings', 'dokan-lite' ),
                'search_downloadable_products_nonce'       => wp_create_nonce( 'search-downloadable-products' ),
                'search_downloadable_products_placeholder' => __( 'Please enter 3 or more characters', 'dokan-lite' ),
            ];

            $default_args = array_merge( $default_args, $custom_args );
        }

        return $default_args;
    }

    /**
     * Get file prefix
     *
     * @return string
     */
    public function get_prefix() {
        $prefix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

        return $prefix;
    }

    /**
     * Register scripts
     *
     * @param array $scripts
     *
     * @return void
     */
    public function register_scripts( $scripts ) {
        foreach ( $scripts as $handle => $script ) {
            $deps      = isset( $script['deps'] ) ? $script['deps'] : false;
            $in_footer = isset( $script['in_footer'] ) ? $script['in_footer'] : true;
            $version   = isset( $script['version'] ) ? $script['version'] : DOKAN_PLUGIN_VERSION;

            wp_register_script( $handle, $script['src'], $deps, $version, $in_footer );
            wp_set_script_translations( $handle, 'dokan-lite', plugin_dir_path( DOKAN_FILE ) . 'languages' );
        }
    }

    /**
     * Register styles
     *
     * @param array $styles
     *
     * @return void
     */
    public function register_styles( $styles ) {
        foreach ( $styles as $handle => $style ) {
            $deps    = isset( $style['deps'] ) ? $style['deps'] : false;
            $version = isset( $style['version'] ) ? $style['version'] : DOKAN_PLUGIN_VERSION;

            wp_register_style( $handle, $style['src'], $deps, $version );
        }
    }

    /**
     * Enqueue the scripts
     *
     * @param array $scripts
     *
     * @return void
     */
    public function enqueue_scripts( $scripts ) {
        foreach ( $scripts as $handle => $script ) {
            wp_enqueue_script( $handle );
        }
    }

    /**
     * Enqueue styles
     *
     * @param array $styles
     *
     * @return void
     */
    public function enqueue_styles( $styles ) {
        foreach ( $styles as $handle => $script ) {
            wp_enqueue_style( $handle );
        }
    }

    /**
     * Admin localized scripts
     *
     * @since 3.0.0
     *
     * @return array
     */
    public function get_admin_localized_scripts() {
        $general_settings = get_option( 'dokan_general', [] );
        $banner_width     = dokan_get_option( 'store_banner_width', 'dokan_appearance', 625 );
        $banner_height    = dokan_get_option( 'store_banner_height', 'dokan_appearance', 300 );
        $has_flex_width   = ! empty( $general_settings['store_banner_flex_width'] ) ? $general_settings['store_banner_flex_width'] : true;
        $has_flex_height  = ! empty( $general_settings['store_banner_flex_height'] ) ? $general_settings['store_banner_flex_height'] : true;
        $decimal          = wc_get_price_decimal_separator();

        return apply_filters(
            'dokan_admin_localize_script', [
                'ajaxurl'                           => admin_url( 'admin-ajax.php' ),
                'nonce'                             => wp_create_nonce( 'dokan_admin' ),
                'rest'                              => [
                    'root'    => esc_url_raw( get_rest_url() ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                    'version' => 'dokan/v1',
                ],
                'api'                               => null,
                'libs'                              => [],
                'routeComponents'                   => [ 'default' => null ],
                'routes'                            => $this->get_vue_admin_routes(),
                'currency'                          => $this->get_localized_price(),
                'proNag'                            => dokan()->is_pro_exists() ? 'hide' : get_option( 'dokan_hide_pro_nag', 'show' ),
                'hasPro'                            => dokan()->is_pro_exists(),
                'showPromoBanner'                   => empty( Helper::dokan_get_promo_notices() ),
                'hasNewVersion'                     => Helper::dokan_has_new_version(),
                'proVersion'                        => dokan()->is_pro_exists() ? dokan_pro()->version : '',
                'urls'                              => [
                    'adminRoot'         => admin_url(),
                    'siteUrl'           => home_url( '/' ),
                    'storePrefix'       => dokan_get_option( 'custom_store_url', 'dokan_general', 'store' ),
                    'assetsUrl'         => DOKAN_PLUGIN_ASSEST,
                    'buynowpro'         => dokan_pro_buynow_url(),
                    'upgradeToPro'      => 'https://dokan.co/wordpress/upgrade-to-pro/?utm_source=plugin&utm_medium=wp-admin&utm_campaign=dokan-lite',
                    'dummy_data'        => DOKAN_PLUGIN_ASSEST . '/dummy-data/dokan_dummy_data.csv',
                    'adminOrderListUrl' => OrderUtil::get_admin_order_list_url(),
                    'adminOrderEditUrl' => OrderUtil::get_admin_order_edit_url(),
                ],
                'states'                            => WC()->countries->get_allowed_country_states(),
                'countries'                         => WC()->countries->get_allowed_countries(),
                'current_time'                      => current_time( 'mysql' ),
                'store_banner_dimension'            => [
                    'width'       => $banner_width,
                    'height'      => $banner_height,
                    'flex-width'  => $has_flex_width,
                    'flex-height' => $has_flex_height,
                ],
                'ajax_loader'                       => DOKAN_PLUGIN_ASSEST . '/images/spinner-2x.gif',
                /* translators: %s: decimal */
                'i18n_decimal_error'                => sprintf( __( 'Please enter with one decimal point (%s) without thousand separators.', 'dokan-lite' ), $decimal ),
                /* translators: %s: price decimal separator */
                'i18n_mon_decimal_error'            => sprintf( __( 'Please enter with one monetary decimal point (%s) without thousand separators and currency symbols.', 'dokan-lite' ), wc_get_price_decimal_separator() ),
                'i18n_country_iso_error'            => __( 'Please enter in country code with two capital letters.', 'dokan-lite' ),
                'i18n_sale_less_than_regular_error' => __( 'Please enter in a value less than the regular price.', 'dokan-lite' ),
                'i18n_delete_product_notice'        => __( 'This product has produced sales and may be linked to existing orders. Are you sure you want to delete it?', 'dokan-lite' ),
                'i18n_remove_personal_data_notice'  => __( 'This action cannot be reversed. Are you sure you wish to erase personal data from the selected orders?', 'dokan-lite' ),
                'decimal_point'                     => $decimal,
                'mon_decimal_point'                 => wc_get_price_decimal_separator(),
                'i18n_date_format'                  => wc_date_format(),
            ]
        );
    }

    /**
     * Admin vue localized scripts
     *
     * @since 3.14.0
     *
     * @return array
     */
    private function get_vue_admin_localized_scripts() {
        return apply_filters(
            'dokan_vue_admin_localize_script', [
                'commission_types' => dokan_commission_types(),
            ]
        );
    }
}
