<?php

function gc_recording_identification_meta_box ( $recording ) { ?>

    <?php
    $recording_parent_id = $recording->post_parent;
    $recording_grandparent_id = ( $recording_parent_id !== 0 ) ? get_post( $recording_parent_id )->post_parent : 0;

    wp_localize_script( 'synchronize-chant-variant-with-chant', 'recording_parent_id', array( $recording_parent_id ));
    ?>

    <!--    Chant dropdown -->
    <div>
        <label for="recording-grandparent"><b><?php _e( 'Chant', 'example' )?></b> - <?php _e( 'Select the chant in the recording. If the chant is not available, it must be created as a "Chant" post.', 'example' ); ?></label>
        <br>
        <select id="recording-grandparent" name="recording-grandparent">
            <option value=""></option>
    <!--            Fill with available chant posts. -->
            <?php
            $all_chants = get_posts( array( 'post_type' => 'gc_chant' ));

            foreach ($all_chants as $chant) { ?>
                <option value="<?= $chant->ID ?>"
                        <?php if ( $recording_grandparent_id === $chant->ID ) { ?>selected<?php } ?>
                    ><?= $chant->post_title ?></option>
            <?php } ?>

        </select>
        <?php if ( $recording_grandparent_id !== 0 ) { ?>
        <p>Edit currently selected chant: <a href="<?=get_edit_post_link( $recording_grandparent_id )?>"><?= get_post( $recording_grandparent_id )->post_title ?></a></p>
        <?php } ?>
    </div>

    <!--    Chant Variant dropdown -->
    <?php wp_nonce_field( 'recording-parent-action', 'recording_parent_nonce' ); ?>

    <div>
        <label for="recording-parent"><b><?php _e( 'Chant Variant', 'example' )?></b> - <?php _e( 'Select the chant variant in the recording. If the chant variant is not available, it must be created as a "Chant Variant" post.', 'example' ); ?></label>
        <br>
        <select id="recording-parent" name="recording-parent">
            <option value=""></option>

    <!--            Fill the dropdown with chant variants that are children of the recording's grandparent chant, and select the currently selected chant variant parent. -->
            <?php
            $possible_chant_variants = get_posts( array(
                'post_type' => 'gc_chant_variant',
                'post_parent' => $recording_grandparent_id
            ));

            foreach ($possible_chant_variants as $chant_variant) { ?>
                <option value="<?= $chant_variant->ID ?>"
                        <?php if ( get_post( $recording ) -> post_parent === $chant_variant->ID ) { ?>selected<?php } ?>
                    ><?= $chant_variant->post_title ?></option>
            <?php } ?>
            ?>
        </select>

        <?php if ( $recording_parent_id !== 0 ) { ?>
        <p>Edit currently selected chant variant: <a href="<?= get_edit_post_link( $recording_parent_id )?>"><?= get_post( $recording_parent_id )->post_title ?></a></p>
        <?php } ?>
    </div>

<?php }

function gc_recording_display_file_editable_meta_box ( $recording ) {
    gc_recording_display_file( $recording, true );
}

function gc_recording_information_meta_box( $recording ) { ?>
    <?php wp_nonce_field( 'artist-name-action', 'artist_name_nonce' ) ?>

    <div>
        <label for="artist-name"><b><?php _e( 'Artist', 'example' )?></b> - <?php _e( 'Enter the name of the artist who performs in this recording.', 'example' ); ?></label>
        <br>
        <input type="text" name="artist-name" id="artist-name" value="<?php echo esc_attr(get_post_meta( $recording->ID, 'artist-name', true ))?>">
    </div>
<?php }

function gc_recordings_add_meta_boxes() {
    add_meta_box(
        'chant-identification-meta-box',
        esc_html__( 'Chant Identification', 'example' ),
        'gc_recording_identification_meta_box',
        'gc_recording',
        'normal',
        'default'
    );

    add_meta_box(
        'recording-file-meta-box',
        esc_html__( 'Recording File', 'example' ),
        'gc_recording_display_file_editable_meta_box',
        'gc_recording',
        'normal',
        'default'
    );

    add_meta_box(
        'recording-information-meta-box',
        esc_html__( 'Recording Information', 'example' ),
        'gc_recording_information_meta_box',
        'gc_recording',
        'normal',
        'default'
    );
}

function gc_recording_remove_file ( $post_id, $meta_value ) {
    delete_post_meta( $post_id, 'recording-file', $meta_value);
    unlink( $meta_value['file'] );
}

function gc_recording_upload_new_file( $post_id ) {
    $upload = wp_upload_bits( $_FILES['recording-file']['name'], null, file_get_contents( $_FILES['recording-file']['tmp_name'] ));

    if ( ! $upload['error'] ) {
        update_post_meta( $post_id, 'recording-file', $upload );
    } else {
        print 'Upload failed. Error: ' . $upload['error'];
    }
}

function gc_recording_update_file( $post_id ) {
    // Security checks
    $recording_file_nonce = 'recording_file_nonce';
    $recording_file_action = 'recording-file';

    if ( !gc_verify_nonce( $recording_file_nonce, $recording_file_action )) {
        return $post_id;
    }

    $meta_value = get_post_meta($post_id, 'recording-file', true);

    // Check if a new file has been chosen
    if ( !empty ( $_FILES['recording-file']['name'] )) {

        $supported_file_types = array('audio/mpeg');
        $uploaded_file_type = wp_check_filetype(basename($_FILES['recording-file']['name']))['type'];

        if ( ! in_array( $uploaded_file_type, $supported_file_types )) {
            return $post_id;
        }

        // Remove old file and metadata
        if ( $meta_value !== "" ) {
            gc_recording_remove_file( $post_id, $meta_value );
        }

        gc_recording_upload_new_file( $post_id );

    // If no new file has been chosen, check if uploaded file should be deleted. If so, delete.
    } else if ( $meta_value !== "" && $_POST[ 'recording-file-url' ] === "" ) {
        gc_recording_remove_file( $post_id, $meta_value );
    }
}

function save_recordings_post_type_meta( $post_id, $post ) {
    gc_recording_update_file( $post_id, $post );
    gc_save_single_meta( $post_id, $post, 'artist_name_nonce', 'artist-name', 'sanitize_text_field' );
    gc_save_post_parent( $post_id, 'recording_parent_nonce', 'recording-parent', 'sanitize_text_field' );
}

function gc_recording_add_enctype_to_form_tag() {
    echo ' enctype="multipart/form-data"';
}

add_action( 'post_edit_form_tag' , 'gc_recording_add_enctype_to_form_tag' );

function gc_recording_unlink_file( $post_id ) {
    if ( get_post( $post_id )->post_type === 'gc_recording' ) {
        $meta_value = get_post_meta( $post_id, 'recording-file', true );
        unlink( $meta_value['file'] );
    }
}

add_action( 'before_delete_post', 'gc_recording_unlink_file' );

