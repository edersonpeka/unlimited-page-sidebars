<?php
/*
Plugin Name: Unlimited Page Sidebars
Plugin URI: https://ederson.ferreira.tec.br
Description: Allows assigning one specific widget area (sidebar) to each page or post.
Version: 0.2.5
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

        // register private post type
        $post_type_args = array( 'public' => false, 'hierarchical' => false );
        register_post_type( 'custom_sidebar', $post_type_args );

        // create "settings" link on plugins' screen
        add_filter( 'plugin_action_links', array( __CLASS__, 'settings_link' ), 10, 2 );
        // register settings, css, js, ajax...
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
            $n = 0;
            // all saved custom sidebars (posts of type 'custom_sidebar')
            $sidebars = call_user_func( array( __CLASS__, 'get_sidebars' ) );
            foreach ( $sidebars as $sidebar ) :
                $n++;
                $name = get_the_title( $sidebar->ID );
                if ( !$name ) {
                    $name = sprintf( __( 'Custom Sidebar #%1$d', 'unlimited-page-sidebars' ), $n );
                }
                // register saved custom sidebar as a sidebar indeed
                register_sidebar( array(
                    'id' => 'custom_sidebar_' . $sidebar->ID,
                    'name' => $name,
                    'description' => __( 'This "virtual" sidebar is hidden by default. It should show up only in the pages or posts that select it on the "Custom Sidebar" section.', 'unlimited-page-sidebars' ),
                    'before_widget' => '<li id="%1$s" class="widget %2$s">',
                    'after_widget' => '</li>',
                    'before_title' => '<h2 class="widgettitle">',
                    'after_title' => '</h2>',
                ) );
            endforeach;

            // deprecated, backward compatibility
            $total = call_user_func( array( __CLASS__, 'option_nsidebars' ) );
            for ( $n = 1; $n <= $total; $n++ ) :
                register_sidebar( array(
                    'id' => 'custom-sidebar-' . $n,
                    'name' => sprintf( __( 'Deprecated Custom Sidebar #%1$d', 'unlimited-page-sidebars' ), $n ),
                    'description' => __( 'This "virtual" sidebar is deprecated. It was left here so you can transfer its widgets to any new custom sidebar. After that, you can get rid of these deprecated sidebars in plugin\'s options screen.', 'unlimited-page-sidebars' ),
                    'before_widget' => '<li id="%1$s" class="widget %2$s">',
                    'after_widget' => '</li>',
                    'before_title' => '<h2 class="widgettitle">',
                    'after_title' => '</h2>',
                ) );
            endfor;
        }
    }

    public static function admin_init() {
        // register settings
        register_setting( 'pagesidebars_options', 'ups_posttypes' );
        // deprecated
        register_setting( 'pagesidebars_options', 'ups_nsidebars' );

        $p_dir = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) . '/';
        $p_url = WP_PLUGIN_URL . '/' . dirname( plugin_basename( __FILE__ ) ) . '/';
        // register css file
        wp_register_style(
            'unlimited-page-sidebars-admin-css',
            $p_url . 'css/admin.css',
            array(),
            filemtime( $p_dir . 'css/admin.css' )
        );
        // register js file
        wp_register_script(
            'unlimited-page-sidebars-admin-script',
            $p_url . 'js/admin.js',
            array(),
            filemtime( $p_dir . 'js/admin.js' )
        );

        // ajax functions
        add_action( 'wp_ajax_custom_sidebar_add', array( __CLASS__, 'ajax_sidebar_add' ) );
        add_action( 'wp_ajax_custom_sidebar_rename', array( __CLASS__, 'ajax_sidebar_rename' ) );
        add_action( 'wp_ajax_custom_sidebar_remove', array( __CLASS__, 'ajax_sidebar_remove' ) );
        add_action( 'wp_ajax_custom_sidebar_list', array( __CLASS__, 'ajax_sidebar_list' ) );
    }
    // create options screen
    public static function admin_menu() {
        $_page = add_options_page(
            __( 'Unlimited Page Sidebars Options', 'unlimited-page-sidebars' ),
            __( 'Unlimited Page Sidebars', 'unlimited-page-sidebars' ),
            'manage_options',
            'pagesidebars-options',
            array( __CLASS__, 'options_screen' )
        );
        // enqueue css
        add_action( 'admin_print_styles-' . $_page, array( __CLASS__, 'admin_styles' ) );
        // enqueue js
        add_action( 'admin_print_scripts-' . $_page, array( __CLASS__, 'admin_scripts' ) );
    }
    // enqueue css
    public static function admin_styles() {
        wp_enqueue_style( 'unlimited-page-sidebars-admin-css' );
    }
    // enqueue js
    public static function admin_scripts() {
        // localize strings
        wp_localize_script(
            'unlimited-page-sidebars-admin-script',
            'unlimited_page_sidebars',
            array(
                'ask_name' => __( 'Inform new sidebar name:', 'unlimited-page-sidebars' ),
                'loading_list' => __( 'Loading...', 'unlimited-page-sidebars' ),
                'confirm_removal' => __( 'Removing a custom sidebar is a permanent action. Are you sure?', 'unlimited-page-sidebars' ),
            )
        );
        wp_enqueue_script( 'unlimited-page-sidebars-admin-script', false, array() );
    }

    // ajax functions
    public static function ajax_sidebar_add() {
        $str_added = __( 'Sidebar added successfully.', 'unlimited-page-sidebars' );
        $str_empty = __( 'Sidebar name can\'t be empty.', 'unlimited-page-sidebars' );
        $str_notallowed = __( 'Not allowed.', 'unlimited-page-sidebars' );
        $ret = array( 'message' => '', 'id' => 0 );
        if ( current_user_can( 'manage_options' ) ) {
            $name = array_key_exists( 'name', $_POST ) ? $_POST[ 'name' ] : '';
            if ( $name ) {
                $id = wp_insert_post( array(
                    'post_title' => $name,
                    'post_content' => '',
                    'post_type' => 'custom_sidebar',
                    'post_status' => 'publish',
                ), true );
                if ( is_wp_error( $id ) ) {
                    $ret[ 'message' ] = $id->get_error_message();
                } else {
                    $ret[ 'message' ] = $str_added;
                    $ret[ 'id' ] = $id;
                }
            } else {
                $ret[ 'message' ] = $str_empty;
            }
        } else {
            $ret[ 'message' ] = $str_notallowed;
        }
        wp_send_json( $ret );
    }
    public static function ajax_sidebar_rename() {
        $str_renamed = __( 'Sidebar renamed successfully.', 'unlimited-page-sidebars' );
        $str_empty = __( 'Sidebar name can\'t be empty.', 'unlimited-page-sidebars' );
        $str_emptyid = __( 'No sidebar id specified.', 'unlimited-page-sidebars' );
        $str_notallowed = __( 'Not allowed.', 'unlimited-page-sidebars' );
        $ret = array( 'message' => '', 'id' => 0 );
        if ( current_user_can( 'manage_options' ) ) {
            $name = array_key_exists( 'name', $_POST ) ? $_POST[ 'name' ] : '';
            $id = array_key_exists( 'id', $_POST ) ? $_POST[ 'id' ] : 0;
            $id = intval( '0' . $id );
            if ( !$id ) {
                $ret[ 'message' ] = $str_emptyid;
            } elseif ( $name ) {
                $id = wp_update_post( array(
                    'ID' => $id,
                    'post_title' => $name,
                ), true );
                if ( is_wp_error( $id ) ) {
                    $ret[ 'message' ] = $id->get_error_message();
                } else {
                    $ret[ 'message' ] = $str_renamed;
                    $ret[ 'id' ] = $id;
                }
            } else {
                $ret[ 'message' ] = $str_empty;
            }
        } else {
            $ret[ 'message' ] = $str_notallowed;
        }
        wp_send_json( $ret );
    }
    public static function ajax_sidebar_remove() {
        $str_removed = __( 'Sidebar removed successfully.', 'unlimited-page-sidebars' );
        $str_emptyid = __( 'No sidebar id specified.', 'unlimited-page-sidebars' );
        $str_notallowed = __( 'Not allowed.', 'unlimited-page-sidebars' );
        $ret = array( 'message' => '', 'id' => 0 );
        if ( current_user_can( 'manage_options' ) ) {
            $id = array_key_exists( 'id', $_POST ) ? $_POST[ 'id' ] : 0;
            $id = intval( '0' . $id );
            if ( $id ) {
                $id = wp_delete_post( $id, true );
                if ( is_wp_error( $id ) ) {
                    $ret[ 'message' ] = $id->get_error_message();
                } else {
                    $ret[ 'message' ] = $str_removed;
                    $ret[ 'id' ] = $id;
                }
            } else {
                $ret[ 'message' ] = $str_emptyid;
            }
        } else {
            $ret[ 'message' ] = $str_notallowed;
        }
        wp_send_json( $ret );
    }
    public static function ajax_sidebar_list() {
        $str_notallowed = __( 'Not allowed.', 'unlimited-page-sidebars' );
        $ret = array( 'message' => '', 'markup' => '' );
        if ( current_user_can( 'manage_options' ) ) {
            $sidebars = call_user_func( array( __CLASS__, 'get_sidebars' ) );
            $ret[ 'markup' ] = call_user_func( array( __CLASS__, 'list_items_markup' ), $sidebars );
        } else {
            $ret[ 'message' ] = $str_notallowed;
        }
        wp_send_json( $ret );
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

    // retrieve "post types" option
    public static function option_posttypes() {
        $ret = get_option( 'ups_posttypes' );
        if ( !( is_array( $ret ) && count( $ret ) ) ) {
            $ret = array( 'page' );
        }
        return $ret;
    }
    // retrieve deprecated "nsidebars" option
    public static function option_nsidebars() {
        return intval( '0' . get_option( 'ups_nsidebars' ) );
    }

    // auxiliar functions
    public static function get_sidebars() {
        return get_posts( array(
            'post_type' => 'custom_sidebar',
            'numberposts' => -1,
            'order' => 'ASC',
        ) );
    }
    public static function get_other_sidebars() {
        global $wp_registered_sidebars;
        $other_sidebars = array();
        foreach ( $wp_registered_sidebars as $k => $v ) :
            if ( stripos( $k, 'custom_sidebar_' ) !== 0 ) :
                $other_sidebars[ $k ] = $v;
            endif;
        endforeach;
        return $other_sidebars;
    }
    public static function list_items_markup( $sidebars ) {
        $ret = '';
        foreach ( $sidebars as $sidebar ) :
            $ret .= '<li data-sidebarid="' . esc_attr( $sidebar->ID ) . '"><span class="dashicons dashicons-minus" title="' . esc_attr( __( 'delete', 'unlimited-page-sidebars' ) ) . '"></span> <a href="#">' . $sidebar->post_title . '</a></li>';
        endforeach;
        return $ret;
    }

    // options screen markup
    public static function options_screen() {
        // get all post types
        $ptypes = get_post_types(
            array(
                'public' => true,
                'show_ui' => true,
                'show_in_nav_menus' => true
            ),
            'objects'
        );
        // retrieve registered sidebars
        $other_sidebars = call_user_func( array( __CLASS__, 'get_other_sidebars' ) );
        // retrieve saved custom sidebars (posts of type "custom_sidebar")
        $sidebars = call_user_func( array( __CLASS__, 'get_sidebars' ) );
        // buils custom sidebars' items list
        $list_markup = call_user_func( array( __CLASS__, 'list_items_markup' ), $sidebars );
        // retrieve "post types" option
        $posttypes = call_user_func( array( __CLASS__, 'option_posttypes' ) );
        // retrieve deprecated "nsidebars" option
        $total = call_user_func( array( __CLASS__, 'option_nsidebars' ) );
        ?>
        <div class="wrap unlimited_page_sidebars_options">
            <div id="icon-options-general" class="icon32"><br /></div>
            <h2><?php _e( 'Unlimited Page Sidebars Options', 'unlimited-page-sidebars' ); ?></h2>
            <form method="post" action="options.php">
                <?php settings_fields( 'pagesidebars_options' ); ?>

                <table class="form-table">
                <tbody>

                <tr valign="top">
                <th scope="row">
                    <?php _e( 'Sidebars:', 'unlimited-page-sidebars' ) ;?>
                </th>
                <td>
                    <?php if ( $sidebars ) : ?>
                        <ul class="unlimited_page_sidebars_list">
                            <?php echo $list_markup; ?>
                        </ul>
                        <p class="description unlimited_page_sidebars_empty" hidden="hidden">
                            <?php _e( 'No sidebar created yet.', 'unlimited-page-sidebars' ); ?>
                        </p>
                    <?php else : ?>
                        <ul class="unlimited_page_sidebars_list" hidden="hidden">
                        </ul>
                        <p class="description unlimited_page_sidebars_empty">
                            <?php _e( 'No sidebar created yet.', 'unlimited-page-sidebars' ); ?>
                        </p>
                    <?php endif; ?>
                    <hr />
                    <a href="#" class="button unlimited_page_sidebars_add"><span class="dashicons dashicons-plus-alt2"></span> <?php _e( 'New sidebar', 'unlimited-page-sidebars' ); ?></a>
                </td>
                </tr>

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

                <?php if ( $total ) : // deprecated option ?>
                    <tr valign="top">
                    <th scope="row">
                        <h3><?php _e( 'Deprecated Option', 'unlimited-page-sidebars' ); ?></h3>
                        <p class="description"><?php _e( 'As you had sidebars set up in a version prior than 0.2.5, this option is still displayed. After you create new custom sidebars above, you should transfer to them your widgets from the old deprecated ones. After that, you can come here and set this option to 0 (zero). Then this field must disappear for good.', 'unlimited-page-sidebars' ); ?></p>
                        <label for="nsidebars"><?php _e( 'Number of Optional Sidebars:', 'unlimited-page-sidebars' ) ;?></label>
                    </th>
                    <td>
                        <input type="number" name="ups_nsidebars" id="nsidebars" value="<?php echo $nsidebars ;?>" size="3" min="0" step="1" class="small-text" />
                    </td>
                    </tr>
                <?php endif; ?>

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
        global $post;

        // retrieve "post types" option
        $posttypes = call_user_func( array( __CLASS__, 'option_posttypes' ) );

        // is this a "singular" screen (page or post)?
        $is_singular_or_posts_page = is_singular();
        $this_singular = $post;
        if ( !$is_singular_or_posts_page ) {
            if ( is_home() && !is_front_page() ) {
                $is_singular_or_posts_page = true;
                $this_singular = get_post( get_option( 'page_for_posts' ) );
            }
        }
        // if it's a "singular" of one of the types specified
        if ( $is_singular_or_posts_page && in_array( $this_singular->post_type, $posttypes ) ) {
            // iterate over all sidebars
            foreach ( $swidgets as $sid => $widgets ) {
                // ignore inactive widgets sidebar
                if ( 'wp_inactive_widgets' == $sid ) continue;
                // ignore custom sidebars
                if ( strpos( $sid, 'custom_sidebar_' ) === 0 ) continue;
                // retrieve which custom sidebar should overwrite this default sidebar
                // for this page or post
                $sidebar_id = call_user_func(
                    array( __CLASS__, 'first_custom' ),
                    'sidebar_id_' . $sid,
                    $this_singular->ID
                );
                // if any
                if ( $sidebar_id ) {
                    $custom_key = 'custom_sidebar_' . $sidebar_id;
                    if ( array_key_exists( $custom_key, $swidgets ) ) {
                        // replace sidebar widgets
                        $swidgets[ $sid ] = $swidgets[ $custom_key ];
                    }
                }
            }
        }
        return $swidgets;
    }

    // create custom box in page/post edit screen
    public static function add_custom_box( $post_id ) {
        if ( function_exists( 'add_meta_box' ) ) {
            // retrieve "post types" option
            $posttypes = call_user_func( array( __CLASS__, 'option_posttypes' ) );
            // add a meta box for each post type
            foreach ( $posttypes as $ptype ) {
                add_meta_box(
                    'pageparentdiv',
                    __( 'Unlimited Page Sidebars', 'unlimited-page-sidebars' ),
                    array( __CLASS__, 'render_sidebars_meta_box' ),
                    $ptype,
                    'side',
                    'default'
                );
            }
        }
    }
    // custom box markup
    public static function render_sidebars_meta_box( $p ) {
        // retrieve registered sidebars
        $other_sidebars = call_user_func( array( __CLASS__, 'get_other_sidebars' ) );
        // retrieve saved custom sidebars (posts of type "custom_sidebar")
        $sidebars = call_user_func( array( __CLASS__, 'get_sidebars' ) );
        // if there are sidebaras and custom sidebars
        if ( $other_sidebars && $sidebars ) {
            // meta box markup
            echo '<input type="hidden" name="pagesidebars_noncename" id="pagesidebars_noncename" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
            ?>
            <p><strong><?php _e( 'Custom Sidebars', 'unlimited-page-sidebars' ); ?></strong></p>
            <?php
            $n = 0;
            foreach ( $other_sidebars as $k => $v ) {
                $n++;
                $sidebar_id = call_user_func(
                    array( __CLASS__, 'first_custom' ),
                    'sidebar_id_' . $v[ 'id' ],
                    $p->ID
                );
                $sidebar_id = intval( '0' . $sidebar_id );
                ?>
                <p>
                    <label for="sidebar_select_<?php echo $n; ?>">
                        <?php echo sprintf( __( 'Sidebar <em>%s</em>:', 'unlimited-page-sidebars' ), $v[ 'name' ] ); ?>
                    </label>
                    <br />
                    <select name="custom_sidebar_id[<?php echo esc_attr( $v[ 'id' ] ); ?>]" id="sidebar_select_<?php echo $n; ?>">
                        <option value="0">(<?php _e( 'Default', 'unlimited-page-sidebars' ); ?>)</option>
                        <?php foreach ( $sidebars as $sidebar ) : ?>
                            <option value="<?php echo $sidebar->ID; ?>"<?php if ( $sidebar->ID == $sidebar_id ) : ?> selected="selected"<?php endif; ?>><?php echo get_the_title( $sidebar->ID ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <?php
            }
        }
    }
    // save custom box data
    public static function save_postdata( $post_id ) {
        // verify this came from the our screen and with proper authorization,
        // because save_post can be triggered at other times
        if ( !array_key_exists( 'pagesidebars_noncename', $_POST ) ) {
            return $post_id;
        }
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
        if ( array_key_exists( 'custom_sidebar_id', $_POST ) ) {
            $custom = $_POST[ 'custom_sidebar_id' ];
            if ( is_array( $custom ) ) {
                foreach ( $custom as $k => $v ) {
                    call_user_func(
                        array( __CLASS__, 'save_custom' ),
                        'sidebar_id_' . $k,
                        $v,
                        $post_id
                    );
                }
            }
        }

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
    public static function save_custom( $field, $value, $pid ) {
        if ( $value ) {
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
        } else {
            delete_post_meta( $pid, '_pagesidebars_' . $field );
        }
    }
}

// RELEASE THE KRAKEN!
add_action( 'init', array( 'unlimited_page_sidebars', 'init' ) );

endif;
