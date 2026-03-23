<?php
/**
 * LicenceFlow — GitHub Auto-Updater
 *
 * Hooks into the WordPress plugin update system to check for new releases
 * on the public GitHub repository github.com/tedisun/licenceflow.
 *
 * How it works:
 *  1. Every 12 hours WordPress checks for plugin updates.
 *  2. This class intercepts that check and calls the GitHub Releases API.
 *  3. If a newer tag exists (e.g. v1.1.0 > 1.0.0), WordPress shows the
 *     update notice in Tableau de bord > Mises à jour.
 *  4. The admin clicks "Mettre à jour" — WordPress downloads the ZIP from
 *     GitHub and installs it automatically.
 *
 * Publish a new release:
 *  1. Bump LFLOW_VERSION in licenceflow.php (e.g. '1.0.1').
 *  2. Push to GitHub.
 *  3. Create a GitHub Release with tag v1.0.1 (the GitHub Action handles the ZIP).
 *  4. WordPress sites pick it up within 12 hours (or force-check via admin).
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_Updater {

    /** GitHub repository owner */
    const GITHUB_USER = 'tedisun';

    /** GitHub repository name */
    const GITHUB_REPO = 'licenceflow';

    /** WordPress plugin slug (folder name) */
    const PLUGIN_SLUG = 'licenceflow';

    /** Plugin basename: folder/main-file.php */
    const PLUGIN_BASENAME = 'licenceflow/licenceflow.php';

    /** Transient key for caching the GitHub API response */
    const TRANSIENT_KEY = 'lflow_update_data';

    /** Cache duration in seconds (12 hours) */
    const CACHE_TTL = 43200;

    /** @var self|null */
    private static $instance = null;

    private function __construct() {
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api',                           array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_source_selection',             array( $this, 'fix_source_dir' ), 10, 4 );
        add_action( 'upgrader_process_complete',             array( $this, 'clear_cache' ), 10, 2 );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Core update check ─────────────────────────────────────────────────────

    /**
     * Called by WordPress when it checks for plugin updates.
     * Injects LicenceFlow update info into the transient if a newer version exists.
     *
     * @param  object $transient  The update_plugins transient.
     * @return object
     */
    public function check_for_update( object $transient ): object {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $latest_version = $this->parse_version( $release->tag_name );

        if ( version_compare( $latest_version, LFLOW_VERSION, '>' ) ) {
            $transient->response[ self::PLUGIN_BASENAME ] = $this->build_update_object( $release, $latest_version );
        } else {
            // Tell WordPress the plugin is up to date (prevents false positives)
            $transient->no_update[ self::PLUGIN_BASENAME ] = $this->build_no_update_object( $latest_version );
        }

        return $transient;
    }

    /**
     * Provides plugin info for the "View details" modal in WP admin.
     */
    public function plugin_info( $result, string $action, object $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }
        if ( ! isset( $args->slug ) || $args->slug !== self::PLUGIN_SLUG ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $latest_version = $this->parse_version( $release->tag_name );

        $info                = new stdClass();
        $info->name          = 'LicenceFlow';
        $info->slug          = self::PLUGIN_SLUG;
        $info->version       = $latest_version;
        $info->author        = '<a href="https://tedisun.com">Tedisun SARL</a>';
        $info->homepage      = 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO;
        $info->requires      = '5.8';
        $info->requires_php  = '7.4';
        $info->last_updated  = $release->published_at ?? '';
        $info->download_link = $this->get_download_url( $release );
        $info->sections      = array(
            'description' => 'Digital license & subscription delivery for WooCommerce. Sell keys, accounts, invitation links and access codes — automatically delivered on purchase.',
            'changelog'   => $this->format_changelog( $release->body ?? '' ),
        );

        return $info;
    }

    /**
     * Fix the extracted folder name after download.
     *
     * GitHub generates ZIPs with the folder named "licenceflow-1.0.1" (tag-based),
     * but WordPress expects "licenceflow". This filter renames it.
     */
    public function fix_source_dir( string $source, string $remote_source, $upgrader, array $hook_extra = array() ): string {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::PLUGIN_BASENAME ) {
            return $source;
        }

        $expected = trailingslashit( $remote_source ) . self::PLUGIN_SLUG . '/';

        // If the source already has the right name, nothing to do
        if ( $source === $expected ) {
            return $source;
        }

        // Rename the extracted folder to the expected name
        if ( $wp_filesystem->move( $source, $expected ) ) {
            return $expected;
        }

        return $source;
    }

    /**
     * Clear the cached release data after a successful update.
     */
    public function clear_cache( WP_Upgrader $upgrader, array $hook_extra ): void {
        if (
            isset( $hook_extra['action'], $hook_extra['type'], $hook_extra['plugins'] ) &&
            $hook_extra['action'] === 'update' &&
            $hook_extra['type'] === 'plugin' &&
            in_array( self::PLUGIN_BASENAME, $hook_extra['plugins'], true )
        ) {
            delete_transient( self::TRANSIENT_KEY );
        }
    }

    // ── GitHub API ────────────────────────────────────────────────────────────

    /**
     * Fetch the latest release from GitHub API.
     * Result is cached in a transient for CACHE_TTL seconds.
     *
     * @return object|null  Decoded GitHub release object, or null on failure.
     */
    private function get_latest_release(): ?object {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( false !== $cached ) {
            return $cached ?: null;
        }

        $url      = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_USER, self::GITHUB_REPO );
        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'LicenceFlow-Updater/' . LFLOW_VERSION . ' (WordPress/' . get_bloginfo( 'version' ) . ')',
            ),
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            // Cache the failure briefly (5 min) to avoid hammering the API
            set_transient( self::TRANSIENT_KEY, false, 300 );
            return null;
        }

        $body    = wp_remote_retrieve_body( $response );
        $release = json_decode( $body );

        if ( empty( $release->tag_name ) ) {
            set_transient( self::TRANSIENT_KEY, false, 300 );
            return null;
        }

        set_transient( self::TRANSIENT_KEY, $release, self::CACHE_TTL );
        return $release;
    }

    /**
     * Determine the best download URL for the release.
     *
     * Prefers an asset named "licenceflow.zip" attached to the release (correct folder structure).
     * Falls back to the GitHub-generated source archive (requires folder rename).
     */
    private function get_download_url( object $release ): string {
        // Look for a manually attached asset named "licenceflow.zip"
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( $asset->name === 'licenceflow.zip' && ! empty( $asset->browser_download_url ) ) {
                    return $asset->browser_download_url;
                }
            }
        }

        // Fallback: GitHub source code ZIP (folder rename handled by fix_source_dir)
        return sprintf(
            'https://github.com/%s/%s/archive/refs/tags/%s.zip',
            self::GITHUB_USER,
            self::GITHUB_REPO,
            $release->tag_name
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Strip leading "v" from a tag name: "v1.2.0" → "1.2.0".
     */
    private function parse_version( string $tag ): string {
        return ltrim( $tag, 'vV' );
    }

    /**
     * Build the update object WordPress expects in the transient.
     */
    private function build_update_object( object $release, string $version ): object {
        return (object) array(
            'id'            => self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'slug'          => self::PLUGIN_SLUG,
            'plugin'        => self::PLUGIN_BASENAME,
            'new_version'   => $version,
            'url'           => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'package'       => $this->get_download_url( $release ),
            'icons'         => array(),
            'banners'       => array(),
            'banners_rtl'   => array(),
            'requires'      => '5.8',
            'tested'        => get_bloginfo( 'version' ),
            'requires_php'  => '7.4',
            'compatibility' => new stdClass(),
        );
    }

    /**
     * Build the "no update needed" object WordPress expects.
     */
    private function build_no_update_object( string $version ): object {
        return (object) array(
            'id'            => self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'slug'          => self::PLUGIN_SLUG,
            'plugin'        => self::PLUGIN_BASENAME,
            'new_version'   => $version,
            'url'           => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'package'       => '',
            'icons'         => array(),
            'banners'       => array(),
            'requires'      => '5.8',
            'requires_php'  => '7.4',
        );
    }

    /**
     * Format the GitHub release body (Markdown) as simple HTML for the changelog section.
     */
    private function format_changelog( string $body ): string {
        if ( empty( $body ) ) {
            return '<p>' . esc_html__( 'Voir les notes de version sur GitHub.', 'licenceflow' ) . '</p>';
        }
        // Basic Markdown → HTML (headers, bullets, bold)
        $body = esc_html( $body );
        $body = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $body );
        $body = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $body );
        $body = preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $body );
        $body = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $body );
        $body = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $body );
        $body = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $body );
        $body = nl2br( $body );
        return $body;
    }

    // ── Admin: force check button ──────────────────────────────────────────────

    /**
     * Register the "force check" feature (called from settings page).
     * Clears the transient so the next WP update check hits GitHub fresh.
     */
    public static function force_check(): void {
        delete_transient( self::TRANSIENT_KEY );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();
    }

    /**
     * Fetch a fresh update status from GitHub and return structured data.
     * Used by the AJAX "check for update" button in the settings page.
     *
     * @return array {
     *   bool   error       True if GitHub was unreachable.
     *   string current     Installed version.
     *   string latest      Latest GitHub version.
     *   bool   has_update  True if latest > current.
     *   string update_url  WP upgrade URL (only when has_update).
     *   string changelog_url GitHub release URL (only when has_update).
     * }
     */
    public function fetch_update_status(): array {
        // Force a fresh GitHub call
        delete_transient( self::TRANSIENT_KEY );

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return array( 'error' => true, 'message' => __( 'Impossible de contacter GitHub. Vérifiez votre connexion et réessayez.', 'licenceflow' ) );
        }

        $latest     = $this->parse_version( $release->tag_name );
        $current    = LFLOW_VERSION;
        $has_update = version_compare( $latest, $current, '>' );

        $result = array(
            'error'      => false,
            'current'    => $current,
            'latest'     => $latest,
            'has_update' => $has_update,
        );

        if ( $has_update ) {
            // Register in the WP update_plugins transient so the upgrade URL works immediately
            $site_transient = get_site_transient( 'update_plugins' );
            if ( ! is_object( $site_transient ) ) {
                $site_transient = new stdClass();
            }
            if ( ! isset( $site_transient->checked ) ) {
                $site_transient->checked = array();
            }
            $site_transient->checked[ self::PLUGIN_BASENAME ]  = $current;
            $site_transient->response[ self::PLUGIN_BASENAME ] = $this->build_update_object( $release, $latest );
            set_site_transient( 'update_plugins', $site_transient );

            $result['update_url']    = wp_nonce_url(
                admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( self::PLUGIN_BASENAME ) ),
                'upgrade-plugin_' . self::PLUGIN_BASENAME
            );
            $result['changelog_url'] = 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/releases/tag/' . $release->tag_name;
        }

        return $result;
    }
}
