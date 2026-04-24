<?php

namespace WPSocialReviews\App\Hooks\CLI;

class Commands
{
    /**
     * Install WP Social Ninja Pro from a ZIP URL or local ZIP path.
     *
     * ## OPTIONS
     *
     * <package>
     * : The Pro plugin ZIP URL or local ZIP path.
     *
     * [--activate]
     * : Activate the Pro plugin after installation.
     *
     * [--force]
     * : Overwrite the plugin if it is already installed.
     *
     * ## EXAMPLES
     *
     *     wp wpsocialninja install_pro https://example.com/wp-social-ninja-pro.zip --activate --force
     */
    public function install_pro($args, $assoc_args)
    {
        if (empty($args[0])) {
            \WP_CLI::error(__('Please provide the WP Social Ninja Pro ZIP URL or path.', 'wp-social-reviews'));
            return;
        }

        $package = trim($args[0]);

        if (!$this->isValidPackage($package)) {
            \WP_CLI::error(__('Please provide a valid ZIP URL or local ZIP file path.', 'wp-social-reviews'));
            return;
        }

        $command = 'plugin install ' . escapeshellarg($package);

        if (!empty($assoc_args['activate'])) {
            $command .= ' --activate';
        }

        if (!empty($assoc_args['force'])) {
            $command .= ' --force';
        }

        \WP_CLI::runcommand($command, [
            'launch' => false,
        ]);
    }

    /**
     * Activate the WP Social Ninja Pro license.
     *
     * ## OPTIONS
     *
     * --key=<license-key>
     * : The Pro license key.
     *
     * ## EXAMPLES
     *
     *     wp wpsocialninja activate_license --key=LICENSE_KEY
     */
    public function activate_license($args, $assoc_args)
    {
        if (!$this->isProAvailable()) {
            \WP_CLI::line(__('WP Social Ninja Pro is not available.', 'wp-social-reviews'));
            return;
        }

        if (empty($assoc_args['key'])) {
            \WP_CLI::line(__('Use --key=LICENSE_KEY to activate the license.', 'wp-social-reviews'));
            return;
        }

        $licenseKey = trim(sanitize_text_field($assoc_args['key']));

        if (!class_exists('\WPSocialReviewsPro\App\Services\Libs\PluginManager\FluentLicensing')) {
            \WP_CLI::line('WP Social Ninja Pro is required');
            return;
        }

        \WP_CLI::line(__('Validating license, please wait...', 'wp-social-reviews'));

        $response = apply_filters('wpsr_activate_license', false, $this->request([
            'license_key' => $licenseKey,
        ]));

        if (is_wp_error($response)) {
            \WP_CLI::error($response->get_error_message());
            return;
        }

        if (!$response || empty($response['license_data'])) {
            \WP_CLI::error(__('License could not be activated. Please make sure WP Social Ninja Pro is up to date.', 'wp-social-reviews'));
            return;
        }

        $licenseData = $response['license_data'];

        \WP_CLI::success(__('Your license key has been successfully updated.', 'wp-social-reviews'));
        \WP_CLI::line(__('Your License Status: ', 'wp-social-reviews') . $this->value($licenseData, 'status'));
        \WP_CLI::line(__('Expire Date: ', 'wp-social-reviews') . $this->value($licenseData, 'expires'));
    }

    /**
     * Show the WP Social Ninja Pro license status.
     *
     * ## OPTIONS
     *
     * [--remote]
     * : Verify the license against the remote license server.
     *
     * ## EXAMPLES
     *
     *     wp wpsocialninja license_status --remote
     */
    public function license_status($args = [], $assoc_args = [])
    {
        if (!$this->isProAvailable()) {
            \WP_CLI::line(__('WP Social Ninja Pro is not available.', 'wp-social-reviews'));
            return;
        }

        if (!class_exists('\WPSocialReviewsPro\App\Services\Libs\PluginManager\FluentLicensing')) {
            \WP_CLI::line('WP Social Ninja Pro is required');
            return;
        }

        \WP_CLI::line(__('Fetching license details, please wait...', 'wp-social-reviews'));

        $response = apply_filters('wpsr_get_license', false, $this->request([
            'verify' => !empty($assoc_args['remote']),
        ]));

        if (is_wp_error($response)) {
            \WP_CLI::error($response->get_error_message());
            return;
        }

        if (!$response) {
            \WP_CLI::error(__('License key has not been set.', 'wp-social-reviews'));
            return;
        }

        \WP_CLI::line(__('Your License Status: ', 'wp-social-reviews') . $this->value($response, 'status'));
        \WP_CLI::line(__('Expires: ', 'wp-social-reviews') . $this->value($response, 'expires'));
    }

    /**
     * Deactivate the WP Social Ninja Pro license.
     *
     * ## EXAMPLES
     *
     *     wp wpsocialninja deactivate_license
     */
    public function deactivate_license()
    {
        if (!$this->isProAvailable()) {
            \WP_CLI::line(__('WP Social Ninja Pro is not available.', 'wp-social-reviews'));
            return;
        }

        if (!class_exists('\WPSocialReviewsPro\App\Services\Libs\PluginManager\FluentLicensing')) {
            \WP_CLI::line('WP Social Ninja Pro is required');
            return;
        }

        \WP_CLI::line(__('Deactivating license, please wait...', 'wp-social-reviews'));

        $response = apply_filters('wpsr_deactivate_license', false, $this->request());

        if (is_wp_error($response)) {
            \WP_CLI::error($response->get_error_message());
            return;
        }

        if (!$response) {
            \WP_CLI::error(__('License could not be deactivated. Please make sure WP Social Ninja Pro is up to date.', 'wp-social-reviews'));
            return;
        }

        \WP_CLI::success(__('Your license key has been successfully deactivated.', 'wp-social-reviews'));
    }

    private function isProAvailable()
    {
        return defined('WPSOCIALREVIEWS_PRO') && WPSOCIALREVIEWS_PRO;
    }

    private function isValidPackage($package)
    {
        if (filter_var($package, FILTER_VALIDATE_URL)) {
            return (bool) wp_http_validate_url($package);
        }

        return file_exists($package) && is_file($package) && preg_match('/\.zip$/i', $package);
    }

    private function request($data = [])
    {
        return new class($data) {
            private $data = [];

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function get($key = null, $default = null)
            {
                if ($key === null) {
                    return $this->data;
                }

                return isset($this->data[$key]) ? $this->data[$key] : $default;
            }
        };
    }

    private function value($data, $key)
    {
        if (is_array($data) && isset($data[$key])) {
            return $data[$key];
        }

        if (is_object($data) && isset($data->{$key})) {
            return $data->{$key};
        }

        return __('N/A', 'wp-social-reviews');
    }
}
