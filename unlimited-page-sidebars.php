<?php 
/*
Plugin Name: Unlimited Page Sidebars
Plugin URI: https://ederson.peka.nom.br
Description: Allows assigning one specific widget area (sidebar) to each page.
Version: 0.2.4
Author: Ederson Peka
Author URI: https://profiles.wordpress.org/edersonpeka/
Text Domain: unlimited-page-sidebars
*/

if ( !class_exists( 'unlimited_page_sidebars' ) ) :

class unlimited_page_sidebars {
    public static function init() {
        // internationalization
        load_plugin_textdomain(
            'unlimited-page-sidebars',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages/'
        );

        // on activation, fill options with default values
        register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
        // create "settings" link on plugins' screen
        add_filter( 'plugin_action_links', array( __CLASS__, 'settings_link' ), 10, 2 );
        // register settings
        add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
        // create options screen
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        // create custom box
        add_action( 'admin_menu', array( __CLASS__, 'add_custom_box' ) );
        // save custom box data
        add_action( 'save_post', array( __CLASS__, 'save_postdata' ) );
        // overwrite default sidebars when needed
        add_filter( 'sidebars_widgets', array( __CLASS__, 'overwrite_widgets' ) );

        // register sidebars
        if ( function_exists( 'register_sidebar' ) ) {
            $total = call_user_func( array( __CLASS__, 'option_nsidebars' ) );
            for ( $n = 1; $n <= $total; $n++ ) :
                register_sidebar( array(
                    'id' => 'custom-sidebar-' . $n,
                    'name' => sprintf( __( 'Custom Sidebar #%1$d', 'unlimited-page-sidebars' ), $n ),
                    'description' => __( 'This "virtual" sidebar is hidden by default. It should show up only in the pages or posts that select it on the "Sidebar" field (taking the place of the "real" sidebars marked to be replaced on the options screen).', 'unlimited-page-sidebars' ),
                    'before_widget' => '<li id="%1$s" class="widget %2$s">',
                    'after_widget' => '</li>',
                    'before_title' => '<h2 class="widgettitle">',
                    'after_title' => '</h2>',
                ) );
            endfor;
        }
    }

    // register settings
    public static function admin_init() {
        register_setting( 'pagesidebars_options', 'ups_posttypes' );
        register_setting( 'pagesidebars_options', 'ups_nsidebars' );
        register_setting( 'pagesidebars_options', 'ups_overwrite' );    
    }

    // on activation, fill options with default values
    public static function activate() {
        // ...only if options aren't already set
        if ( get_option( 'ups_nsidebars', false ) === false ) {
            add_option( 'ups_nsidebars', 5 );
        }
        if ( get_option( 'ups_overwrite', false ) === false ) {
            add_option( 'ups_overwrite', array( 'sidebar' ) );
        }
    }

    // Add Settings link to plugins screen - code from GD Star Ratings
    // (as seen in http://www.whypad.com/posts/wordpress-add-settings-link-to-plugins-page/785/ )
    public static function settings_link( $links, $file ) {
        $this_plugin = plugin_basename(__FILE__);
        if ( $file == $this_plugin ) {
            $settings_link = '<a href="options-general.php?page=pagesidebars-options">' . __( 'Settings', 'unlimited-page-sidebars' ) . '</a>';
            array_unshift( $links, $settings_link );
        }
        return $links;
    }

    // some getter functions (for retrieving options)
    public static function option_posttypes() {
        $ret = get_option( 'ups_posttypes' );
        if ( !( is_array( $ret ) && count( $ret ) ) ) {
            $ret = array( 'page' );
        }
        return $ret;
    }
    public static function option_nsidebars() {
        return intval( '0' . get_option( 'ups_nsidebars' ) );
    }
    public static function option_overwrite() {
        $overwrite = get_option( 'ups_overwrite' );
        if ( !is_array( $overwrite ) ) {
            $overwrite = array( $overwrite );
        }
        return $overwrite;
    }

    // create options screen
    public static function admin_menu() {
        add_options_page(
            __( 'Unlimited Page Sidebars Options', 'unlimited-page-sidebars' ),
            __( 'Unlimited Page Sidebars', 'unlimited-page-sidebars' ),
            'manage_options',
            'pagesidebars-options',
            array( __CLASS__, 'options_screen' )
        );
    }
    // options screen markup
    public static function options_screen() {
        global $wp_registered_sidebars;
        // get all post types
        $ptypes = get_post_types(
            array(
                'public' => true,
                'show_ui' => true,
                'show_in_nav_menus' => true
            ),
            'objects'
        );
        // retrieve saved options
        $posttypes = call_user_func( array( __CLASS__, 'option_posttypes' ) );
        $nsidebars = call_user_func( array( __CLASS__, 'option_nsidebars' ) );
        $overwrite = call_user_func( array( __CLASS__, 'option_overwrite' ) );
        ?>
        <div class="wrap">
            <div id="icon-options-general" class="icon32"><br /></div>
            <h2><?php _e( 'Unlimited Page Sidebars Options', 'unlimited-page-sidebars' ); ?></h2>
            <form method="post" action="options.php">
                <?php settings_fields( 'pagesidebars_options' ); ?>
                
                <table class="form-table">
                <tbody>
                
                <tr valign="top">
                <th scope="row">
                    <?php _e( 'Enable on post types:', 'unlimited-page-sidebars' ) ;?>
                </th>
                <td>
                    <?php foreach ( $ptypes as $ptype ) : ?>
                        <label for="ptype_<?php echo $ptype->name; ?>"><input type="checkbox" id="ptype_<?php echo $ptype->name; ?>" name="ups_posttypes[]" value="<?php echo $ptype->name; ?>" <?php if ( in_array( $ptype->name, $posttypes ) ) : ?>checked="checked" <?php endif; ?>/> <?php _e( $ptype->labels->name ); ?></label><br />
                    <?php endforeach; ?>
                </td>
                </tr>
                
                <tr valign="top">
                <th scope="row">
                    <label for="nsidebars"><?php _e( 'Number of Optional Sidebars:', 'unlimited-page-sidebars' ) ;?></label>
                </th>
                <td>
                    <input type="number" name="ups_nsidebars" id="nsidebars" value="<?php echo $nsidebars ;?>" size="3" min="0" step="1" class="small-text" />
                </td>
                </tr>
                
                <tr valign="top">
                <th scope="row">
                    <?php _e( 'Overwrite These Sidebars:', 'unlimited-page-sidebars' ) ;?>
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
                <input type="submit" class="button-primary" value="<?php _e( 'Update Options', 'unlimited-page-sidebars' ) ;?>" />
                </p>
            </form>
        </div>
        <?php
    }

    // overwrite default sidebars when needed
    public static function overwrite_widgets( $swidgets ) {
        global $post, $wp_registered_sidebars;
        // retrieve saved options
        $overwrite = call_user_func( array( __CLASS__, 'option_overwrite' ) );
        $posttypes = call_user_func( array( __CLASS__, 'option_posttypes' ) );
        $is_singular_or_posts_page = is_singular();
        $this_singular = $post;
        if ( !$is_singular_or_posts_page ) {
            if ( is_home() && !is_front_page() ) {
                $is_singular_or_posts_page = true;
                $this_singular = get_post( get_option( 'page_for_posts' ) );
            }
        }
        if ( $is_singular_or_posts_page && in_array( $this_singular->post_type, $posttypes ) ) {
            // retrieve saved sidebar id for this page/post
            $sidebar_id = call_user_func(
                array( __CLASS__, 'first_custom' ),
                'sidebar_id',
                $this_singular->ID
            );
            $sidebar_id = intval( '0' . $sidebar_id );
            if ( $sidebar_id && array_key_exists( 'custom-sidebar-' . $sidebar_id, $swidgets ) ) {
                foreach ( $overwrite as $ow ) {
                    $swidgets[ $ow ] = $swidgets[ 'custom-sidebar-' . $sidebar_id ];
                }
            }
        }
        return $swidgets;
    }

    // create custom box in page/post edit screen
    public static function add_custom_box( $post_id ) {
        if ( function_exists( 'add_meta_box' ) ) {
            $posttypes = call_user_func( array( __CLASS__, 'option_posttypes' ) );
            foreach ( $posttypes as $ptype ) {
                add_meta_box(
                    'pageparentdiv',
                    __( 'Unlimited Page Sidebars', 'unlimited-page-sidebars' ),
                    array( __CLASS__, 'page_attributes_meta_box' ),
                    $ptype,
                    'side',
                    'default'
                );
            }
        }
    }
    // custom box markup
    public static function page_attributes_meta_box( $p ) {
        $total = call_user_func( array( __CLASS__, 'option_nsidebars' ) );
        if ( $total ) {
            $sidebar_id = call_user_func( array( __CLASS__, 'first_custom' ), 'sidebar_id' , $p->ID );
            $sidebar_id = intval( '0' . $sidebar_id );
            // use nonce for verification
            echo '<input type="hidden" name="pagesidebars_noncename" id="pagesidebars_noncename" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
            ?>
            <p><strong><?php _e( 'Sidebar', 'unlimited-page-sidebars' ); ?></strong></p>
            <label class="screen-reader-text" for="sidebar_id"><?php _e( 'Sidebar', 'unlimited-page-sidebars' ); ?></label>
            <select name="sidebar_id" id="sidebar_id">
                <option value="0">(<?php _e( 'Default', 'unlimited-page-sidebars' ); ?>)</option>
                <?php for ( $n = 1; $n <= $total; $n++ ) : ?>
                    <option value="<?php echo $n; ?>"<?php if ( $n == $sidebar_id ) : ?> selected="selected"<?php endif; ?>><?php printf( __( 'Custom Sidebar #%1$d', 'unlimited-page-sidebars' ), $n ); ?></option>
                <?php endfor; ?>
            </select>
            <?php
        }
    }
    // save custom box data
    public static function save_postdata( $post_id ) {
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

        // ok, we're authenticated: we need to find and save the data
        call_user_func( array( __CLASS__, 'save_custom' ), 'sidebar_id', $post_id );

        return true;
    }
    // retrieve custom field value
    public static function first_custom( $field, $pid = null, $default = '' ) {
        $ret = get_post_custom_values( '_pagesidebars_' . $field, $pid );
        if ( $ret && $ret[0] ) {
            return $ret[0];
        }
        return $default;
    }
    // save custom field value
    public static function save_custom( $field, $pid ) {
        if ( is_array( $_POST[$field] ) ) {
            $value = implode( ',', $_POST[$field] );
        } else {
            $value = trim( $_POST[$field] );
        }
        add_post_meta(
            $pid,
            '_pagesidebars_' . $field,
            $value,
            true
        ) or update_post_meta(
            $pid,
            '_pagesidebars_' . $field,
            $value
        );
    }
}

// RELEASE THE KRAKEN!
add_action( 'init', array( 'unlimited_page_sidebars', 'init' ) );

endif;
