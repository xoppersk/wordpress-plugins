<?php
/**
 * The seven site helpers (spec §6.5).
 *
 * Each helper is small, isolated, and gated by its own toggle in
 * sc_settings (Settings → Scroll Chapters → Site helpers). Switching
 * one off removes only that behaviour: its hooks are never registered
 * and its script is never enqueued.
 *
 * @package ScrollChapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers module.
 */
class SC_Helpers {

	/**
	 * Search group result counts, filled by the query callbacks and
	 * read by the footer summary writer. Keys: bends, posts, signs.
	 *
	 * @var array|null
	 */
	private static $search_counts = null;

	/**
	 * Re-entry guard for the counting twin query in search_group().
	 * While the twin runs, a re-entrant call (any hook re-firing on
	 * the twin) must not spawn another WP_Query.
	 *
	 * @var bool
	 */
	private static $counting = false;

	/**
	 * Wire only the helpers that are switched on.
	 */
	public function __construct() {
		if ( SC_Settings::helper_enabled( 'filter_motion' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_filter_motion' ) );
		}

		if ( SC_Settings::helper_enabled( 'form_summary' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_form_summary' ) );
		}

		if ( SC_Settings::helper_enabled( 'grouped_search' ) ) {
			add_action( 'elementor/query/klo_search_bends', array( $this, 'query_search_bends' ) );
			add_action( 'elementor/query/klo_search_posts', array( $this, 'query_search_posts' ) );
			add_action( 'elementor/query/klo_search_signs', array( $this, 'query_search_signs' ) );
			add_action( 'wp_footer', array( $this, 'search_summary_footer' ) );
		}

		if ( SC_Settings::helper_enabled( 'bend_order' ) ) {
			add_action( 'pre_get_posts', array( $this, 'bend_order_query' ) );
			add_filter( 'get_previous_post_join', array( $this, 'adjacent_join' ) );
			add_filter( 'get_next_post_join', array( $this, 'adjacent_join' ) );
			add_filter( 'get_previous_post_where', array( $this, 'adjacent_prev_where' ) );
			add_filter( 'get_next_post_where', array( $this, 'adjacent_next_where' ) );
			add_filter( 'get_previous_post_sort', array( $this, 'adjacent_prev_sort' ) );
			add_filter( 'get_next_post_sort', array( $this, 'adjacent_next_sort' ) );
		}

		if ( SC_Settings::helper_enabled( 'header_hide' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_header' ) );
		}

		if ( SC_Settings::helper_enabled( 'menu_motion' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_menu' ) );
		}

		if ( SC_Settings::helper_enabled( 'tag_boxes' ) ) {
			add_shortcode( 'klo_tags', array( $this, 'tag_boxes_shortcode' ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Asset enqueue helpers
	 * ------------------------------------------------------------------ */

	public function enqueue_filter_motion() {
		wp_enqueue_style( 'sc-helpers' );
		wp_enqueue_script( 'sc-filter-motion' );

		// Term counts, keyed by slug, for the chip badges (filter-motion.js).
		$counts = array();
		// Per-taxonomy totals for the "All" chip (data-filter="__all"): the
		// number of distinct published posts carrying any term of the
		// taxonomy (12 bends / 6 entries) — never a sum of term counts.
		$totals = array();
		foreach ( array( 'bend_category', 'category' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$counts[ $term->slug ] = array(
					'count' => (int) $term->count,
					'name'  => $term->name,
				);
			}
			$term_ids = wp_list_pluck( $terms, 'term_id' );
			$tax_obj  = get_taxonomy( $taxonomy );
			if ( $term_ids && $tax_obj && ! empty( $tax_obj->object_type ) ) {
				$total_query = new \WP_Query(
					array(
						'post_type'      => $tax_obj->object_type,
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'no_found_rows'  => false,
						'tax_query'      => array(
							array(
								'taxonomy' => $taxonomy,
								'field'    => 'term_id',
								'terms'    => $term_ids,
							),
						),
					)
				);
				$totals[ $taxonomy ] = (int) $total_query->found_posts;
			}
		}
		wp_localize_script( 'sc-filter-motion', 'scFilterMotion', array( 'counts' => $counts, 'totals' => $totals ) );
	}

	public function enqueue_form_summary() {
		wp_enqueue_style( 'sc-helpers' );
		wp_enqueue_script( 'sc-form-summary' );
	}

	public function enqueue_header() {
		wp_enqueue_style( 'sc-helpers' );
		wp_enqueue_script( 'sc-header' );
	}

	public function enqueue_menu() {
		wp_enqueue_style( 'sc-helpers' );
		wp_enqueue_script( 'sc-menu' );
	}

	/* ---------------------------------------------------------------------
	 * Helper 3 — grouped search results
	 *
	 * The three Query IDs on the Search Results template each map to one
	 * post type, so the page shows Bends / Notebook / Sign Archive groups.
	 * ------------------------------------------------------------------ */

	/**
	 * Shared group query setter.
	 *
	 * @param WP_Query $query    The Loop Grid query.
	 * @param string   $post_type Post type for this group.
	 * @param string   $count_key Key to record the result count under.
	 */
	private function search_group( $query, $post_type, $count_key ) {
		$term = $query->get( 's' );
		if ( ! is_string( $term ) || '' === $term ) {
			$term = get_query_var( 's' );
		}
		if ( ( ! is_string( $term ) || '' === $term ) && isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search term.
			$term = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on this line.
		}
		if ( ! is_string( $term ) ) {
			$term = get_search_query();
		}
		$query->set( 'post_type', $post_type );
		$query->set( 's', $term );
		$query->set( 'post_status', 'publish' );

		if ( null === self::$search_counts ) {
			self::$search_counts = array(
				'bends' => 0,
				'posts' => 0,
				'signs' => 0,
				'term'  => $term,
			);
		}

		// Count this group with a twin query so the footer can write the
		// summary line even before the grids have rendered. The twin is
		// built from explicit minimal args ONLY. The original version
		// merged $query->query_vars, which inherits Elementor's query
		// marker, so the twin re-fired this very action and the query
		// recursed until PHP killed the request (every /?s=… returned
		// HTTP 500). The static guard is the second layer: while the
		// twin runs, a re-entrant call sets the main query's vars above
		// but records nothing and spawns nothing.
		if ( self::$counting ) {
			return;
		}
		self::$counting = true;
		$count_query    = new WP_Query(
			array(
				'post_type'      => $post_type,
				's'              => $term,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);
		self::$counting = false;

		self::$search_counts[ $count_key ] = (int) $count_query->found_posts;
		wp_reset_postdata();
	}

	public function query_search_bends( $query ) {
		$this->search_group( $query, 'bend', 'bends' );
	}

	public function query_search_posts( $query ) {
		$this->search_group( $query, 'post', 'posts' );
	}

	public function query_search_signs( $query ) {
		$this->search_group( $query, 'archive_sign', 'signs' );
	}

	/**
	 * Write the summary line into #sc-search-summary, reveal the
	 * empty-state container when all three groups are empty, and hide
	 * any group grid that came back with nothing.
	 */
	public function search_summary_footer() {
		if ( ! is_search() || null === self::$search_counts ) {
			return;
		}
		$c     = self::$search_counts;
		$total = $c['bends'] + $c['posts'] + $c['signs'];

		$summary = sprintf(
			/* translators: 1: total results, 2: search term, 3: bends count, 4: notes count, 5: signs count. */
			__( '%1$s for \'%2$s\' · %3$s, %4$s, %5$s', 'scroll-chapters' ),
			sprintf( _n( '%d result', '%d results', $total, 'scroll-chapters' ), $total ),
			$c['term'],
			sprintf( _n( '%d bend', '%d bends', $c['bends'], 'scroll-chapters' ), $c['bends'] ),
			sprintf( _n( '%d note', '%d notes', $c['posts'], 'scroll-chapters' ), $c['posts'] ),
			sprintf( _n( '%d sign', '%d signs', $c['signs'], 'scroll-chapters' ), $c['signs'] )
		);
		?>
		<script>
		(function () {
			var summary = document.getElementById('sc-search-summary');
			if (summary) { summary.textContent = <?php echo wp_json_encode( $summary ); ?>; }
			if (<?php echo (int) $total; ?> === 0) {
				document.body.classList.add('sc-search-all-empty');
			}
			// Hide groups whose grid rendered no items (their Loop Grid
			// "nothing found" message hides with them).
			document.querySelectorAll('.elementor-widget-loop-grid, .elementor-widget-loop-carousel').forEach(function (widget) {
				var items = widget.querySelectorAll('.e-loop-item');
				if (items.length === 0) { widget.style.display = 'none'; }
			});
		})();
		</script>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Helper 4 — bend order (by bend_number)
	 * ------------------------------------------------------------------ */

	/**
	 * Order bend archives and Loop queries by bend_number ascending.
	 *
	 * @param WP_Query $query Query being built.
	 */
	public function bend_order_query( $query ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		$post_types = (array) $query->get( 'post_type' );
		if ( ! in_array( 'bend', $post_types, true ) ) {
			return;
		}
		if ( $query->is_singular() ) {
			return;
		}
		$query->set( 'meta_key', 'bend_number' );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order', 'ASC' );
	}

	/**
	 * The current bend's number, or null when not on a bend.
	 *
	 * @return int|null
	 */
	private function current_bend_number() {
		global $post;
		if ( ! $post || 'bend' !== get_post_type( $post ) ) {
			return null;
		}
		$value = get_post_meta( $post->ID, 'bend_number', true );
		return '' === $value ? null : (int) $value;
	}

	/**
	 * Join the bend_number meta row into prev/next queries.
	 *
	 * @param string $join Existing join clause.
	 * @return string
	 */
	public function adjacent_join( $join ) {
		if ( null === $this->current_bend_number() ) {
			return $join;
		}
		global $wpdb;
		return $join . " INNER JOIN {$wpdb->postmeta} AS sc_pm ON ( p.ID = sc_pm.post_id AND sc_pm.meta_key = 'bend_number' )";
	}

	public function adjacent_prev_where( $where ) {
		$number = $this->current_bend_number();
		if ( null === $number ) {
			return $where;
		}
		global $wpdb;
		return $wpdb->prepare(
			'WHERE CAST( sc_pm.meta_value AS UNSIGNED ) < %d AND p.post_type = %s AND p.post_status = %s',
			$number,
			'bend',
			'publish'
		);
	}

	public function adjacent_next_where( $where ) {
		$number = $this->current_bend_number();
		if ( null === $number ) {
			return $where;
		}
		global $wpdb;
		return $wpdb->prepare(
			'WHERE CAST( sc_pm.meta_value AS UNSIGNED ) > %d AND p.post_type = %s AND p.post_status = %s',
			$number,
			'bend',
			'publish'
		);
	}

	public function adjacent_prev_sort( $sort ) {
		if ( null === $this->current_bend_number() ) {
			return $sort;
		}
		return 'ORDER BY CAST( sc_pm.meta_value AS UNSIGNED ) DESC LIMIT 1';
	}

	public function adjacent_next_sort( $sort ) {
		if ( null === $this->current_bend_number() ) {
			return $sort;
		}
		return 'ORDER BY CAST( sc_pm.meta_value AS UNSIGNED ) ASC LIMIT 1';
	}

	/* ---------------------------------------------------------------------
	 * Helper 7 — [klo_tags] service tag boxes
	 *
	 * Prints the current bend's place, year and each service_tags value
	 * as .klo-tagbox spans, for the Bend hero.
	 * ------------------------------------------------------------------ */

	/**
	 * Read an ACF field when ACF is present, raw meta otherwise.
	 *
	 * @param string $name Field name.
	 * @param int    $post_id Post ID.
	 * @return mixed
	 */
	private function bend_field( $name, $post_id ) {
		if ( function_exists( 'get_field' ) ) {
			return get_field( $name, $post_id );
		}
		return get_post_meta( $post_id, $name, true );
	}

	/**
	 * [klo_tags] shortcode.
	 *
	 * @return string
	 */
	public function tag_boxes_shortcode() {
		global $post;
		if ( ! $post || 'bend' !== get_post_type( $post ) ) {
			return '';
		}

		$boxes = array();

		$place = $this->bend_field( 'place', $post->ID );
		if ( ! empty( $place ) ) {
			$boxes[] = (string) $place;
		}

		$year = $this->bend_field( 'year', $post->ID );
		if ( ! empty( $year ) ) {
			$boxes[] = (string) $year;
		}

		$tags = $this->bend_field( 'service_tags', $post->ID );
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				// ACF checkboxes can return value/label pairs by format.
				if ( is_array( $tag ) && isset( $tag['label'] ) ) {
					$tag = $tag['label'];
				}
				if ( is_scalar( $tag ) && '' !== (string) $tag ) {
					$boxes[] = (string) $tag;
				}
			}
		} elseif ( is_string( $tags ) && '' !== $tags ) {
			$boxes[] = $tags;
		}

		if ( empty( $boxes ) ) {
			return '';
		}

		$html = array();
		foreach ( $boxes as $box ) {
			$html[] = '<span class="klo-tagbox">' . esc_html( $box ) . '</span>';
		}
		return implode( "\n", $html );
	}
}
