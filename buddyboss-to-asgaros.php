<?php
/**
 * Plugin Name: BuddyBoss to Asgaros Migrator
 * Description: Migrates forums from BuddyBoss to Asgaros Forum, handling existing data to prevent duplicates.
 * Version: 0.1
 * Author: Five Point Web Solutions + ChatGPT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class BuddyBoss_To_Asgaros_Migrator {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'wp_ajax_bb_to_asgaros_start_migration', [ $this, 'start_migration' ] );
        add_action( 'wp_ajax_bb_to_asgaros_process_batch', [ $this, 'process_batch' ] );
        add_action( 'wp_ajax_bb_to_asgaros_reset_migration', [ $this, 'reset_migration' ] );
    }

    public function add_admin_menu() {
        add_management_page(
            'BuddyBoss to Asgaros Migrator',
            'Forum Migrator',
            'manage_options',
            'bb-to-asgaros-migrator',
            [ $this, 'migration_page' ]
        );
    }

    public function migration_page() {
        // Get saved options or default values
        $batch_size = get_option( 'bb_to_asgaros_batch_size', 100 );
        $category_id = get_option( 'bb_to_asgaros_category_id', 496 );
        $migration_in_progress = get_option( 'bb_to_asgaros_migration_in_progress', false );
        ?>
        <div class="wrap">
            <h1>BuddyBoss to Asgaros Forum Migrator</h1>
            <form id="migration-form">
                <h2>Migration Options</h2>
                <p>Select the data you wish to migrate:</p>
                <label><input type="checkbox" name="migrate_forums" checked> Migrate Forums</label><br>
                <label><input type="checkbox" name="migrate_topics" checked> Migrate Topics</label><br>
                <label><input type="checkbox" name="migrate_replies" checked> Migrate Replies</label><br><br>

                <label for="batch_size">Batch Size:</label>
                <input type="number" name="batch_size" id="batch_size" value="<?php echo esc_attr( $batch_size ); ?>" min="1"><br><br>

                <label for="category_id">Asgaros Category ID:</label>
                <input type="number" name="category_id" id="category_id" value="<?php echo esc_attr( $category_id ); ?>" min="1"><br><br>

                <?php if ( $migration_in_progress ) : ?>
                    <p>A migration is in progress. Would you like to:</p>
                    <button type="button" id="resume-migration" class="button button-primary">Resume Migration</button>
                    <button type="button" id="reset-migration" class="button">Restart Migration</button>
                <?php else : ?>
                    <button type="button" id="start-migration" class="button button-primary">Start Migration</button>
                <?php endif; ?>
            </form>
            <h2>Migration Progress</h2>
            <div id="progress-bar" style="width: 100%; background-color: #e0e0e0; border-radius: 5px; margin-bottom: 10px;">
                <div id="progress-bar-fill" style="height: 20px; width: 0%; background-color: #4caf50; text-align: center; color: #fff; line-height: 20px; border-radius: 5px;">
                    0%
                </div>
            </div>
            <div id="migration-status" style="border: 1px solid #ccc; padding: 10px; height: 300px; overflow-y: scroll; background: #fff;"></div>
        </div>
        <style>
            /* Progress Bar Styles */
            #progress-bar {
                position: relative;
            }
            #progress-bar-fill {
                transition: width 0.25s;
            }
        </style>
        <script>
            jQuery(document).ready(function($) {
                var totalRecords = 0;
                var processedRecords = 0;

                function startMigration(resume = false) {
                    var formData = $('#migration-form').serializeArray();
                    var action = resume ? 'bb_to_asgaros_start_migration' : 'bb_to_asgaros_start_migration';
                    var data = {
                        action: action,
                        _ajax_nonce: '<?php echo wp_create_nonce( 'bb_to_asgaros_migration_nonce' ); ?>',
                        form_data: formData,
                        resume: resume ? 1 : 0
                    };
                    $('#migration-status').html('');
                    $('#progress-bar-fill').css('width', '0%').text('0%');
                    $('#start-migration, #resume-migration, #reset-migration').prop('disabled', true);
                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            totalRecords = response.data.total_records;
                            processedRecords = response.data.processed_records || 0;
                            var progressPercentage = Math.min( (processedRecords / totalRecords) * 100, 100 ).toFixed(2);
                            $('#progress-bar-fill').css('width', progressPercentage + '%').text(progressPercentage + '%');
                            processBatch(response.data.next_step);
                        } else {
                            $('#migration-status').append('<p>Error initializing migration: ' + response.data.message + '</p>');
                            $('#start-migration, #resume-migration, #reset-migration').prop('disabled', false);
                        }
                    });
                }

                $('#start-migration').on('click', function() {
                    startMigration(false);
                });

                $('#resume-migration').on('click', function() {
                    startMigration(true);
                });

                $('#reset-migration').on('click', function() {
                    if (confirm('Are you sure you want to restart the migration? This will start over from the beginning.')) {
                        var data = {
                            action: 'bb_to_asgaros_reset_migration',
                            _ajax_nonce: '<?php echo wp_create_nonce( 'bb_to_asgaros_migration_nonce' ); ?>'
                        };
                        $.post(ajaxurl, data, function(response) {
                            if (response.success) {
                                $('#migration-status').html('<p>Migration reset. You can now start a new migration.</p>');
                                $('#start-migration, #resume-migration, #reset-migration').prop('disabled', false);
                                location.reload();
                            } else {
                                $('#migration-status').append('<p>Error resetting migration: ' + response.data.message + '</p>');
                                $('#start-migration, #resume-migration, #reset-migration').prop('disabled', false);
                            }
                        });
                    }
                });

                function processBatch(step) {
                    var data = {
                        action: 'bb_to_asgaros_process_batch',
                        step: step,
                        _ajax_nonce: '<?php echo wp_create_nonce( 'bb_to_asgaros_migration_nonce' ); ?>'
                    };
                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            processedRecords += response.data.processed_records;
                            var progressPercentage = Math.min( (processedRecords / totalRecords) * 100, 100 ).toFixed(2);
                            $('#progress-bar-fill').css('width', progressPercentage + '%').text(progressPercentage + '%');
                            $('#migration-status').append('<p>' + response.data.message + '</p>');
                            $('#migration-status').scrollTop($('#migration-status')[0].scrollHeight);
                            if (response.data.next_step !== 'done') {
                                processBatch(response.data.next_step);
                            } else {
                                $('#migration-status').append('<p>Migration completed successfully.</p>');
                                $('#start-migration, #resume-migration, #reset-migration').prop('disabled', false);
                                // Reset migration state
                                $.post(ajaxurl, {
                                    action: 'bb_to_asgaros_reset_migration',
                                    _ajax_nonce: '<?php echo wp_create_nonce( 'bb_to_asgaros_migration_nonce' ); ?>'
                                }, function() {
                                    // Reload the page to reflect the reset state
                                    location.reload();
                                });
                            }
                        } else {
                            $('#migration-status').append('<p>Error during migration: ' + response.data.message + '</p>');
                            $('#start-migration, #resume-migration, #reset-migration').prop('disabled', false);
                        }
                    });
                }
            });
        </script>
        <?php
    }

    public function start_migration() {
        // Check nonce for security
        check_ajax_referer( 'bb_to_asgaros_migration_nonce' );

        global $wpdb;

        $resume = isset( $_POST['resume'] ) && $_POST['resume'] == 1;

        if ( ! $resume ) {
            // Retrieve and sanitize form data
            $form_data = isset( $_POST['form_data'] ) ? $_POST['form_data'] : [];
            $options = [];
            foreach ( $form_data as $field ) {
                $options[ $field['name'] ] = sanitize_text_field( $field['value'] );
            }

            // Save options
            $migrate_forums = isset( $options['migrate_forums'] ) ? true : false;
            $migrate_topics = isset( $options['migrate_topics'] ) ? true : false;
            $migrate_replies = isset( $options['migrate_replies'] ) ? true : false;
            $batch_size = isset( $options['batch_size'] ) ? max( 1, intval( $options['batch_size'] ) ) : 100;
            $category_id = isset( $options['category_id'] ) ? intval( $options['category_id'] ) : 496;

            // Store options in the database
            update_option( 'bb_to_asgaros_migrate_forums', $migrate_forums );
            update_option( 'bb_to_asgaros_migrate_topics', $migrate_topics );
            update_option( 'bb_to_asgaros_migrate_replies', $migrate_replies );
            update_option( 'bb_to_asgaros_batch_size', $batch_size );
            update_option( 'bb_to_asgaros_category_id', $category_id );

            // Initialize migration steps based on selected options
            $steps = [];
            if ( $migrate_forums ) $steps[] = 'forums';
            if ( $migrate_topics ) $steps[] = 'topics';
            if ( $migrate_replies ) $steps[] = 'replies'; // Changed from 'posts' to 'replies'

            if ( empty( $steps ) ) {
                wp_send_json_error( [ 'message' => 'No migration options selected.' ] );
                return;
            }

            // Save steps in order
            update_option( 'bb_to_asgaros_migration_steps', $steps );
            update_option( 'bb_to_asgaros_current_step_index', 0 );

            // Set $current_step_index to 0
            $current_step_index = 0;

            // Reset offsets
            foreach ( $steps as $step ) {
                delete_option( 'bb_to_asgaros_migration_offset_' . $step );
            }

            // Set migration in progress
            update_option( 'bb_to_asgaros_migration_in_progress', true );
        } else {
            // Resuming migration, retrieve existing steps and progress
            $steps = get_option( 'bb_to_asgaros_migration_steps', [] );
            $current_step_index = get_option( 'bb_to_asgaros_current_step_index', 0 );
        }

        // Calculate total records to migrate
        $total_records = 0;

        if ( in_array( 'forums', $steps ) ) {
            $total_forums = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'forum'" );
            $total_records += intval( $total_forums );
        }

        if ( in_array( 'topics', $steps ) ) {
            $total_topics = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'topic'" );
            $total_records += intval( $total_topics );
        }

        if ( in_array( 'replies', $steps ) ) {
            $total_replies = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'reply'" );
            $total_records += intval( $total_replies );
        }

        update_option( 'bb_to_asgaros_total_records', $total_records );

        // Calculate processed records so far
        $processed_records = 0;
        foreach ( $steps as $index => $step_name ) {
            $offset = get_option( 'bb_to_asgaros_migration_offset_' . $step_name, 0 );
            $processed_records += $offset;
        }

        wp_send_json_success( [
            'next_step'         => $steps[ $current_step_index ],
            'total_records'     => $total_records,
            'processed_records' => $processed_records,
        ] );
    }

	public function process_batch() {
		// Check nonce for security
		check_ajax_referer( 'bb_to_asgaros_migration_nonce' );

		global $wpdb;

		// Retrieve options
		$steps = get_option( 'bb_to_asgaros_migration_steps', [] );
		$current_step_index = get_option( 'bb_to_asgaros_current_step_index', 0 );
		$batch_size = get_option( 'bb_to_asgaros_batch_size', 100 );
		$category_id = get_option( 'bb_to_asgaros_category_id', 496 );
		$total_records = get_option( 'bb_to_asgaros_total_records', 0 );

		if ( isset( $_POST['step'] ) ) {
			$step = sanitize_text_field( $_POST['step'] );
		} else {
			wp_send_json_error( [ 'message' => 'Step parameter missing.' ] );
			return;
		}

		$offset_option_name = 'bb_to_asgaros_migration_offset_' . $step;
		$offset = get_option( $offset_option_name, 0 );

		$message = '';

		// Store logs in an array to return in the AJAX response
		$logs = [];
		$processed_records = 0;

		try {
			switch ( $step ) {
                case 'forums':
                    // Migrate forums
                    $forums = $wpdb->get_results( $wpdb->prepare( "
                        SELECT f.ID as id, f.post_title as name, f.post_content as description, f.post_name as slug
                        FROM {$wpdb->posts} AS f
                        WHERE f.post_type = 'forum'
                        ORDER BY f.ID ASC
                        LIMIT %d OFFSET %d
                    ", $batch_size, $offset ) );

                    if ( ! empty( $forums ) ) {
                        foreach ( $forums as $forum ) {
                            // Sanitize and truncate the description
                            $max_desc_length = 255;
                            $description = wp_kses_post( $forum->description );
                            $description = mb_substr( $description, 0, $max_desc_length );

                            // Sanitize and truncate the name and slug
                            $max_name_length = 255;
                            $name = sanitize_text_field( $forum->name );
                            $name = mb_substr( $name, 0, $max_name_length );

                            $max_slug_length = 200;
                            $slug = sanitize_title( $forum->slug );
                            $slug = mb_substr( $slug, 0, $max_slug_length );

                            // Check if the forum already exists
                            $existing_forum = $wpdb->get_var( $wpdb->prepare(
                                "SELECT id FROM {$wpdb->prefix}forum_forums WHERE id = %d",
                                $forum->id
                            ) );

                            if ( $existing_forum ) {
                                // Update existing forum
                                $result = $wpdb->update(
                                    $wpdb->prefix . 'forum_forums',
                                    [
                                        'name'        => $name,
                                        'description' => $description,
                                        'parent_id'   => $category_id,
                                        'slug'        => $slug,
                                    ],
                                    [ 'id' => $forum->id ]
                                );
                                if ( $result === false ) {
                                    $logs[] = 'Database error updating forum ID ' . $forum->id . ': ' . $wpdb->last_error;
                                    throw new Exception( 'Database error: ' . $wpdb->last_error );
                                } else {
                                    $logs[] = 'Updated existing forum ID ' . $forum->id;
                                }
                            } else {
                                // Insert new forum
                                $result = $wpdb->insert( $wpdb->prefix . 'forum_forums', [
                                    'id'          => $forum->id, // Use the original ID
                                    'name'        => $name,
                                    'description' => $description,
                                    'parent_id'   => $category_id,
                                    'slug'        => $slug,
                                ] );
                                if ( $result === false ) {
                                    $logs[] = 'Database error inserting forum ID ' . $forum->id . ': ' . $wpdb->last_error;
                                    throw new Exception( 'Database error: ' . $wpdb->last_error );
                                } else {
                                    $logs[] = 'Inserted new forum ID ' . $forum->id;
                                }
                            }
                            $processed_records++;
                        }
                        $offset += count( $forums );
                        update_option( $offset_option_name, $offset );
                        $message = 'Migrating forums... Processed ' . $offset . ' forums so far.';
                        $next_step = $step;
                    } else {
                        // Reset offset and move to next step
                        delete_option( $offset_option_name );
                        $current_step_index++;
                        update_option( 'bb_to_asgaros_current_step_index', $current_step_index );
                        $next_step = isset( $steps[ $current_step_index ] ) ? $steps[ $current_step_index ] : 'done';
                        $message = 'Forums migration completed.';
                    }
                    break;

                case 'topics':
                    // Migrate topics
                    $topics = $wpdb->get_results( $wpdb->prepare( "
                        SELECT t.ID as id, t.post_title as name, t.post_name as slug, t.post_parent as parent_id
                        FROM {$wpdb->posts} AS t
                        WHERE t.post_type = 'topic'
                        ORDER BY t.ID ASC
                        LIMIT %d OFFSET %d
                    ", $batch_size, $offset ) );

                    if ( ! empty( $topics ) ) {
                        foreach ( $topics as $topic ) {
                            // Sanitize and truncate the name and slug
                            $max_name_length = 255;
                            $name = sanitize_text_field( $topic->name );
                            $name = mb_substr( $name, 0, $max_name_length );

                            $max_slug_length = 200;
                            $slug = sanitize_title( $topic->slug );
                            $slug = mb_substr( $slug, 0, $max_slug_length );

                            // Check if the topic already exists
                            $existing_topic = $wpdb->get_var( $wpdb->prepare(
                                "SELECT id FROM {$wpdb->prefix}forum_topics WHERE id = %d",
                                $topic->id
                            ) );

                            if ( $existing_topic ) {
                                // Update existing topic
                                $result = $wpdb->update(
                                    $wpdb->prefix . 'forum_topics',
                                    [
                                        'name'      => $name,
                                        'slug'      => $slug,
                                        'parent_id' => $topic->parent_id,
                                    ],
                                    [ 'id' => $topic->id ]
                                );
                                if ( $result === false ) {
                                    $logs[] = 'Database error updating topic ID ' . $topic->id . ': ' . $wpdb->last_error;
                                    throw new Exception( 'Database error: ' . $wpdb->last_error );
                                } else {
                                    $logs[] = 'Updated existing topic ID ' . $topic->id;
                                }
                            } else {
                                // Insert new topic
                                $result = $wpdb->insert( $wpdb->prefix . 'forum_topics', [
                                    'id'        => $topic->id, // Use the original ID
                                    'name'      => $name,
                                    'slug'      => $slug,
                                    'parent_id' => $topic->parent_id,
                                ] );
                                if ( $result === false ) {
                                    $logs[] = 'Database error inserting topic ID ' . $topic->id . ': ' . $wpdb->last_error;
                                    throw new Exception( 'Database error: ' . $wpdb->last_error );
                                } else {
                                    $logs[] = 'Inserted new topic ID ' . $topic->id;
                                }
                            }
                            $processed_records++;
                        }
                        $offset += count( $topics );
                        update_option( $offset_option_name, $offset );
                        $message = 'Migrating topics... Processed ' . $offset . ' topics so far.';
                        $next_step = $step;
                    } else {
                        // Reset offset and move to next step
                        delete_option( $offset_option_name );
                        $current_step_index++;
                        update_option( 'bb_to_asgaros_current_step_index', $current_step_index );
                        $next_step = isset( $steps[ $current_step_index ] ) ? $steps[ $current_step_index ] : 'done';
                        $message = 'Topics migration completed.';
                    }
                    break;

				case 'replies':
					// Migrate replies

					// Adjust the offset to reprocess the last few records, but only if we're not at the end
					if ( $offset > 0 && $offset < $total_records - 5 ) {
						$offset = max( 0, $offset - 5 ); // Go back 5 records to reprocess
					}

					$posts = $wpdb->get_results( $wpdb->prepare( "
						SELECT p.ID as id, p.post_content as text, p.post_parent as topic_id, t.post_parent as forum_id, p.post_date as date, p.post_author as author_id
						FROM {$wpdb->posts} AS p
						INNER JOIN {$wpdb->posts} AS t ON p.post_parent = t.ID AND t.post_type = 'topic'
						WHERE p.post_type = 'reply'
						ORDER BY p.ID ASC
						LIMIT %d OFFSET %d
					", $batch_size, $offset ) );

					if ( ! empty( $posts ) ) {
						foreach ( $posts as $post ) {
							// Sanitize and prepare the text
							$text = wp_kses_post( $post->text );

							// Check if the post already exists
							$existing_post = $wpdb->get_var( $wpdb->prepare(
								"SELECT id FROM {$wpdb->prefix}forum_posts WHERE id = %d",
								$post->id
							) );

							if ( $existing_post ) {
								// Update existing post
								$result = $wpdb->update(
									$wpdb->prefix . 'forum_posts',
									[
										'text'       => $text,
										'parent_id'  => $post->topic_id,
										'forum_id'   => $post->forum_id,
										'date'       => $post->date,
										'author_id'  => $post->author_id,
									],
									[ 'id' => $post->id ]
								);
								if ( $result === false ) {
									$logs[] = 'Database error updating reply ID ' . $post->id . ': ' . $wpdb->last_error;
									throw new Exception( 'Database error: ' . $wpdb->last_error );
								} else {
									$logs[] = 'Updated existing reply ID ' . $post->id;
								}
							} else {
								// Insert new post
								$result = $wpdb->insert( $wpdb->prefix . 'forum_posts', [
									'id'         => $post->id, // Use the original ID
									'text'       => $text,
									'parent_id'  => $post->topic_id,
									'forum_id'   => $post->forum_id,
									'date'       => $post->date,
									'author_id'  => $post->author_id,
								] );
								if ( $result === false ) {
									$logs[] = 'Database error inserting reply ID ' . $post->id . ': ' . $wpdb->last_error;
									throw new Exception( 'Database error: ' . $wpdb->last_error );
								} else {
									$logs[] = 'Inserted new reply ID ' . $post->id;
								}
							}
							$processed_records++;
						}
						$offset += count( $posts );
						update_option( $offset_option_name, $offset );
						$message = 'Migrating replies... Processed ' . $offset . ' replies so far.';
						$next_step = $step;
					} else {
						// Reset offset and finish migration
						delete_option( $offset_option_name );
						$current_step_index++;
						update_option( 'bb_to_asgaros_current_step_index', $current_step_index );
						$next_step = isset( $steps[ $current_step_index ] ) ? $steps[ $current_step_index ] : 'done';
						$message = 'Replies migration completed.';
					}
					break;

				default:
					$next_step = 'done';
					$message = 'Migration completed successfully.';
					// Reset migration state
					update_option( 'bb_to_asgaros_migration_in_progress', false );
					break;
			}

			wp_send_json_success( [
				'message'           => implode( '<br>', $logs ) . '<br>' . $message,
				'next_step'         => $next_step,
				'processed_records' => $processed_records,
			] );
		} catch ( Exception $e ) {
			$logs[] = 'Error: ' . $e->getMessage();
			// Increment offset based on actual records processed to prevent infinite loop
			$offset += $processed_records;
			update_option( $offset_option_name, $offset );
			wp_send_json_success( [
				'message'           => implode( '<br>', $logs ) . '<br>An error occurred but migration is continuing: ' . $e->getMessage(),
				'next_step'         => $step,
				'processed_records' => $processed_records,
			] );
		}
	}

    public function reset_migration() {
        // Check nonce for security
        check_ajax_referer( 'bb_to_asgaros_migration_nonce' );

        // Delete all migration-related options
        delete_option( 'bb_to_asgaros_migration_steps' );
        delete_option( 'bb_to_asgaros_current_step_index' );
        delete_option( 'bb_to_asgaros_migration_in_progress' );
        delete_option( 'bb_to_asgaros_total_records' );

        // Also delete offsets for all steps
        $steps = [ 'forums', 'topics', 'replies' ];
        foreach ( $steps as $step ) {
            delete_option( 'bb_to_asgaros_migration_offset_' . $step );
        }

        wp_send_json_success( [ 'message' => 'Migration reset successfully.' ] );
    }
}

new BuddyBoss_To_Asgaros_Migrator();
