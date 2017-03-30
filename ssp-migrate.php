<?php
/*
Plugin Name:        SSP Migrate
Plugin URI:         https://github.com/ymauray/ssp-migrate
GitHub Plugin URI:  https://github.com/ymauray/ssp-migrate
GitHub Branch:      master
Description:        Migrate from PowerPress to SSP
Version:            1.0.0
Author:             Yannick Mauray
Author URI:         https://frenchguy.ch
Contributors:
Yannick Mauray (Euterpia Radio)

Credits:

Copyright 2017 Yannick Mauray

License: GPL v3 (http://www.gnu.org/licenses/gpl-3.0.txt)
*/

/*
 * Encoding: UTF-8
*/

// Start up this plugin
if ( !class_exists( 'SSPMigrate' ) ) {
    class SSPMigrate {
        
        public $menu_id;

    	public function __construct() {
            load_plugin_textdomain( 'ssp-migrate' );

            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

            $this->capability = apply_filters( 'ssp_migrate_cap', 'manage_options' );
        }

        public function add_admin_menu() {
            $this->menu_id = add_management_page( __( 'SSP Migrate', 'ssp-migrate' ), __( 'SSP Migrate', 'ssp-migrate' ), $this->capability, 'ssp-migrate', array( $this, 'ssp_migrate_interface' ) );
        }

        public function ssp_migrate_interface() {
            global $wpdb;

            $sql = $wpdb->prepare( "SELECT tt.term_taxonomy_id, tt.term_id, t.name, t.slug FROM wp_term_taxonomy tt, wp_terms t WHERE tt.taxonomy = %s and t.term_id = tt.term_id ORDER BY t.name ASC;", "category" );
            $categories = $wpdb->get_results( $sql );

            $sql = $wpdb->prepare( "SELECT tt.term_taxonomy_id, tt.term_id, t.name, t.slug FROM wp_term_taxonomy tt, wp_terms t WHERE tt.taxonomy = %s and t.term_id = tt.term_id ORDER BY t.name ASC;", "series" );
            $series = $wpdb->get_results( $sql );

?>
<div id="message" class="updated fade" style="display:none"></div>
<div class="wrap ssp-migrate">
    <h2><?php _e( 'SSP Migrate', 'ssp-migrate' ); ?></h2>
<?php 
if ( ! empty( $_POST[ 'migrate-posts' ] ) && ! empty( $_REQUEST[ 'source-category' ] ) && ! empty( $_REQUEST[ 'target-series' ] ) ):
    $sql = $wpdb->prepare( "SELECT tr.object_id, p.ID FROM $wpdb->term_relationships tr, $wpdb->posts p WHERE tr.term_taxonomy_id = %d and p.ID = tr.object_id and p.post_type = %s order by p.ID asc", $_REQUEST[ 'source-category' ], 'post' );
    $posts = $wpdb->get_results( $sql );
    $count = 0;
    foreach ( $posts as $post ) {
        $sql = $wpdb->prepare( "UPDATE $wpdb->posts SET post_type = %s WHERE ID = %d", "podcast", $post->ID );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "SELECT pm.meta_value FROM $wpdb->postmeta pm WHERE pm.post_id = %d and pm.meta_key = %s", $post->ID, "enclosure" );
        $enclosure = $wpdb->get_row( $sql );
        $fields = split( "\n", $enclosure->meta_value );
        $enclosure = $fields[ 0 ];
        $filesize_raw = $fields[ 1 ];
        $tmp = unserialize( $fields[ 3 ] );
        $duration = $tmp[ "duration" ];
        $sql = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_value = %s WHERE post_id = %d AND meta_key = %s", $enclosure, $post->ID, "enclosure" );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_value = %s WHERE post_id = %d AND meta_key = %s", $enclosure, $post->ID, "audio_file" );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value ) VALUES ( %d, %s, %s )", $post->ID, "duration", $duration );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value ) VALUES ( %d, %s, %s )", $post->ID, "filesize_raw", $filesize_raw );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value ) VALUES ( %d, %s, %s )", $post->ID, "episode_type", "audio" );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value ) VALUES ( %d, %s, %s )", $post->ID, "filesize", $this->human_filesize( $filesize_raw ) );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value ) VALUES ( %d, %s, %s )", $post->ID, "date_recorded", "" );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value ) VALUES ( %d, %s, %s )", $post->ID, "explicit", "" );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value ) VALUES ( %d, %s, %s )", $post->ID, "block", "" );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "DELETE FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id = %d", $post->ID, $_REQUEST[ 'source-category' ] );
        $wpdb->query( $sql );
        $sql = $wpdb->prepare( "INSERT INTO $wpdb->term_relationships ( object_id, term_taxonomy_id, term_order ) VALUES ( %d, %d, 0 )", $post->ID, $_REQUEST[ 'target-series' ] );
        $wpdb->query( $sql );
        $count += 1;
    }
    $sql = $wpdb->prepare( "UPDATE $wpdb->term_taxonomy SET count = %d WHERE term_taxonomy_id = %d", $count, $_REQUEST[ 'target-series' ] );
    $wpdb->query( $sql );
    echo "All done !";
else:
?>
    <form method="post" action="">
        <?php wp_nonce_field( 'migrate-posts' ); ?>
        <fieldset>
        <ul id="ssp-migrate-settings" style="display: block;">
            <li>
                <label for="source-category">
                    <span class="label-responsive"><?php _e( 'PowerPress category', 'ssp-migrate' ); ?>&nbsp;:</span>
                    <select name="source-category" id="source-category" class="postform">
                        <?php foreach ( $categories as $category ): ?>
                        <option class="level-0" value="<?php echo $category->term_taxonomy_id; ?>"><?php echo $category->name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </li>
            <li>
                <label for="target-series">
                    <span class="label-responsive"><?php _e( 'SSP series', 'ssp-migrate' ); ?>&nbsp;:</span>
                    <select name="target-series" id="target-series" class="postform">
                        <?php foreach ( $series as $serie ): ?>
                        <option class="level-0" value="<?php echo $serie->term_taxonomy_id; ?>"><?php echo $serie->name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </li>
        </ul>
    	<p>
            <input type="submit" class="button" name="migrate-posts" id="migrate-posts" value="<?php _e( 'Migrate', 'ssp-migrate' ); ?>">
        </p>
    </form>
<?php
endif;
        }

        public function human_filesize( $bytes, $decimals = 2 ) {
            $size = array( 'B','kB','MB','GB','TB','PB','EB','ZB','YB' );
            $factor = floor( ( strlen( $bytes ) - 1 ) / 3 );
            return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$size[ $factor ];
        }
    }
}

add_action( 'init', function() {
    global $SSPMigrate;
    $SSPMigrate = new SSPMigrate();
} );
