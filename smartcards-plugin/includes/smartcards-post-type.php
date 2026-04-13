<?php
/**
 * Post Type: Smart Cards
 */

if (!function_exists('sc_register_smartcards_post_type')) {
  function sc_register_smartcards_post_type() {
    $labels = [
      'name'                  => 'Smart Cards',
      'singular_name'         => 'Smart Card',
      'menu_name'             => 'Smart Cards',
      'name_admin_bar'        => 'Smart Card',
      'add_new'               => 'Añadir nueva',
      'add_new_item'          => 'Añadir Smart Card',
      'new_item'              => 'Nueva Smart Card',
      'edit_item'             => 'Editar Smart Card',
      'view_item'             => 'Ver Smart Card',
      'all_items'             => 'Todas las Smart Cards',
      'search_items'          => 'Buscar Smart Cards',
      'not_found'             => 'No se encontraron Smart Cards',
      'not_found_in_trash'    => 'No hay Smart Cards en la papelera',
      'featured_image'        => 'Imagen destacada',
      'set_featured_image'    => 'Asignar imagen destacada',
      'remove_featured_image' => 'Quitar imagen destacada',
      'use_featured_image'    => 'Usar como imagen destacada',
    ];

    register_post_type('smartcards', [
      'labels'             => $labels,
      'public'             => true,
      'publicly_queryable' => true,
      'show_ui'            => true,
      'show_in_menu'       => true,
      'show_in_rest'       => true,
      'has_archive'        => false,
      'rewrite'            => [
        'slug'       => 'card',
        'with_front' => false,
      ],
      'supports'           => ['title', 'editor', 'thumbnail'],
      'menu_icon'          => 'dashicons-id',
    ]);
  }
}

add_action('init', 'sc_register_smartcards_post_type');
