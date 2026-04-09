<?php
/**
 * Central hook registration.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Loader
 */
class Loader {

	/**
	 * Registered actions.
	 *
	 * @var array<int, array{0: string, 1: string, 2: int, 3: int}>
	 */
	protected $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array<int, array{0: string, 1: string, 2: int, 3: int}>
	 */
	protected $filters = array();

	/**
	 * Add action.
	 *
	 * @param string $hook       Hook name.
	 * @param object $component  Object instance.
	 * @param string $callback   Method name.
	 * @param int    $priority   Priority.
	 * @param int    $accepted_args Accepted args.
	 * @return void
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = array( $hook, array( $component, $callback ), $priority, $accepted_args );
	}

	/**
	 * Add filter.
	 *
	 * @param string $hook       Hook name.
	 * @param object $component  Object instance.
	 * @param string $callback   Method name.
	 * @param int    $priority   Priority.
	 * @param int    $accepted_args Accepted args.
	 * @return void
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = array( $hook, array( $component, $callback ), $priority, $accepted_args );
	}

	/**
	 * Register all hooks with WordPress.
	 *
	 * @return void
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook[0], $hook[1], $hook[2], $hook[3] );
		}
		foreach ( $this->actions as $hook ) {
			add_action( $hook[0], $hook[1], $hook[2], $hook[3] );
		}
	}
}
