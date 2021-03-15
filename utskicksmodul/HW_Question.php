<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HW_Question' ) ) :

	/**
	 * Backend for question.php (Fråga/Utskick), handles downloading lists, filtering users,
	 * batching mailings to them via HW_Mailing as well as handling mailing templates and geography regions used as filters.
	 */
	class HW_Question {

		/**
		 * @var array An array of all the question fields that will be added into the array.
		 */
		private static $question_fields_SQL = array();

		/**
		 * @var array An array of the question types and what fields they can have in the lists.
		 */
		private static $list_fields = array();

		/**
		 * @var array An associative array containing readable definitions for all columns being selected.
		 */
		private static $column_definitions = array(
			'ID'                 => 'AnvändarID',
			'first_name'         => 'Förnamn',
			'last_name'          => 'Efternamn',
			'mobile'             => 'Mobilnummer',
			'email'              => 'Email',
			'address'            => 'Address',
			'zipcode'            => 'Postnummer',
			'city'               => 'Ort',
			'foto'               => 'Fototillstånd',
			'form'               => 'Enkät',
			'stipendium'         => 'Stipendium',
			'active'             => 'Aktiv',
			'p1_first'           => 'M1 Förnamn',
			'p1_last'            => 'M1 Efternamn',
			'p1_active'          => 'M1 Aktiv',
			'p2_first'           => 'M2 Förnamn',
			'p2_last'            => 'M2 Efternamn',
			'p2_active'          => 'M2 Aktiv',
			'p1_ID'              => 'M1 AnvändarID',
			'p2_ID'              => 'M2 AnvändarID',
			'p1_mobile'          => 'M1 Mobilnummer',
			'p2_mobile'          => 'M2 Mobilnummer',
			'p1_email'           => 'M1 Epost',
			'p2_email'           => 'M2 Epost',
			'past_event_types'   => 'Har Deltagit i Eventtypes',
			'future_event_types' => 'Kommer Delta i Eventtypes',
			'past_studios'       => 'Har Deltagit i Studios',
			'future_studios'     => 'Kommer Delta i Studios',
			'income'             => 'Inkomst',
			'ssn'                => 'Personnummer',
			'gender'             => 'Kön',
			'worker_role'        => 'Roll',
			'user_type'          => "Användartyp",
			'registered'         => "Registrerad",
			'tax_year'           => "Taxeringsår",
			'scholarship_start'  => "Stipendie Giltigt Från",
			'scholarship_end'    => "Stipendie Giltigt Till",
			'scholarship_type'   => "Stipendietyp"
		);


		//region Initialization

		/**
		 * Initializes HW_Question. Called when file is included.
		 */
		public static function init() {

			define( 'QUESTION_TYPE_CHILD', 0 );
			define( 'QUESTION_TYPE_PARENT', 1 );
			define( 'QUESTION_TYPE_LEADER', 2 );
			define( 'QUESTION_TYPE_INACTIVE', 3 );

			add_action( 'init', array( __CLASS__, 'create_question_fields' ) );

			add_action( 'rest_api_init', function () {


				if ( HWCRM_Auth::user_can( "view_admin_lists" ) ) {

					register_rest_route( 'hwcrm/v2', '/question/mailing/children', array(
						'methods'  => array( 'POST' ),
						'callback' => array(
							__CLASS__,
							'filter_children_question',
						),
					) );

					register_rest_route( 'hwcrm/v2', 'question/mailing/children/send', array(
						'methods'  => array( 'POST' ),
						'callback' => array(
							__CLASS__,
							'batch_children_mailings',
						),
					) );

					register_rest_route( 'hwcrm/v2', 'question/mailing/leaders/send', array(
						'methods'  => array( 'POST' ),
						'callback' => array(
							__CLASS__,
							'batch_leader_mailings',
						),
					) );

					register_rest_route( 'hwcrm/v2', 'question/mailing/leaders', array(
						'methods'  => array( 'POST' ),
						'callback' => array(
							__CLASS__,
							'filter_leader_question',
						),
					) );

					register_rest_route( 'hwcrm/v2', 'question/lists', array(
						'methods'  => array( 'POST' ),
						'callback' => array(
							__CLASS__,
							'download_list',
						),
					) );


				}
			} );

		}

		/**
		 * Creates the array containing all the dynamic join and select statements.
		 */
		public static function create_question_fields() {

			global $wpdb;

			// Question mailing fields & joins
			self::$question_fields_SQL = array(
				// Children Fields.
				'join'   => array(
					'gender'     => "LEFT JOIN {$wpdb->usermeta} g ON g.user_id = u.ID AND g.meta_key = 'gender'",
					'age'        => "LEFT JOIN {$wpdb->usermeta} pnr ON pnr.user_id = u.ID AND pnr.meta_key = 'personnummer'",
					'geography'  => "LEFT JOIN {$wpdb->usermeta} pst ON pst.user_id = u.ID AND pst.meta_key = 'zipcode'",
					'income'     => "LEFT JOIN {$wpdb->usermeta} inc ON inc.user_id = u.ID AND inc.meta_key = 'parent_income'",
					'bookings'   => " 
						LEFT JOIN {$wpdb->bookings} bp ON bp.user_id = u.ID
						LEFT JOIN {$wpdb->term_relationships} ptr ON ptr.object_id = bp.event_id
						LEFT JOIN {$wpdb->term_taxonomy} ptt ON ptt.term_taxonomy_id = ptr.term_taxonomy_id AND ptt.taxonomy = 'hwcrm_event_type'
						LEFT JOIN {$wpdb->terms} pt ON pt.term_id = ptt.term_id
						LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = bp.event_id AND pm.meta_key = '_starttime'",
					"studios"    => "
						LEFT JOIN {$wpdb->terms} 			ts ON ts.term_id = bp.event_track_id
						LEFT JOIN {$wpdb->term_taxonomy}	xs ON xs.term_id = ts.term_id
						LEFT JOIN {$wpdb->terms} 			pr ON pr.term_id = xs.parent
					",
					"stipendium" => "LEFT JOIN {$wpdb->usermeta} sti ON sti.user_id = u.ID AND sti.meta_key = 'stipendium_type'",
					'tax_year'   => "LEFT JOIN {$wpdb->usermeta} tax ON tax.user_id = u.ID AND tax.meta_key = 'tax_year'",
					// Question Default Fields
					'p_mobile'   => "LEFT JOIN {$wpdb->usermeta} p1mo	ON p1mo.user_id = p1.ID AND p1mo.meta_key = 'mobile'
															LEFT JOIN {$wpdb->usermeta} p2mo	ON p2mo.user_id = p2.ID AND p2mo.meta_key = 'mobile'",

					'p_names'           => "LEFT JOIN {$wpdb->usermeta} p1fn	ON p1fn.user_id = p1.ID AND p1fn.meta_key = 'first_name'
															LEFT JOIN {$wpdb->usermeta} p1ln	ON p1ln.user_id = p1.ID AND p1ln.meta_key = 'last_name'
															LEFT JOIN {$wpdb->usermeta} p2fn	ON p2fn.user_id = p2.ID AND p2fn.meta_key = 'first_name'
															LEFT JOIN {$wpdb->usermeta} p2ln	ON p2ln.user_id = p2.ID AND p2ln.meta_key = 'last_name'",
					'name'              => "LEFT JOIN {$wpdb->usermeta} fn  	ON fn.user_id = u.ID AND fn.meta_key =	  'first_name'
															LEFT JOIN {$wpdb->usermeta} ln  	ON ln.user_id = u.ID AND ln.meta_key =	  'last_name'",
					'scholarship_start' => "LEFT JOIN {$wpdb->usermeta} sst     ON sst.user_id = u.ID AND sst.meta_key =    'stipendium_start'",
					'scholarship_end'   => "LEFT JOIN {$wpdb->usermeta} sse     ON sse.user_id = u.ID AND sse.meta_key =    'stipendium'",
					'scholarship_type'  => "LEFT JOIN {$wpdb->usermeta} ssy     ON ssy.user_id = u.ID AND ssy.meta_key =    'stipendium_type'",
					// Leader Fields
					'worker_role'       => "
					LEFT JOIN {$wpdb->leader_roles} ler ON ler.user_id = u.ID
					LEFT JOIN {$wpdb->worker_roles} wor ON wor.role_id = ler.role_id
				",
					'worker_place'      => "
					LEFT JOIN {$wpdb->leader_places} lep ON lep.user_id = u.ID
					LEFT JOIN {$wpdb->worker_places} wop ON wop.place_id = lep.place_id
				",
					'knowledge'         => "
					LEFT JOIN {$wpdb->leaderknowledge} lek ON lek.leaderId = u.ID
					LEFT JOIN {$wpdb->terms} lkt ON lkt.term_id = lek.termId
					LEFT JOIN {$wpdb->term_taxonomy} lktt ON lktt.term_id = lkt.term_id
					LEFT JOIN {$wpdb->terms} lks ON (lks.term_id = lktt.parent) OR (lks.term_id = lktt.term_id AND lktt.parent = 0)
					",
					'employment'        => "LEFT JOIN {$wpdb->usermeta} eps ON eps.user_id = u.ID AND eps.meta_key = 'employment_status'",
					// Parent fields.
					'mobile'            => "LEFT JOIN {$wpdb->usermeta} mo ON mo.user_id = u.ID AND mo.meta_key = 'mobile'",
					// Inactive fields.
					'user_type'         => "LEFT JOIN {$wpdb->usermeta} ut ON ut.user_id = u.ID AND ut.meta_key = 'user_type'",
					'inactivated' => "LEFT JOIN {$wpdb->usermeta} iac ON iac.user_id = u.ID AND iac.meta_key = 'inactivate_timestamp'"

				),
				'select' => array(
					// Children Fields
					'gender'            => 'CASE WHEN g.meta_value = \'M\' THEN "Kille" WHEN g.meta_value = \'F\' THEN "Tjej" WHEN g.meta_value = \'A\' THEN "Annat" else g.meta_value END gender',
					'age'               => 'pnr.meta_value ssn',
					'geography'         => 'pst.meta_value zipcode',
					'parent_geography'  => 'pst.meta_value zipcode',
					'income'            => 'inc.meta_value income',
					'bookings'          => 'GROUP_CONCAT(distinct IF(pm.meta_value < NOW(),pt.name,null) SEPARATOR \',\') past_event_types, GROUP_CONCAT(distinct IF(pm.meta_value >= NOW(),pt.name,null) SEPARATOR \',\') future_event_types,
					GROUP_CONCAT(distinct IF(pm.meta_value < NOW(),pt.term_id,null) SEPARATOR \',\' ) hide_past_event_types,
					GROUP_CONCAT(distinct IF(pm.meta_value >= NOW(),pt.term_id,null) SEPARATOR \',\') hide_future_event_types',
					'stipendium'        => 'sti.meta_value stipendium',
					'studios'           => 'GROUP_CONCAT(distinct IF (pm.meta_value < NOW(), CASE WHEN pr.term_id IS NULL THEN 	
												ts.term_id ELSE pr.term_id END ,null) SEPARATOR \',\' ) hide_past_studios,
											GROUP_CONCAT(distinct IF (pm.meta_value >=NOW(), CASE WHEN pr.term_id IS NULL THEN
												ts.term_id ELSE pr.term_id END ,null) SEPARATOR \',\' ) hide_future_studios,
											GROUP_CONCAT(distinct IF (pm.meta_value >=NOW(), CASE WHEN pr.term_id IS NULL THEN ts.name ELSE pr.name END, null) SEPARATOR \',\') future_studios,
											GROUP_CONCAT(distinct IF (pm.meta_value < NOW(), CASE WHEN pr.term_id IS NULL THEN ts.name ELSE pr.name END ,null) SEPARATOR \',\' ) past_studios',
					'tax_year'          => 'tax.meta_value tax_year',
					'active'            => 'act.meta_value active',
					'scholarship_start' => 'sst.meta_value scholarship_start',
					'scholarship_end'   => 'sse.meta_value scholarship_end',
					'scholarship_type'  => 'ssy.meta_value scholarship_type',
					// Question Default Children Fields
					'p_mobile'          => 'p1mo.meta_value p1_mobile, p2mo.meta_value p2_mobile',
					'p_email'           => 'p1.user_email p1_email, p2.user_email p2_email',
					'p_names'           => 'p1fn.meta_value p1_first, p1ln.meta_value p1_last, p2fn.meta_value p2_first, p2ln.meta_value p2_last',
					'p_ID'              => 'p1u.meta_value p1_ID, p2u.meta_value p2_ID',
					'p_active'          => 'p1ac.meta_value p1_active, p2ac.meta_value p2_active',
					'name'              => 'fn.meta_value first_name, ln.meta_value last_name',
					// Leader Fields
					'worker_role'       => "CONCAT(wor.role_name, ' ', wor.salary, ' kr/h') worker_role",
					'worker_place'      => "GROUP_CONCAT(distinct wop.place_name ) worker_places, GROUP_CONCAT( wop.place_id ) hide_worker_places",
					'knowledge'         => "GROUP_CONCAT(distinct lks.name ) knowledge, GROUP_CONCAT( lks.term_id ) hide_knowledge",
					'employment'        => "(CASE WHEN eps.meta_value = 'AKTIV' THEN 'AKTIV' ELSE 'ALUMNI' END) employment",
					// Parent Fields
					'mobile'            => 'mo.meta_value mobile',
					'email'             => 'u.user_email email',
					'registered'        => 'u.user_registered registered',
					// Inactive Fields.
					'user_type'         => 'ut.meta_value as user_type',
					'inactivated' => 'iac.meta_value inactivated',
				),
			);

			self::$list_fields = array(
				QUESTION_TYPE_CHILD    => array(
					'name'         => 'Namn',
					'gender'       => 'Kön',
					'age'          => 'Ålder',
					'geography'    => 'Geografi',
					'income'       => 'Inkomst',
					'bookings'     => 'Tidigare & Framtida Event types',
					'p_mobile'     => 'M Mobil',
					'p_email'      => 'M E-post',
					'p_ID'         => 'M AnvändarID',
					'p_active'     => 'M Aktiv',
					'stipendium'   => "Stipendium",
					'tax_year'     => "Taxeringsår",
					'm_deactivate' => "M Deaktiveringslänk",
					'active'       => "Aktiv"
				),
				QUESTION_TYPE_PARENT   => array(
					'name'       => 'Namn',
					
					'mobile'     => 'Mobil',
					'email'      => 'E-post',
					'bookings'   => 'Tidigare & Framtida Event Types',
					'deactivate' => 'Inaktiveringslänk',
					'registered' => 'Registreringsdatum'
				),
				QUESTION_TYPE_INACTIVE => array(
					'name'       => 'Namn',
					'mobile'     => 'Mobil',
					'email'      => 'E-post',
					'bookings'   => 'Tidigare & Framtida Event Types',
					'registered' => 'Registreringsdatum',
					"inactivated" => "Inaktiveringsdatum",
					'user_type'  => "Användartyp"
				),
			);
		}
		//endregion

		//region Handles Parsing & Executing Question Queries
		/**
		 * Puts together all the query data and executes the proper query generating function.
		 *
		 * @param $data                    array   Data from parse_children_question_fields.
		 * @param $quesiton_type        int    Defines what query build function is used. Can be: QUESTION_TYPE_CHILD, QUESTION_TYPE_PARENT, QUESTION_TYPE_LEADER or QUESTION_TYPE_INACTIVE
		 * @param $extra_parameters    int    Any extra parameters for the build function.
		 *
		 * @return string           The query.
		 */
		private static function build_question_query( $data, $question_type, $extra_parameters = array() ) {


			$joins = array();

			$select_fields = array();


			// Add all select & join statements.
			foreach ( $data['filter'] as $f => $include_filter ) {
				if ( $include_filter ) {
					if ( isset( self::$question_fields_SQL['select'][ $f ] ) ) {
						$select_fields[] = self::$question_fields_SQL['select'][ $f ];
					}
					if ( isset( self::$question_fields_SQL['join'][ $f ] ) ) {
						$joins[] = self::$question_fields_SQL['join'][ $f ];
					}
				}
			}

			// Implode the arrays into SQL syntax.
			$select = implode( ', ', $select_fields );
			$join   = implode( "\n", $joins );

			if ( count( $select_fields ) === 0 ) {
				$select = '';
			}
			if ( count( $joins ) === 0 ) {
				$join = '';
			}


			// If we have something to select on, we add a comma to the select.
			if ( count( $select_fields ) > 0 ) {
				$select = ", $select";
			}

			$inner_conditionals = "";

			$outer_conditionals = "";

			// Implode the inner conditionals into SQL syntax.
			foreach ( $data['conditionals']['inner'] as $c ) {
				if ( is_array( $c ) ) {
					$inner_conditionals .= "((" . implode( ') OR (', $c ) . ")) AND";
				} else {
					$inner_conditionals .= " ( $c ) AND";
				}
			}

			// Implode the outer conditionals into SQL syntax.
			for ( $i = 0; $i < count( $data['conditionals']['outer'] ); $i ++ ) {
				$c                  = $data['conditionals']['outer'][ $i ];
				$outer_conditionals .= "((" . implode( ') OR (', $c ) . "))";

				if ( $i !== count( $data['conditionals']['outer'] ) - 1 ) {
					$outer_conditionals .= " AND ";
				} else {
					$outer_conditionals = "WHERE " . $outer_conditionals;
				}

			}

			if ( isset( $data['num_rows'] ) ) {
				$outer_conditionals .= "\nLIMIT {$data[ 'num_rows' ]}";
			}


			$query = false;
			
			switch ( $question_type ) {
				case QUESTION_TYPE_CHILD:
					$query = self::build_children_question_query( array(
						'select'       => $select,
						'join'         => $join,
						'conditionals' => array(
							'inner' => $inner_conditionals,
							'outer' => $outer_conditionals,
						),
						'extra'        => $extra_parameters
					) );
					break;
				case QUESTION_TYPE_LEADER:
					$query =
						self::build_leader_question_query( $select, $join, $inner_conditionals, $outer_conditionals );
					break;
				case QUESTION_TYPE_PARENT:
					$query =
						self::build_parent_question_query( $select, $join, $inner_conditionals, $outer_conditionals );
					break;
				case QUESTION_TYPE_INACTIVE;
					$query =
						self::build_inactive_question_query( $select, $join, $inner_conditionals, $outer_conditionals );
					break;
				default:
					$query = false;
			}

			return $query;

		}

		/**
		 * Filters for the Children mailing in question.php
		 *
		 * @param WP_REST_Request $request The request, from question.php.
		 */
		public static function filter_children_question( WP_REST_Request $request, $extra_parameters = array() ) {
			global $wpdb;


			// First we fetch the query data containing all the columns and their WHERE clauses.
			$query_data = self::parse_children_question_fields( $request );

			if ( isset( $extra_parameters['extra_filters'] ) and is_array( $extra_parameters['extra_filters'] ) ) {
				foreach ( $extra_parameters['extra_filters'] as $filter ) {
					$query_data['filter'][ $filter ] = true;
				}
			}

			$query_data['filter']['p_names']  = true;
			$query_data['filter']['p_email']  = true;
			$query_data['filter']['p_mobile'] = true;
			$query_data['filter']['name']     = true;

			if ( isset( $query_data['error'] ) ) {
				return $query_data;
			}

			// Next we put together the query.
			$query = self::build_question_query( $query_data, QUESTION_TYPE_CHILD, $extra_parameters );

			// We get the results.
			$rows = $wpdb->get_results( $query, ARRAY_A );

			// The rows to be loaded into the table.
			$data = array();

			// List of column IDs and definitions to be used.
			$columns = array();

			if ( count( $rows ) > 0 ) {
				foreach ( $rows[0] as $key => $value ) {

					if ( ! isset( self::$column_definitions[ $key ] ) ) {
						$title = $key;
					} else {
						$title = self::$column_definitions[ $key ];
					}
					$columns[] = array(
						'title' => $title,
						'data'  => $key,
					);
				}
			}


			return array( 'rows' => $rows, 'columns' => $columns );
		}

		/**
		 * Parses the request parameters and adds conditionals to the query.
		 *
		 * @param $request  WP_REST_Request The request object.
		 *
		 * @return array                    An array with information about the query.
		 */
		public static function parse_children_question_fields( $request ) {


			// All request variables.
			$gender                      = $request['gender'];
			$age                         = str_replace( ' ', '', $request['age'] );
			$geography                   = $request['city'];
			$tax_year                    = $request['tax_year'];
			$income                      = $request['income'];
			$participated                = $request['participated'];
			$participated_event_type     = $request['participated_event_type'];
			$participated_studio         = $request['participated_studio'];
			$will_participate_event_type = $request['will_participate_event_type'];
			$will_participate            = $request['will_participate'];
			$will_participate_studio     = $request['will_participate_studio'];
			$scholarship_valid           = $request['scholarship_valid'];
			$scholarship_type            = $request['scholarship_type'];
			$num_rows                    = $request['num_rows'];


			// Booleans to check what we need to filter on, to reduce amount of JOINs in query.
			$filter_gender               = false;
			$filter_age                  = false;
			$filter_geography            = false;
			$filter_income               = false;
			$filter_tax_year             = false;
			$filter_bookings             = false;
			$filter_studios              = false;
			$filter_scholarship_validity = false;
			$filter_scholarship_type     = false;

			$income_categories = get_option( 'HWCRM_INCOME_CATEGORIES' );
			$geography_regions = get_option( 'HWCRM_GEOGRAPHY_REGIONS' );


			// These are all the conditionals in the SQL query.
			$conditionals = array();

			// These are the conditionals that will be on the outmost (first) select statement. This is to be able to filter on GROUP_CONCAT.
			$outer_conditionals = array();

			// Now we apply each filter, and append where clauses.
			if ( isset( $gender ) and count( $gender ) < 3 ) {


				// This means we filter on gender.
				$filter_gender = true;

				$conditional = array();
				foreach ( $gender as $g ) {
					if ( $g === 'A' ) {
						$conditional[] = "g.meta_value != 'F' AND g.meta_value != 'M'";
					} else {
						$conditional[] = "g.meta_value = '$g'";
					}
				}
				if ( isset( $gender[1] ) ) {
					$conditional[] = " g.meta_value = '{$gender[1]}'";
				}

				$conditionals[] = $conditional;
			}


			// Make sure they have entered an age filter.
			if ( isset( $age ) and $age !== "" ) {

				// This means we filter on age.
				$filter_age  = true;
				$conditional = array();
				$age_sql     = "SUBSTR(pnr.meta_value, 1, 2)";
				// Get all filters.
				$age_filters = explode( ',', $age );
				if ( count( $age_filters ) > 0 ) {

					// Iterate over them, and check if they are a range or a year, and apply that year.
					foreach ( $age_filters as $age_filter ) {

						// Is it a range?
						if ( strpos( $age_filter, '-' ) !== false ) {
							$range = explode( '-', $age_filter );

							if ( count( $range ) !== 2 ) {
								return array(
									'error' => array(
										'age' => 'En åldersintervall måste innehålla exakt 2 årtal.',
									),
								);
							}

							$min = substr( $range[0], 2 );
							$max = substr( $range[1], 2 );

							$conditional[] = "$age_sql >= $min AND $age_sql <= $max";
						} else {
							// If it is a single year, make sure it is 4 digits long (2002).
							if ( strlen( $age_filter ) !== 4 ) {
								return array(
									'error' => array(
										'age' => 'Vänligen ange alla årtal med 4 siffror.',
									),
								);
							}
							$conditional[] = "$age_sql = " . substr( $age_filter, 2 );
						}
					}

					if ( count( $conditional ) > 0 ) {
						$conditionals[] = $conditional;
					}
				}
			}

			// Make sure they have entered a geography filter.
			if ( isset( $geography ) and count( $geography ) < count( $geography_regions ) ) {
				$filter_geography = true;
				$conditional      = array();

				foreach ( $geography as $region => $values ) {
					foreach ( explode( ',', $values ) as $zip ) {
						$conditional[] = "pst.meta_value LIKE '$zip%'";
					}
				}
				$conditionals[] = $conditional;
			}

			// Filter for tax year.
			if ( isset ( $tax_year ) and $tax_year !== "" ) {
				$filter_tax_year = true;

				$tax_years = explode( ',', $tax_year );

				$conditional = array();

				foreach ( $tax_years as $year ) {

					// Check if they have entered an exclusive filter (exclude rows with this tax year).
					if ( strpos( $tax_year, '!' ) === false ) {
						// If not, simply add a conditional for this year.
						$SQL_year      = intval( $year );
						$conditional[] = "tax.meta_value = $SQL_year";
					} else {
						// Otherwise, make sure we don't include rows with this year.
						$SQL_year      = intval( str_replace( '!', '', $tax_year ) );
						$conditional[] = "tax.meta_value != $SQL_year";
					}
				}

				if ( count( $conditional ) > 0 ) {
					$conditionals[] = $conditional;
				}
			}

			// Filter for income.
			if ( isset( $income ) and count( $income ) < count( $income_categories ) ) {
				$filter_income = true;

				$conditional = array();
				$sql_inc     = "CAST(REPLACE(inc.meta_value, 'kr', '') AS DECIMAL)";
				foreach ( $income as $inc ) {
					if ( $inc === 0 ) {
						$conditional[] = "$sql_inc >= {$income_categories[ 0 ]}";
					} elseif ( $inc === 'not_applied' ) {
						$conditional[] = "$sql_inc IS NULL";
					} elseif ( intval( $inc ) === count( $income_categories ) - 1 ) {
						$conditional[] = "$sql_inc< {$income_categories[ intval($inc) ]}";
					} else {
						$conditional[] =
							"$sql_inc < {$income_categories[ intval($inc) ]} AND $sql_inc >= {$income_categories[ $inc + 1 ]}";
					}
				}


				if ( count( $conditional ) > 0 ) {
					$conditionals[] = $conditional;

				}
			}


			// Filter on past and future participation.
			$part_values = array(
				array(
					'input'          => $participated,
					'event_types'    => $participated_event_type,
					'field_bookings' => 'T.hide_past_event_types',
					'studios'        => $participated_studio,
					'field_studios'  => 'T.hide_past_studios',
					'type'           => 'past'
				),
				array(
					'inputs'         => $will_participate,
					'event_types'    => $will_participate_event_type,
					'field_bookings' => 'T.hide_future_event_types',
					'studios'        => $will_participate_studio,
					'field_studios'  => 'T.hide_future_studios',
					'type'           => 'future'
				),
			);

			foreach ( $part_values as $participation ) {
				// Filter for participance (make sure both alternatives are not selected)
				if ( isset( $participation['input'] ) and count( $participation['input'] ) === 1 ) {
					$filter_bookings = true;
					$studios         = $participation['studios'];
					$event_types     = $participation['event_types'];

					$outer_conditionals[] = array(
						( $participated[0] === 'yes' ) ? "{$participation[ 'field_bookings' ]} IS NOT NULL"
							: "{$participation[ 'field_bookings' ]} IS NULL"
					);

					$outer_conditional = array();

					if ( ! isset( $event_types ) ) {
						$event_types = array();
					}
					if ( ! isset( $studios ) ) {
						$studios = array();
					}
					// Did they also filter on a specific event type?
					foreach ( $event_types as $event_type ) {
						$outer_conditional[] = self::array_to_where( array(
							"{$participation[ 'field_bookings' ]} = '$event_type'",
							"{$participation[ 'field_bookings' ]} LIKE '%,$event_type,%'",
							"{$participation[ 'field_bookings' ]} LIKE '%,$event_type'",
							"{$participation[ 'field_bookings' ]} LIKE '$event_type,%'",
						), 'OR' );


					}

					if ( count( $event_types ) > 0 ) {
						$outer_conditionals[] = $outer_conditional;
						$outer_conditional    = array();
					}


					foreach ( $studios as $studio ) {
						if ( ! $filter_studios ) {
							$filter_studios = true;
						}

						$outer_conditional[] = self::array_to_where( array(
							"{$participation[ 'field_studios' ]} = '$studio'",
							"{$participation[ 'field_studios' ]} LIKE '%,$studio,%'",
							"{$participation[ 'field_studios' ]} LIKE '%,$studio'",
							"{$participation[ 'field_studios' ]} LIKE '$studio,%'",
						), 'OR' );
					}

					if ( count( $studios ) > 0 ) {
						$outer_conditionals[] = $outer_conditional;
					}

					// Make sure they actually attended the event. (If we have checked any past event types)
					if ( count( $event_types ) + count( $studios ) > 0 and $participation['type'] === 'past' ) {
						$conditionals[] = array( "bp.status LIKE '%attending%'" );
					}
				}
			}

			if ( $filter_bookings ) {
				$conditionals[] = array( 'ptt.taxonomy = \'hwcrm_event_type\'' );
			}

			// Filter on scholarship validity.
			if ( isset( $scholarship_valid ) and $scholarship_valid !== '' ) {
				if ( false === DateTime::createFromFormat( 'Y-m-d', $scholarship_valid ) ) {
					return array(
						'error' => array(
							'scholarship_valid' => "Datumet var angivet i fel format."
						),
					);
				}
				$filter_scholarship_validity = true;
				$conditionals[] = "sst.meta_value <= '$scholarship_valid' AND sse.meta_value >= '$scholarship_valid'";
			}


			// Filter scholarship type.
			if ( isset( $scholarship_type ) and $scholarship_type !== '' ) {
				$filter_scholarship_type = true;
				$conditionals[]          = "ssy.meta_value LIKE '%$scholarship_type%'";
			}


			$return = array(
				'filter'       => array(
					'gender'            => $filter_gender,
					'age'               => $filter_age,
					'geography'         => $filter_geography,
					'income'            => $filter_income,
					'bookings'          => $filter_bookings,
					'tax_year'          => $filter_tax_year,
					'studios'           => $filter_studios,
					'scholarship_start' => $filter_scholarship_validity,
					'scholarship_end'   => $filter_scholarship_validity,
					'scholarship_type'  => $filter_scholarship_type
				),
				'conditionals' => array(
					'outer' => $outer_conditionals,
					'inner' => $conditionals,
				),
			);

			if ( isset( $num_rows ) ) {
				$return['num_rows'] = intval( $num_rows );
			}

			return $return;
		}


		/**
		 * Puts together the final query for the children question.
		 *
		 * @param $select                string    The select statements.
		 * @param $join                    string    The join statements.
		 * @param $inner_conditionals    string    The WHERE statements in the inner clause.
		 * @param $outer_conditionals    string    The WHERE statements in the outer clause.
		 * @param $include_orphans        bool    Should we include children that don't have any parents in the query? Defaults to false.
		 *
		 * @return string                        The query.
		 */
		private static function build_children_question_query( $parameters ) {
			global $wpdb;

			$select             = $parameters['select'];
			$join               = $parameters['join'];
			$inner_conditionals = $parameters['conditionals']['inner'];
			$outer_conditionals = $parameters['conditionals']['outer'];
			$extra_parameters   = $parameters['extra'];

			$exclude_orphans_where  = " AND (p1.ID IS NOT NULL OR p2.ID IS NOT NULL)";
			$exclude_inactive_where = " AND  (act.meta_value IS NULL)";

			if ( isset( $extra_parameters['include_orphans'] ) and $extra_parameters['include_orphans'] ) {
				$exclude_orphans_where = "";
			}

			if ( isset( $extra_parameters['include_inactive'] ) and $extra_parameters['include_orphans'] ) {
				$exclude_inactive_where = '';
			}

			if ( $select !== "" ) {
				$select = "$select";
			}
			$query = "
				SELECT * FROM (
					SELECT  u.ID $select FROM {$wpdb->users} u
					LEFT JOIN {$wpdb->usermeta} act   ON act.user_id = u.ID AND act.meta_key = 'active'
					LEFT JOIN {$wpdb->usermeta} typ   ON typ.user_id = u.ID AND typ.meta_key = 'user_type'
					LEFT JOIN {$wpdb->usermeta} p1u ON p1u.user_id = u.ID AND p1u.meta_key =	'user_parent' AND p1u.meta_value NOT LIKE '%WP_Error%'
					LEFT JOIN {$wpdb->usermeta} p2u ON p2u.user_id = u.ID AND p2u.meta_key =		'user_parent' AND p2u.meta_value NOT LIKE '%WP_Error%' AND p2u.meta_value != p1u.meta_value
					LEFT JOIN {$wpdb->usermeta} p1ac 	ON p1ac.meta_key = 'active' AND p1ac.user_id = p1u.meta_value
					LEFT JOIN {$wpdb->usermeta} p2ac 	ON p2ac.meta_key = 'active' AND p2ac.user_id = p2u.meta_value
					LEFT JOIN {$wpdb->users} p1	 		ON p1.ID = p1u.meta_value AND p1ac.meta_value IS NULL 
					LEFT JOIN {$wpdb->users} p2	 		ON p2.ID = p2u.meta_value AND p2ac.meta_value IS NULL
					$join
					WHERE $inner_conditionals (typ.meta_value = 'child') $exclude_inactive_where $exclude_orphans_where
					GROUP BY u.ID
				) AS T
				$outer_conditionals
				";

			


			return $query;
		}

		/**
		 * Puts together the final query for the inactive list.
		 *
		 * @param string $select The select statements.
		 * @param string $join The join statements.
		 * @param string $inner_conditionals WHERE statements in the inner clause
		 * @param string $outer_conditionals WHERE statements in the outer clause.
		 *
		 * @return string                     The final query.
		 */
		private static function build_inactive_question_query( $select, $join, $inner_conditionals,
															   $outer_conditionals ) {
			global $wpdb;
			$query = "
				SELECT * FROM (
					SELECT  u.ID $select FROM {$wpdb->users} u
					LEFT JOIN {$wpdb->usermeta} act   ON act.user_id = u.ID AND act.meta_key = 'active'
					$join
					WHERE $inner_conditionals (act.meta_value IS NOT NULL) 
					GROUP BY u.ID
				) AS T
				$outer_conditionals
			";

			return $query;
		}

		private static function build_parent_question_query( $select, $join, $inner_conditionals,
															 $outer_conditionals ) {
			global $wpdb;

			$query = " 
                SELECT * FROM ( 
                    SELECT  u.ID $select FROM {$wpdb->users} u
                    LEFT JOIN {$wpdb->usermeta} typ   ON typ.user_id = u.ID AND typ.meta_key = 'user_type' 
                    LEFT JOIN {$wpdb->usermeta} act   ON act.user_id = u.ID AND act.meta_key = 'active'
                    LEFT JOIN {$wpdb->usermeta} par   ON par.meta_key = 'user_parent' AND par.meta_value = u.ID 	
                    $join 
                    WHERE $inner_conditionals (act.meta_value IS NULL) AND (typ.meta_value = 'parent') AND (par.meta_value IS NULL)
                    GROUP BY u.ID 
                ) AS T 
                $outer_conditionals 
            ";
			
			
			


			return $query;
		}

		/**
		 * Filters for the leader mailing in question.php.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return array                    All leaders that match the criteria.
		 */
		public static function filter_leader_question( WP_REST_Request $request ) {
			global $wpdb;

			// First we need to fetch the query data.
			$query_data = self::parse_leader_question_fields( $request );

			// Next we build the query.
			$query = self::build_question_query( $query_data, QUESTION_TYPE_LEADER );

			// Get the results.
			$rows = $wpdb->get_results( $query, ARRAY_A );


			// The rows are to be loaded into the table.
			$data = array();

			// List of column IDs and definitions to be used.
			$columns = array();

			if ( count( $rows ) > 0 ) {
				foreach ( $rows[0] as $key => $value ) {
					if ( ! isset( self::$column_definitions[ $key ] ) ) {
						$title = $key;
					} else {
						$title = self::$column_definitions[ $key ];
					}
					$columns[] = array(
						'title' => $title,
						'data'  => $key,
					);
				}
			}

			return array(
				'rows'    => $rows,
				'columns' => $columns,
			);
		}

		/**
		 * Parses and creates the conditions for the leader question.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return array                        The query data with all restraints.
		 */
		public static function parse_leader_question_fields( WP_REST_Request $request ) {
			$is_active = $request['active'];
			$roles     = $request['role'];
			$places    = $request['work_place'];
			$studios   = $request['studios'];
			$num_rows  = $request['num_rows'];

			$filter_role       = false;
			$filter_employment = false;
			$filter_places     = false;
			$filter_studios    = false;
			$filter_active     = false;

			// These are all the conditionals in the SQL query.
			$conditionals = array();

			// These are all the outer conditionals.
			$outer_conditionals = array();


			$total_roles   = count( HW_Worker_Role::get_worker_roles() );
			$total_places  = count( HW_Worker_place::get_worker_places() );
			$total_studios = count( HWCRM_Helpers::get_studios() );

			// Apply the employment status filter.
			if ( isset( $is_active ) and count( $is_active ) === 1 ) {
				$filter_active  = true;
				$conditionals[] = array(
					( $is_active[0] === 'yes' ) ? "LOWER(eps.meta_value) = 'aktiv'"
						: "LOWER(eps.meta_value) = 'inaktiv'",
				);

			}

			// Apply the roles filter.
			if ( isset( $roles ) and count( $roles ) < $total_roles ) {
				$filter_role = true;
				$conditional = array();
				foreach ( $roles as $role ) {
					$conditional[] = 'ler.role_id = ' . intval( $role );
				}
				$conditionals[] = $conditional;
			}

			// Apply the work plcaes filter.
			if ( isset( $places ) and count( $places ) < $total_places ) {
				$filter_places = true;
				$conditional   = array();
				foreach ( $places as $place ) {
					$conditional[] = "hide_worker_places LIKE '%" . intval( $place ) . "%'";
				}
				$outer_conditionals[] = $conditional;
			}

			// Apply the studios filter.
			if ( isset( $studios ) and count( $studios ) < $total_studios ) {
				$filter_studios = true;
				$conditional    = array();
				foreach ( $studios as $studio ) {
					$studio        = intval( $studio );
					$conditional[] = "hide_knowledge LIKE '{$studio},%'";
					$conditional[] = "hide_knowledge = '{$studio}'";
					$conditional[] = "hide_knowledge LIKE '%,{$studio}'";
					$conditional[] = "hide_knowledge LIKE '%,{$studio},%'";
				}

				$outer_conditionals[] = $conditional;
			}

			$return = array(
				'filter'       => array(
					'employment'   => $filter_active,
					'worker_role'  => $filter_role,
					'worker_place' => $filter_places,
					'knowledge'    => $filter_studios,
				),
				'conditionals' => array(
					'outer' => $outer_conditionals,
					'inner' => $conditionals,
				),
			);

			if ( isset( $num_rows ) ) {
				$return['num_rows'] = intval( $num_rows );
			}


			return $return;


		}

		/**
		 * Puts together the final query for the leader question.
		 *
		 * @param $select                string    The select statements.
		 * @param $join                    string    The join statements.
		 * @param $inner_conditionals    string    The WHERE statements in the inner clause.
		 * @param $outer_conditionals    string    The WHERE statements in the outer clause.
		 *
		 * @return string                        The query.
		 */
		private static function build_leader_question_query( $select, $join, $inner_conditionals,
															 $outer_conditionals ) {
			global $wpdb;

			$query = "
				SELECT * FROM (
					SELECT 	u.ID,
							fn.meta_value first_name,
							ln.meta_value last_name,
							mo.meta_value mobile,
							u.user_email email
							$select FROM {$wpdb->users} u
					LEFT JOIN {$wpdb->usermeta} fn 		ON fn.user_id = u.ID AND fn.meta_key = 'first_name'
					LEFT JOIN {$wpdb->usermeta} ln 		ON ln.user_id = u.ID AND ln.meta_key = 'last_name'
					LEFT JOIN {$wpdb->usermeta} mo 		ON mo.user_id = u.ID AND mo.meta_key = 'mobile'
					LEFT JOIN {$wpdb->usermeta} typ 	ON typ.user_id = u.ID AND typ.meta_key = 'user_type'
					$join
					WHERE $inner_conditionals (typ.meta_value = 'leader')
					GROUP BY u.ID
				) AS T
				$outer_conditionals
			";

			return $query;
		}

		private static function array_to_where( $array, $join ) {
			return '(' . implode( ") $join (", $array ) . ')';
		}
		//endregion

		//region Request handlers for batching the mailings.
		/**
		 * Batches a children question mailing using the HW_Mailing:batch
		 *
		 * @param WP_REST_Request $request The request contaning information such as the filter parameters, and the template.
		 *
		 * @return array                        Status from HW_Mailing:batch.
		 */
		public static function batch_children_mailings( WP_REST_Request $request ) {

			// When we batch the mailings we need to make sure we send to everyone that matches the filter.
			$request['num_rows'] = null;

			// First we get all the children to send the mailing to.

			$extra_filters = array(
				'p_email',
				'p_ID',
				'p_mobile'
			);
			$rows          =
				self::filter_children_question( $request, array( 'extra_filters' => $extra_filters ) )['rows'];

			$template = get_option( $request['option'] );
			$is_sms   = $template['type'] === 'sms';


			// We need to iterate through the list and compile a list of parents and their children.
			$recipients = array();


			foreach ( $rows as $child ) {
				// Add both parents to the list, or add the children to the parents list if the parent has already been added.
				foreach ( array( 'p1_ID', 'p2_ID' ) as $p_ID ) {
					if ( ! isset( $child[ $p_ID ] ) or $child[ $p_ID ] === '' ) {
						continue;
					}

					if ( $request['debug'] === 'yes' ) {
						$child['p1_mobile'] = $request['admin_mobile'];
						$child['p2_mobile'] = $request['admin_mobile'];
						$child['p1_email']  = $request['admin_email'];
						$child['p2_email']  = $request['admin_email'];
					}

					if ( key_exists( $child[ $p_ID ], $recipients ) ) {
						$recipients[ $child[ $p_ID ] ]['children'][] = $child;
					} else {
						$recipients[ $child[ $p_ID ] ] = array(
							'children' => array( $child ),
							'parent'   => substr( $p_ID, 0, 3 ),
						);
					}

					if ( $request['debug'] === 'yes' ) {
						break;
					}
				}

				if ( $request['debug'] === 'yes' ) {
					break;
				}
			}


			return HW_Mailing::batch_mailing( intval( $request['batch_number'] ), intval( $request['batch_size'] ),
				$is_sms, array_values( $recipients ), array( __CLASS__, 'children_question_mailing' ), $template );

		}


		/**
		 * Batches leader mailings.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return array
		 */
		public static function batch_leader_mailings( WP_REST_Request $request ) {
			$template = get_option( $request['option'] );
			$is_sms   = $template['type'] === 'sms';

			// When we batch the mailings we need to make sure we send to everyone that matches the filter.
			$request['num_rows'] = null;


			$rows = self::filter_leader_question( $request )['rows'];

			foreach ( $rows as $adult ) {
				if ( $request['debug'] === 'yes' ) {
					$adult['mobile'] = $request['admin_mobile'];
					$adult['email']  = $request['admin_email'];
					$rows            = array( $adult );
				}
			}

			return HW_Mailing::batch_mailing( intval( $request['batch_number'] ), intval( $request['batch_size'] ),
				$is_sms, $rows, array( __CLASS__, 'adult_question_mailing' ), $template );
		}

		/**
		 * Creates a mailing for a child based of a template.
		 *
		 * @param $parent       array   Array with parent data.s
		 * @param $template     array   Array contaning template information, such as body and subject.
		 * @param $is_sms       boolean If this is an SMS.
		 *
		 * @return array                The finished mailing.
		 */
		public static function children_question_mailing( $parent, $template, $is_sms ) {
			$child_names  = array(
				'fnames' => array(),
				'lnames' => array(),
			);
			$prefix       = $parent['parent'];
			$children     = $parent['children'];
			$info         = $parent['children'][0];
			$contact_type = ( $is_sms ) ? 'mobile' : 'email';
			$recipient    = $info[ $prefix . $contact_type ];


			$body    = $template['body'];
			$subject = $template['subject'];

			foreach ( $children as $child ) {
				$child_names['fnames'][] = $child['first_name'];
				$child_names['lnames'][] = $child['last_name'];
			}


			$joint_firstnames = HW_Mailing::join_names( $child_names, false );
			$joint_fullnames  = HW_Mailing::join_names( $child_names, true );

			$first_name = $info[ $prefix . 'first' ];
			$last_name  = $info[ $prefix . 'last' ];

			$unregister_link = HWCRM_MyPages::get_stop_email_link( get_user_by( 'ID', $info[ $prefix . 'ID' ] ) );
			$discount_code   = HWCRM_Helpers::encode_discount_code( $info[ $prefix . 'ID' ] );
			$discount_amount = get_option( 'DISCOUNT_CODE_AMOUNT' );

			$body = str_replace( '%C_FNAME%', $joint_firstnames, $body );
			$body = str_replace( '%C_FULLNAME%', $joint_fullnames, $body );
			$body = str_replace( '%FNAME%', $first_name, $body );
			$body = str_replace( '%LNAME%', $last_name, $body );
			$body = str_replace( '%AVREG%', $unregister_link, $body );
			$body = str_replace( '%DISCOUNT_CODE%', $discount_code, $body );
			$body = str_replace( '%DISCOUNT_AMOUNT%', $discount_amount, $body );


			return array(
				'body'       => $body,
				'subject'    => $subject,
				'recipients' => $recipient,
			);
		}

		/**
		 * Creates an adult mailing based of a template. Called from HW_Mailing::batch_mailing
		 *
		 * @param $recipient    array    The recipient row from batch_adult_mailings.
		 * @param $template     array    The template object.
		 * @param $is_sms       bool    Is this an SMS?
		 *
		 * @return array                The finished mailing.
		 */
		public static function adult_question_mailing( $recipient, $template, $is_sms ) {


			$first_name      = $recipient['first_name'];
			$last_name       = $recipient['last_name'];
			$unregister_link = HWCRM_MyPages::get_stop_email_link( get_user_by( 'ID', $recipient['ID'] ) );
			$discount_code   = HWCRM_Helpers::encode_discount_code( $recipient['ID'] );
			$discount_amount = get_option( 'DISCOUNT_CODE_AMOUNT' );
			$body            = $template['body'];
			$subject         = $template['subject'];
			$contact         = $is_sms ? $recipient['mobile'] : $recipient['email'];

			$body = str_replace( '%FNAME%', $first_name, $body );
			$body = str_replace( '%LNAME%', $last_name, $body );
			$body = str_replace( '%AVREG%', $unregister_link, $body );
			$body = str_replace( '%DISCOUNT_CODE%', $discount_code, $body );
			$body = str_replace( '%DISCOUNT_AMOUNT%', $discount_amount, $body );


			return array(
				'body'       => $body,
				'subject'    => $subject,
				'recipients' => $contact,
			);
		}
		//endregion

		//region lists
		/**
		 * @return array    All lists that are available for download.
		 */
		public static function get_lists() {
			return array(
				'all-kids'                => array(
					"name"          => "Alla barn",
					"question_type" => QUESTION_TYPE_CHILD,
				),
				'all-inactive'            => array(
					"name"          => "Alla inaktiva barn/vuxna/ledare",
					"question_type" => QUESTION_TYPE_INACTIVE,
				),
				'adults-without-children' => array(
					'name'          => "Alla aktiva vuxna utan barn",
					'question_type' => QUESTION_TYPE_PARENT
				)
			);
		}

		/**
		 * Returns a list of fields (keys and readable) a question type list can include.
		 *
		 * @param int $question_type The question type.
		 *
		 * @return  array                        All fields said question type can include.
		 */
		public static function get_list_fields_from_type( $question_type ) {
			return self::$list_fields[ $question_type ];
		}

		/**
		 * This request handler parses and downloads an excel list to the user.
		 *
		 * @param WP_REST_Request $request
		 *
		 * @return mixed
		 */
		public static function download_list( WP_REST_Request $request ) {

			global $wpdb;
		
			

			// Build the query.
			$query_data = self::get_list_query_data( $request );
			
			

			// If they don't provide any fields, add all of them.
			if ( ! isset( $request['fields'] ) or count( $request['fields'] ) === 0 ) {
				$request = array();
				foreach ( self::$list_fields[ $query_data['question_type'] ] as $key => $value ) {
					$query_data['filter'][ $key ] = true;
					$request['fields'][]          = $key;
				}
			}

			// If m_deactivate is filtered upon, make sure
			if ( in_array( 'm_deactivate', $request['fields'] ) ) {
				if ( ! in_array( 'p_ID', $request['fields'] ) ) {
					$query_data['filter']['p_ID'] = true;
				}
			}

			$build_options = array(
				'include_orphans'  => true,
				'include_inactive' => true
			);
			$query         = self::build_question_query( $query_data, $query_data['question_type'], $build_options );
			
			
			$rows = $wpdb->get_results( $query );
			$CSV_content = self::get_list_CSV( $rows, $request );

			self::set_list_headers();

			echo $CSV_content;

			exit();

		}

		/**
		 * Puts together a query for a list.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return string                   The query.
		 */
		public static function get_list_query_data( WP_REST_Request $request ) {

			$joins   = "";
			$selects = "";

			$data = array(
				'filter'        => array(),
				'conditionals'  => array( 'inner' => array(), 'outer' => array() ),
				'question_type' => QUESTION_TYPE_INACTIVE
			);

			// Make sure to include the selected fields.
			if ( isset( $request['fields'] ) ) {
				foreach ( $request['fields'] as $field ) {
					$data['filter'][ $field ] = true;
				}
			}

			// Set the appropriate question type, for building the query lateron.
			switch ( $request['list-type'] ) {
				case 'all-kids':
					$data['question_type'] = QUESTION_TYPE_CHILD;
					break;
				case 'adults-without-children':
					$data['question_type'] = QUESTION_TYPE_PARENT;
					break;
			}


			return $data;
		}

		/**
		 * Generates the CSV content for alist
		 *
		 * @param array $rows The rows from the query.
		 * @param array $request The request.
		 *
		 * @return string            The CSV string.
		 */
		public static function get_list_CSV( $rows, $request ) {


			$header = array();

			$data = "";

			// Make sure the list is not empty.
			if ( count( $rows ) < 1 ) {
				return "";
			}

			// Get the headers from the first row.
			foreach ( $rows[0] as $key => $value ) {
				if ( strpos( $key, 'hide' ) !== false ) {
					continue;
				}

				$title = $key;
				if ( isset( self::$column_definitions[ $key ] ) ) {
					$title = self::$column_definitions[ $key ];
				}
				$header[] = str_replace( ',', ' & ', $title );
			}

			if ( in_array( 'deactivate', $request['fields'] ) ) {
				$header[] = "Inaktiveringslänk";
			}


			if ( in_array( 'm_deactivate', $request['fields'] ) ) {
				$header[] = "V1 Inaktiveringslänk";
				$header[] = "V2 Inaktiveringslänk";
			}


			// Generate the rows.
			foreach ( $rows as $row ) {

				$CSV_row = array();

				foreach ( $row as $field => $value ) {
					if ( strpos( $field, 'hide' ) === false ) {
						$CSV_row[] = str_replace( ',', ' & ', $value );
					}
				}

				// If we are supposed to include the stop email link, include that now.
				if ( in_array( 'deactivate', $request['fields'] ) ) {
					$CSV_row[] = HWCRM_MyPages::get_stop_email_link( new WP_User( $row->ID ) );
				}
				// Do the same for the parents.
				if ( in_array( 'm_deactivate', $request['fields'] ) ) {
					foreach ( array( $row->p1_ID, $row->p2_ID ) as $p_ID ) {
						if ( isset( $p_ID ) ) {
							$CSV_row[] = HWCRM_MyPages::get_stop_email_link( new WP_User( $p_ID ) );
						} else {
							$CSV_row[] = '';
						}
					}
				}

				$data .= implode( ',', $CSV_row ) . "\n";
			}

			return implode( ',', $header ) . "\n" . $data;
		}

		/**
		 * Sets the headers for downloading a CSV file.
		 */
		public static function set_list_headers() {
			header( "Content-type: application/octet-stream; charset=utf-8" );
			header( "Content-Disposition: attachment; filename=data.csv" );
			header( "Cache-Control: public" );
			header( "Pragma: no-cache" );
			header( "Expires: 0" );
		}
		//endregion

	}

	HW_Question::init();

endif;

