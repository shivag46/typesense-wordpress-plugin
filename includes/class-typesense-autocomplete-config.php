<?php
/**
 * Typesense_Autocomplete_Config class file.
 *
 * @author  WebDevStudios <contact@webdevstudios.com>
 * @since   1.0.0
 *
 * @package WebDevStudios\WPSWA
 */

/**
 * Class Typesense_Autocomplete_Config
 *
 * @since 1.0.0
 */
class Typesense_Autocomplete_Config {

	/**
	 * The Typesense_Plugin instance.
	 *
	 * @author WebDevStudios <contact@webdevstudios.com>
	 * @since  1.0.0
	 *
	 * @var Typesense_Plugin
	 */
	private $plugin;

	/**
	 * Typesense_Autocomplete_Config constructor.
	 *
	 * @author WebDevStudios <contact@webdevstudios.com>
	 * @since  1.0.0
	 *
	 * @param Typesense_Plugin $plugin The Typesense_Plugin instance.
	 */
	public function __construct( Typesense_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Get form data.
	 *
	 * @author WebDevStudios <contact@webdevstudios.com>
	 * @since  1.0.0
	 *
	 * @return array
	 */
	public function get_form_data() {
		$indices = $this->plugin->get_indices();
		$config  = array();

		$existing_config = $this->get_config();

		/**
		 * Loop over the indices.
		 *
		 * @author WebDevStudios <contact@webdevstudios.com>
		 * @since  1.0.0
		 *
		 * @var Typesense_Index $index
		 */
		foreach ( $indices as $index ) {
			$index_config = $this->extract_index_config( $existing_config, $index->get_id() );
			if ( $index_config ) {
				// If there is an existing configuration, add it.
				$config[] = $index_config;
				continue;
			}

			$default_config            = $index->get_default_autocomplete_config();
			$default_config['enabled'] = false;

			$config[] = $default_config;
		}

		usort(
			$config, function( $a, $b ):int {
				return $a['position'] <=> $b['position'];
			}
		);

		return $config;
	}

	/**
	 * Sanitize form data.
	 *
	 * @author WebDevStudios <contact@webdevstudios.com>
	 * @since  1.0.0
	 *
	 * @param array $data The data to sanitize.
	 *
	 * @return mixed
	 */
	public function sanitize_form_data( $data ) {

		if ( ! is_array( $data ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $data as $index_id => $config ) {
			$index = $this->plugin->get_index( $index_id );

			// Remove disabled indices.
			if ( ! isset( $config['enabled'] ) ) {
				continue;
			}

			$merged_config = array_merge(
				$index->get_default_autocomplete_config(),
				array(
					'position'        => (int) $config['position'],
					'max_suggestions' => (int) $config['max_suggestions'],
				)
			);

			if ( isset( $config['label'] ) && ! empty( $config['label'] ) ) {
				$merged_config['label'] = $config['label'];
			}

			$sanitized[] = $merged_config;
		}

		return $sanitized;
	}

	/**
	 * Extract index config.
	 *
	 * @author WebDevStudios <contact@webdevstudios.com>
	 * @since  1.0.0
	 *
	 * @param array  $config   The config.
	 * @param string $index_id The index id.
	 *
	 * @return mixed|void
	 */
	private function extract_index_config( array $config, $index_id ) {
		foreach ( $config as $entry ) {
			if ( $index_id === $entry['index_id'] ) {
				return $entry;
			}
		}
	}

	/**
	 * Get config.
	 *
	 * @author WebDevStudios <contact@webdevstudios.com>
	 * @since  1.0.0
	 *
	 * @return array
	 */
	public function get_config() {
		$settings = $this->plugin->get_settings();
		$config   = $settings->get_autocomplete_config();
		foreach ( $config as $key => &$entry ) {
			if ( ! isset( $entry['index_id'] ) ) {
				unset( $config[ $key ] );
				continue;
			}
////////////////////////////////////////////changes here
			$index = $this->plugin->get_index( $entry['index_id'] );
			if ( null === $index ) {
				unset( $config[ $key ] );
				continue;
			}
////////////////////////////////////////////changes here
			$entry['index_name'] = $index->get_name();
			$entry['enabled']    = true;
		}

		$config = (array) apply_filters( '_autocomplete_config', $config );

		// Remove manually disabled indices.
		$config = array_filter(
			$config, function( $item ) {
				return (bool) $item['enabled'];
			}
		);

		// Sort the indices.
		usort(
			$config, function( $a, $b ):int {
				return $a['position'] <=> $b['position'];
			}
		);

		return $config;
	}
}
