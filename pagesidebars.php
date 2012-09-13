<?php 
/*
Plugin Name: Unlimited Page Sidebars
Plugin URI: http://ederson.peka.nom.br
Description: Assign a specific sidebar (widget area) to a page.
Version: 0.1
Author: Ederson Peka
Author URI: http://ederson.peka.nom.br
*/

// Registering our settings...
add_action( 'admin_init', 'pagesidebars_settings' );
    
function pagesidebars_settings() {
    register_setting( 'pagesidebars_options', 'ups_nsidebars' );
    register_setting( 'pagesidebars_options', 'ups_overwrite' );
}
if ( !function_exists( 'sanitizeArray' ) ) {
  function sanitizeArray( $i, $a ){
    return in_array( $i, $a ) ? $i : '' ;
  }
}
if ( !function_exists( 'sanitizeSidebars' ) ) {
  function sanitizeSidebars( $t ){
    global $wp_registered_sidebars;
    return sanitizeArray( $t, array_keys( $wp_registered_sidebars ) );
  }
}
function pagesidebars_overwrite() {
    $overwrite = get_option( 'ups_overwrite' );
    if ( !is_array( $overwrite ) ) $overwrite = array( $overwrite );
    return $overwrite;
}
function pagesidebars_nsidebars() {
    return intval( '0' . get_option( 'ups_nsidebars' ) );
}

// quando o plugin é ativado, preenche as opções com valores padrão
register_activation_hook( __FILE__, 'pagesidebars_activate' );
// quando o plugin é desinstalado, remove as opções
register_uninstall_hook( __FILE__, 'pagesidebars_uninstall' );
// Quando o plugin é ativado, preenche as opções com valores padrão...
function pagesidebars_activate() {
    // ...só se as opções já não estiverem preenchidas
    if ( get_option( 'ups_nsidebars', false ) === false ) add_option( 'ups_nsidebars', 5 );
    if ( get_option( 'ups_overwrite', false ) === false ) add_option( 'ups_overwrite', array( 'sidebar' ) );
}
function pagesidebars_uninstall() {
    delete_option( 'ups_nsidebars' );
    delete_option( 'ups_overwrite' );
}

// Our options screens...
add_action( 'admin_menu', 'pagesidebars_menu' );

function pagesidebars_menu() {
    add_options_page( __( 'Unlimited Page Sidebars Options', 'pagesidebars' ), __( 'Unlimited Page Sidebars', 'pagesidebars' ), 'manage_options', 'pagesidebars-options', 'pagesidebars_options' );
}

function pagesidebars_options() {
    global $wp_registered_sidebars;
    $nsidebars = pagesidebars_nsidebars();
    $overwrite = pagesidebars_overwrite();
    if ( !is_array( $overwrite ) ) $overwrite = array( $overwrite );
    ?>
    <div class="wrap">
        <div id="icon-options-general" class="icon32"><br /></div>
        <h2><?php _e( 'Unlimited Page Sidebars Options', 'pagesidebars' ); ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields( 'pagesidebars_options' ); ?>
            
            <table class="form-table">
            <tbody>
            
            <tr valign="top">
            <th scope="row">
                <label for="nsidebars"><?php _e( 'Number of Optional Sidebars:', 'pagesidebars' ) ;?></label>
            </th>
            <td>
                <input type="number" name="ups_nsidebars" id="nsidebars" value="<?php echo $nsidebars ;?>" size="3" min="0" step="1" class="small-text" />
            </td>
            </tr>
            
            <tr valign="top">
            <th scope="row">
                <?php _e( 'Overwrite These Sidebars:', 'pagesidebars' ) ;?>
            </th>
            <td>
                <?php $n = 0; foreach ( $wp_registered_sidebars as $k => $v ) : $n++; ?>
                    <?php if ( stripos( $k, 'custom-sidebar-' ) !== 0 ) : ?>
                        <label for="overwrite-sidebar-<?php echo $n; ?>"><input type="checkbox" name="ups_overwrite[]" id="overwrite-sidebar-<?php echo $n; ?>" value="<?php echo $k ;?>" <?php if ( in_array( $k, $overwrite ) ) : ?>checked="checked" <?php endif; ?>/> <?php _e( $v['name'] ); ?></label><br />
                    <?php endif; ?>
                <?php endforeach; ?>
            </td>
            </tr>
            
            </tbody>
            </table>

            <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e( 'Update Options', 'pagesidebars' ) ;?>" />
            </p>
        </form>
    </div>
    <?php
}


// Add Settings link to plugins - code from GD Star Ratings
// (as seen in http://www.whypad.com/posts/wordpress-add-settings-link-to-plugins-page/785/ )
function pagesidebars_settings_link( $links, $file ) {
    $this_plugin = plugin_basename(__FILE__);
    if ( $file == $this_plugin ) {
        $settings_link = '<a href="options-general.php?page=pagesidebars-options">' . __( 'Settings', 'pagesidebars' ) . '</a>';
        array_unshift( $links, $settings_link );
    }
    return $links;
}
add_filter( 'plugin_action_links', 'pagesidebars_settings_link', 10, 2 );


add_action( 'init', 'pagesidebars_init' );

function pagesidebars_init() {
    load_plugin_textdomain( 'pagesidebars', false, basename( dirname( __FILE__ ) ) . '/languages' );
    if ( function_exists('register_sidebar') ) {
        $total = pagesidebars_nsidebars(); for ( $n = 1; $n <= $total; $n++ ) :
	        register_sidebar( array(
		        'id' => 'custom-sidebar-' . $n,
		        'name' => sprintf( __( 'Custom Sidebar #%1$d', 'pagesidebars' ), $n ),
		        'description' => __( 'This "virtual" sidebar is hidden by default. It should show up only in the pages that select it on the "Sidebar" field (taking the place of the "real" sidebars marked to be replaced on the options screen).', 'pagesidebars' ),
		        'before_widget' => '<div id="%1$s" class="widget-container %2$s">',
		        'after_widget' => '</div>',
		        'before_title' => '<h3 class="widget-title">',
		        'after_title' => '</h3>',
	        ) );
	    endfor;
	}
	add_filter( 'sidebars_widgets', 'pagesidebars_overwrite_widgets' );
}
function pagesidebars_overwrite_widgets( $swidgets ) {
    global $post, $wp_registered_sidebars;
    $overwrite = pagesidebars_overwrite();
    if ( !is_array( $overwrite ) ) $overwrite = array( $overwrite );
    if ( is_page() && $sidebar_id = intval( '0' . pagesidebars_first_custom( 'sidebar_id' , $post->ID ) ) ) if ( $sidebar_id && array_key_exists( 'custom-sidebar-' . $sidebar_id, $swidgets ) ) {
        foreach ( $overwrite as $ow ) $swidgets[ $ow ] = $swidgets[ 'custom-sidebar-' . $sidebar_id ];
    }
    return $swidgets;
}

/* Use the admin_menu action to define the custom boxes */
add_action( 'admin_menu', 'pagesidebars_add_custom_box' );
/* Use the save_post action to do something with the data entered */
add_action( 'save_post', 'pagesidebars_save_postdata' );
/* Adds a custom section to the "advanced" Post edit screen */
function pagesidebars_page_attributes_meta_box( $p ) {
    page_attributes_meta_box( $p );
    $total = pagesidebars_nsidebars(); if ( $total ) {
        $pid = intval( $_GET['post'] );
        $sidebar_id = intval( '0' . pagesidebars_first_custom( 'sidebar_id' , $pid ) );
        // Use nonce for verification
        echo '<input type="hidden" name="pagesidebars_noncename" id="pagesidebars_noncename" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
        ?>
        <p><strong><?php _e( 'Sidebar', 'pagesidebars' ); ?></strong></p>
        <label class="screen-reader-text" for="sidebar_id"><?php _e( 'Sidebar', 'pagesidebars' ); ?></label>
        <select name="sidebar_id" id="sidebar_id">
            <option value="0">(<?php _e( 'Default', 'pagesidebars' ); ?>)</option>
            <?php for ( $n = 1; $n <= $total; $n++ ) : ?>
                <option value="<?php echo $n; ?>"<?php if ( $n == $sidebar_id ) : ?> selected="selected"<?php endif; ?>><?php printf( __( 'Custom Sidebar #%1$d', 'pagesidebars' ), $n ); ?></option>
            <?php endfor; ?>
        </select>
        <?php
    }
}
function pagesidebars_add_custom_box( $post_id ) {
    if( function_exists( 'add_meta_box' ) ) {
        add_meta_box( 'pageparentdiv', __( 'Page Attributes' ), 'pagesidebars_page_attributes_meta_box', 'page', 'side', 'default' );
    }
}
/* When the post is saved, saves our custom data */
function pagesidebars_save_postdata( $post_id ) {
    // verify this came from the our screen and with proper authorization,
    // because save_post can be triggered at other times
    if ( !wp_verify_nonce( $_POST['pagesidebars_noncename'], plugin_basename(__FILE__) ) ) {
        return $post_id;
    }

    if ( 'page' == $_POST['post_type'] ) {
        if ( !current_user_can( 'edit_page', $post_id ) )
            return $post_id;
    } else {
        if( !current_user_can( 'edit_post', $post_id ) )
            return $post_id;
    }

    // OK, we're authenticated: we need to find and save the data
    pagesidebars_save_custom( 'sidebar_id', $post_id );

    return true;
}
function pagesidebars_first_custom( $field, $pid=null, $default='' ) {
    $ret = get_post_custom_values('_pagesidebars_'.$field, $pid);
    if($ret && $ret[0]) return $ret[0];
    return $default;
}
function pagesidebars_save_custom( $field, $pid ) {
    if ( is_array( $_POST[$field] ) ) {
        $value = implode( ',', $_POST[$field] );
    } else {
        $value = trim( $_POST[$field] );
    }
    add_post_meta( $pid, '_pagesidebars_'.$field, $value, true ) or update_post_meta( $pid, '_pagesidebars_'.$field, $value );
}

?>
