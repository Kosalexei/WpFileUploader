<?php

	/**
	 * Class WpFileUploader.
	 */
	class WpFileUploader {
		/**
		 * @var array List of files to be saved.
		 */
		private $files = [];

		/**
		 * @var string The path to save files by default.
		 */
		private $path_to_save;

		/**
		 * WpFileUploader constructor.
		 *
		 * @param array $files List of files to be saved.
		 */
		public function __construct( array $files ) {
			$this->files        = $files;
			$this->path_to_save = $this->generate_path_to_save();
		}

		/**
		 * Saves files to a directory without saving information to the database.
		 *
		 * @param string|null $path The path where the files will be saved.
		 *
		 * @return array List of paths saved files.
		 */
		public function save_to_dir( $path = null ) {
			if ( $path === null ) {
				$path = $this->path_to_save;
			}

			$uploaded = [];

			try {
				foreach ( $this->files as $file ) {
					$path_filename_ext = $path . $file["name"];

					// If such a file with the same name already exists, then generate a new file name.
					if ( file_exists( $path_filename_ext ) ) {
						$path_filename_ext = $this->generate_new_filename( $path, $path_filename_ext );
					}

					move_uploaded_file( $file["tmp_name"], $path_filename_ext );
					array_push( $uploaded, $path_filename_ext );
				}
			} catch ( Exception $exception ) {
				echo $exception->getMessage();
			}

			return $uploaded;
		}

		/**
		 * Add files to the Wordpress File Library.
		 */
		public function save_to_wordpress() {
			try {
				$uploaded = $this->save_to_dir();
				$this->save_to_db( $uploaded );
			} catch ( Exception $exception ) {
				echo $exception->getMessage();
			}
		}

		/**
		 * Save information about files in Wordpress database.
		 * Files must first be uploaded to the server.
		 *
		 * @param array $files Files whose information you want to save to the database.
		 */
		private function save_to_db( array $files ) {
			foreach ( $files as $file ) {
				// Check the post type that we will use in the 'post_mime_type' field.
				$filetype = wp_check_filetype( basename( $file ), null );

				// Get the path to the downloads directory.
				$wp_upload_dir = wp_upload_dir();

				// Prepare an array with the necessary data for the attachment.
				$attachment = array(
					'guid'           => $wp_upload_dir['url'] . '/' . basename( $file ),
					'post_mime_type' => $filetype['type'],
					'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				);

				// Insert a record into the database.
				$attach_id = wp_insert_attachment( $attachment, $file, 0 );

				// Connect the desired file if it is not already connected.
				// wp_generate_attachment_metadata() depends on this file.
				require_once( ABSPATH . 'wp-admin/includes/image.php' );

				// Create metadata for the attachment and update the record in the database.
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
				wp_update_attachment_metadata( $attach_id, $attach_data );
			}
		}

		/**
		 * Get the path to save files by default.
		 *
		 * @return string The path to save files.
		 */
		public function get_path_to_save() {
			return $this->path_to_save;
		}

		/**
		 * Generate the path to save files by default.
		 *
		 * @return string The path to save files.
		 */
		private function generate_path_to_save() {
			$wp_upload_dir = wp_upload_dir();
			$path_to_save  = sprintf( '%s/', $wp_upload_dir['path'] );

			return $path_to_save;
		}

		/**
		 * @param string $path Путь хранения файлов.
		 * @param string $path_filename_ext Путь файла.
		 *
		 * @return string New path to the file.
		 */
		private function generate_new_filename( $path, $path_filename_ext ) {
			$new_path_filename_ext = $path_filename_ext;

			$count = 2;
			while ( file_exists( $new_path_filename_ext ) ) {
				$path_parts = pathinfo( $path_filename_ext );
				$filename   = $path_parts["filename"];
				$extension  = $path_parts["extension"];

				$new_path_filename_ext = sprintf( '%1$s%2$s-%3$s.%4$s', $path, $filename, $count, $extension );
				$count ++;
			}

			return $new_path_filename_ext;
		}
	}