<?php

namespace Pods\Whatsit\Storage;

use Pods\Whatsit;
use Pods\Whatsit\Storage;
use Pods\Whatsit\Store;

/**
 * Collection class.
 *
 * @since 2.8.0
 */
class Collection extends Storage {

	/**
	 * {@inheritdoc}
	 */
	protected static $type = 'collection';

	/**
	 * @var array
	 */
	protected static $compatible_types = [
		'collection' => 'collection',
		'file'       => 'file',
	];

	/**
	 * @var array
	 */
	protected $secondary_args = [];

	/**
	 * {@inheritdoc}
	 */
	public function get_label() {
		return __( 'Code', 'pods' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( array $args = [] ) {
		// Object type is required.
		if ( empty( $args['object_type'] ) ) {
			return null;
		}

		if ( ! empty( $args['name'] ) ) {
			$find_args = [
				'object_type' => $args['object_type'],
				'name'        => $args['name'],
				'limit'       => 1,
			];

			$objects = $this->find( $find_args );

			if ( $objects ) {
				return reset( $objects );
			}
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function find( array $args = [] ) {
		// Object type OR parent is required.
		if ( empty( $args['object_type'] ) && empty( $args['parent'] ) ) {
			return [];
		}

		/**
		 * Filter the maximum number of posts to get for post type storage.
		 *
		 * @since 2.8.0
		 *
		 * @param int $limit
		 *
		 */
		$limit = apply_filters( 'pods_whatsit_storage_post_type_find_limit', 300 );

		if ( empty( $args['limit'] ) ) {
			$args['limit'] = $limit;
		}

		if ( ! isset( $args['args'] ) ) {
			$args['args'] = [];
		}

		$args['args'] = (array) $args['args'];

		$secondary_object_args = [
			'parent',
			'group',
		];

		foreach ( $secondary_object_args as $arg ) {
			$args      = $this->setup_arg( $args, $arg );
			$arg_value = $this->get_arg_value( $args, $arg );

			if ( '_null' === $arg_value ) {
				continue;
			}

			$args['args'][ $arg ] = $arg_value;
		}

		foreach ( $this->secondary_args as $arg ) {
			if ( ! isset( $args[ $arg ] ) ) {
				continue;
			}

			$args['args'][ $arg ] = $args[ $arg ];
		}

		$cache_key = wp_json_encode( $args );

		$use_cache = did_action( 'init' );

		$found_objects = null;

		if ( $use_cache ) {
			$found_objects = pods_static_cache_get( $cache_key, self::class . '/find_objects' );
		}

		// Cached objects found, don't process again.
		if ( is_array( $found_objects ) ) {
			return $found_objects;
		}

		$object_collection = Store::get_instance();

		$objects = $object_collection->get_objects( static::$compatible_types );

		if ( empty( $objects ) ) {
			return [];
		}

		if ( ! empty( $args['object_type'] ) ) {
			$object_types = (array) $args['object_type'];

			foreach ( $objects as $k => $object ) {
				if ( in_array( $object->get_object_type(), $object_types, true ) ) {
					continue;
				}

				unset( $objects[ $k ] );
			}

			if ( empty( $objects ) ) {
				return [];
			}
		}

		foreach ( $args['args'] as $arg => $value ) {
			if ( null === $value ) {
				foreach ( $objects as $k => $object ) {
					if ( $value === $object->get_arg( $arg ) ) {
						continue;
					}

					unset( $objects[ $k ] );
				}

				if ( empty( $objects ) ) {
					return [];
				}

				continue;
			}

			if ( ! is_array( $value ) ) {
				$value = trim( $value );

				foreach ( $objects as $k => $object ) {
					if ( $value === (string) $object->get_arg( $arg ) ) {
						continue;
					}

					unset( $objects[ $k ] );
				}

				if ( empty( $objects ) ) {
					return [];
				}

				continue;
			}

			$value = (array) $value;
			$value = array_map( 'trim', $value );
			$value = array_unique( $value );
			$value = array_filter( $value );

			if ( $value ) {
				foreach ( $objects as $k => $object ) {
					$arg_value = $object->get_arg( $arg );

					if ( null !== $arg_value && ! is_scalar( $arg_value ) ) {
						$arg_value = serialize( $arg_value );
					}

					if ( in_array( (string) $arg_value, $value, true ) ) {
						continue;
					}

					unset( $objects[ $k ] );
				}

				if ( empty( $objects ) ) {
					return [];
				}
			}
		}//end foreach

		if ( ! empty( $args['id'] ) ) {
			$args['id'] = (array) $args['id'];
			$args['id'] = array_map( 'absint', $args['id'] );
			$args['id'] = array_unique( $args['id'] );
			$args['id'] = array_filter( $args['id'] );

			if ( $args['id'] ) {
				foreach ( $objects as $k => $object ) {
					if ( in_array( $object->get_id(), $args['id'], true ) ) {
						continue;
					}

					unset( $objects[ $k ] );
				}

				if ( empty( $objects ) ) {
					return [];
				}
			}
		}

		if ( ! empty( $args['name'] ) ) {
			$args['name'] = (array) $args['name'];
			$args['name'] = array_map( 'trim', $args['name'] );
			$args['name'] = array_unique( $args['name'] );
			$args['name'] = array_filter( $args['name'] );

			if ( $args['name'] ) {
				foreach ( $objects as $k => $object ) {
					if ( in_array( $object->get_name(), $args['name'], true ) ) {
						continue;
					}

					unset( $objects[ $k ] );
				}

				if ( empty( $objects ) ) {
					return [];
				}
			}
		}

		if ( isset( $args['internal'] ) ) {
			foreach ( $objects as $k => $object ) {
				if ( $args['internal'] === (boolean) $object->get_arg( 'internal' ) ) {
					continue;
				}

				unset( $objects[ $k ] );
			}
		}

		if ( ! empty( $args['limit'] ) ) {
			$objects = array_slice( $objects, 0, $args['limit'], true );
		}

		if ( $use_cache ) {
			pods_static_cache_set( $cache_key, $objects, self::class . '/find_objects' );
		}

		$names = wp_list_pluck( $objects, 'name' );

		return array_combine( $names, $objects );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function save_object( Whatsit $object ) {
		$storage_type = $object->get_object_storage_type();

		if ( empty( $storage_type ) ) {
			$object->set_arg( 'object_storage_type', $this->get_object_storage_type() );
		}

		$object_collection = Store::get_instance();
		$object_collection->register_object( $object );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function delete_object( Whatsit $object ) {
		// If this object has fields or groups, delete them.
		$objects = array_merge( $object->get_all_fields(), $object->get_groups() );

		// Delete child objects.
		array_map( [ $this, 'delete' ], $objects );

		$object_collection = Store::get_instance();
		$object_collection->unregister_object( $object );

		$object->set_arg( 'id', null );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function save_args( Whatsit $object ) {
		return true;
	}

}
