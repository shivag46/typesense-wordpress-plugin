<?php
/**
 * Typesense_Admin class file.
 *
 * @author  WebDevStudios <contact@webdevstudios.com>
 * @since   1.0.0
 *
 * @package WebDevStudios\WPSWA
 */

/**
 * Class Typesense_Admin
 *
 * @since 1.0.0
 */

use Typesense\Client; 
class Typesense_Admin {

	/**
	 * The Typesense Plugin.
	 *
	 * @since   1.0.0
	 *
	 * @var Typesense_Plugin
	 */
	private $plugin;

	/**
	 * Typesense_Admin constructor.
	 *
	 * @author WebDevStudios <contact@webdevstudios.com>
	 * @since  1.0.0
	 *
	 * @param Typesense_Plugin $plugin The Typesense Plugin.
	 */
	public function __construct( Typesense_Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		$api = $plugin->get_api();
		//if ( $api->is_reachable() ) {
			new Typesense_Admin_Page_Autocomplete( $plugin->get_settings());
			new Typesense_Admin_Page_Native_Search( $plugin );

			add_action( 'wp_ajax_typesense_re_index', array( $this, 're_index' ) );
			add_action( 'wp_ajax_typesense_push_settings', array( $this, 'push_settings' ) );

			//$maybe_get_page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
			//if ( ! empty( $maybe_get_page ) && 'typesense' === substr( $maybe_get_page, 0, 7 ) ) {
			//	add_action( 'admin_notices', array( $this, 'display_reindexing_notices' ) );
			//}
		//}

		new Typesense_Admin_Page_Settings( $plugin );

		add_action( 'admin_notices', array( $this, 'display_unmet_requirements_notices' ) );
	}

	/**
	 * Enqueue styles.
	 *
	 * @author  WebDevStudios <contact@webdevstudios.com>
	 * @since   1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'typesense-admin', plugin_dir_url( __FILE__ ) . 'css/typesense-admin.css', array(), TYPESENSE_VERSION );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @author  WebDevStudios <contact@webdevstudios.com>
	 * @since   1.0.0
	 */
	public function enqueue_scripts() {
		/*
		wp_enqueue_script(
			'typesense-admin',
			plugin_dir_url( __FILE__ ) . 'js/typesense-admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			TYPESENSE_VERSION,
			false
		);
		wp_enqueue_script(
			'typesense-admin-reindex-button',
			plugin_dir_url( __FILE__ ) . 'js/reindex-button.js',
			array( 'jquery' ),
			TYPESENSE_VERSION,
			false
		);
		/*
		wp_enqueue_script(
			'typesense-admin-push-settings-button',
			plugin_dir_url( __FILE__ ) . 'js/push-settings-button.js',
			array( 'jquery' ),
			TYPESENSE_VERSION,
			false
		);
		*/
		wp_enqueue_script(
			'typesense-admin-reindex-button',
			plugin_dir_url( __FILE__ ) . 'js/reindex-button.js',
			array( 'jquery' ),
			ALGOLIA_VERSION,
			false
		);
		wp_localize_script( 'ajax-script', 'ajax_object',
		array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'we_value' => 1234 ) );
	}

	/**
	 * Displays an error notice for every unmet requirement.
	 *
	 * @author  WebDevStudios <contact@webdevstudios.com>
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public function display_unmet_requirements_notices() {
		if ( ! extension_loaded( 'mbstring' ) ) {
			echo '<div class="error notice">
					  <p>' . esc_html__( 'Typesense Search requires the "mbstring" PHP extension to be enabled. Please contact your hosting provider.', 'wp-search-with-typesense' ) . '</p>
				  </div>';
		} elseif ( ! function_exists( 'mb_ereg_replace' ) ) {
			echo '<div class="error notice">
					  <p>' . esc_html__( 'Typesense needs "mbregex" NOT to be disabled. Please contact your hosting provider.', 'wp-search-with-typesense' ) . '</p>
				  </div>';
		}

		if ( ! extension_loaded( 'curl' ) ) {
			echo '<div class="error notice">
					  <p>' . esc_html__( 'Typesense Search requires the "cURL" PHP extension to be enabled. Please contact your hosting provider.', 'wp-search-with-typesense' ) . '</p>
				  </div>';

			return;
		}

		$this->w3tc_notice();
	}

	/**
	 * Display notice to help users adding 'typesense_' as an ignored query string to the db caching configuration.
	 *
	 * @author  WebDevStudios <contact@webdevstudios.com>
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public function w3tc_notice() {
		if ( ! function_exists( 'w3tc_pgcache_flush' ) || ! function_exists( 'w3_instance' ) ) {
			return;
		}

		$config   = w3_instance( 'W3_Config' );
		$enabled  = $config->get_integer( 'dbcache.enabled' );
		$settings = array_map( 'trim', $config->get_array( 'dbcache.reject.sql' ) );

		if ( $enabled && ! in_array( 'typesense_', $settings, true ) ) {
			/* translators: placeholder contains the URL to the caching plugin's config page. */
			$message = sprintf( __( 'In order for <strong>database caching</strong> to work with Typesense you must add <code>typesense_</code> to the "Ignored Query Stems" option in W3 Total Cache settings <a href="%s">here</a>.', 'wp-search-with-typesense' ), esc_url( admin_url( 'admin.php?page=w3tc_dbcache' ) ) );
			?>
			<div class="error">
				<p><?php echo wp_kses_post( $message ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Display reindexing notices.
	 *
	 * @author  WebDevStudios <contact@webdevstudios.com>
	 * @since   1.0.0
	 */
	public function display_reindexing_notices() {
		$indices = $this->plugin->get_indices(
			array(
				'enabled' => true,
			)
		);

		$allowed_html = array(
			'strong' => array(),
		);

		foreach ( $indices as $index ) {
			if ( $index->exists() ) {
				continue;
			}
			?>
			<div class="error">
				<p>
					<?php
					echo wp_kses(
						sprintf(
							/* Translators: placeholder is an Typesense index name. */
							__( 'For Typesense search to work properly, you need to index: <strong>%1$s</strong>', 'wp-search-with-typesense' ),
							esc_html( $index->get_admin_name() )
						),
						$allowed_html
					);
					?>
				</p>
				<p>
					<button class="typesense-reindex-button button button-primary" data-index="<?php echo esc_attr( $index->get_id() ); ?>">
						<?php esc_html_e( 'Index now', 'wp-search-with-typesense' ); ?>
					</button>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Re index.
	 *
	 * @author  WebDevStudios <contact@webdevstudios.com>
	 * @since   1.0.0
	 *
	 * @throws RuntimeException If index ID or page are not provided, or index name dies not exist.
	 * @throws Exception If index ID or page are not provided, or index name dies not exist.
	 */
	public function re_index() {
		/*
		$index_id = filter_input( INPUT_POST, 'index_id', FILTER_SANITIZE_STRING );
		$page     = filter_input( INPUT_POST, 'p', FILTER_SANITIZE_STRING );

		try {
			if ( empty( $index_id ) ) {
				throw new RuntimeException( 'Index ID should be provided.' );
			}

			if ( ! ctype_digit( $page ) ) {
				throw new RuntimeException( 'Page should be provided.' );
			}
			$page = (int) $page;

			$index = $this->plugin->get_index( $index_id );
			if ( null === $index ) {
				throw new RuntimeException( sprintf( 'Index named %s does not exist.', $index_id ) );
			}

			$total_pages = $index->get_re_index_max_num_pages();

			ob_start();
			if ( $page <= $total_pages || 0 === $total_pages ) {
				$index->re_index( $page );
			}
			ob_end_clean();

			$response = array(
				'totalPagesCount' => $total_pages,
				'finished'        => $page >= $total_pages,
			);

			wp_send_json( $response );
		} catch ( Exception $exception ) {
			echo esc_html( $exception->getMessage() );
			throw $exception;
		}
		*/
		//echo 'gfg';
		$document = [
			'post_id' => '1',
			'id' => '500',
			'post_content' => 'New world',
			'post_title' => 'Dummy text 2',
			'post_excerpt' => 'dcd',
			'is_sticky' => 1,
		
			'post_modified' => 'fwecfwe',
			'post_date' => 'cwe',
		
			'comment_count' => 2,	
		];
		$indices = $this->plugin->get_indices();
		//$post_index = $indices[0];
		try{
			$indices[0]->sync($document);
			//$client = $this->plugin->get_api()->get_client();
			//$client->collections['posts']->documents->create($document);
			//echo gettype($this->plugin->get_api()->get_client());
			//$client = $this->plugin->get_api()->get_client();
			//$client->collections['posts']->documents->create($document);
		}
		catch(Exception $e){
			echo $e->getMessage();
		}

		echo 'Victory';
	}

	/**
	 * Push settings.
	 *
	 * @author  WebDevStudios <contact@webdevstudios.com>
	 * @since   1.0.0
	 *
	 * @throws RuntimeException If index_id is not provided or if the corresponding index is null.
	 * @throws Exception If index_id is not provided or if the corresponding index is null.
	 */
	public function push_settings() {

		$index_id = filter_input( INPUT_POST, 'index_id', FILTER_SANITIZE_STRING );

		try {
			if ( empty( $index_id ) ) {
				throw new RuntimeException( 'index_id should be provided.' );
			}

			$index = $this->plugin->get_index( $index_id );
			if ( null === $index ) {
				throw new RuntimeException( sprintf( 'Index named %s does not exist.', $index_id ) );
			}

			$index->push_settings();

			$response = array(
				'success' => true,
			);
			wp_send_json( $response );
		} catch ( Exception $exception ) {
			echo esc_html( $exception->getMessage() );
			throw $exception;
		}

	}
}