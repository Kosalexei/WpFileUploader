<?php

	class WpFileUploader {
		/**
		 * @var array Список файлов, которые будут сохранены.
		 */
		private $files = [];

		/**
		 * @var string Путь сохранения файлов по-умолчанию
		 */
		private $path_to_save;

		/**
		 * WpFileUploader constructor.
		 *
		 * @param array $files Список файлов, которые будут сохранены.
		 */
		public function __construct( array $files ) {
			$this->files        = $files;
			$this->path_to_save = $this->generate_path_to_save();
		}

		/**
		 * Сохраняет файлы в директорию без сохранения информации в базу данных.
		 *
		 * @param string | null $path Путь, куда будут сохранены файлы.
		 *
		 * @return array Список путей сохраненный файлов.
		 */
		public function save_to_dir( $path = null ) {
			if ( $path === null ) {
				$path = $this->path_to_save;
			}

			$uploaded = [];

			try {
				foreach ( $this->files as $file ) {
					$path_filename_ext = $path . $file["name"];

					// Если такой файл с таким именем уже существует, то генерируем новое имя файла.
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
		 * Добавление файлов в Библиотеку файлов Wordpress.
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
		 * Сохранение информации о файлах в базе данных Wordpress.
		 * Файлы предварительно должны быть загружены на сервер.
		 *
		 * @param array $files Файлы, информацию о которых нужно сохранить в базу данных.
		 */
		private function save_to_db( array $files ) {
			foreach ( $files as $file ) {
				// Проверим тип поста, который мы будем использовать в поле 'post_mime_type'.
				$filetype = wp_check_filetype( basename( $file ), null );

				// Получим путь до директории загрузок.
				$wp_upload_dir = wp_upload_dir();

				// Подготовим массив с необходимыми данными для вложения.
				$attachment = array(
					'guid'           => $wp_upload_dir['url'] . '/' . basename( $file ),
					'post_mime_type' => $filetype['type'],
					'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				);

				// Вставляем запись в базу данных.
				$attach_id = wp_insert_attachment( $attachment, $file, 0 );

				// Подключим нужный файл, если он еще не подключен
				// wp_generate_attachment_metadata() зависит от этого файла.
				require_once( ABSPATH . 'wp-admin/includes/image.php' );

				// Создадим метаданные для вложения и обновим запись в базе данных.
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
				wp_update_attachment_metadata( $attach_id, $attach_data );
			}
		}

		/**
		 * Получить путь сохранения файлов по-умолчанию.
		 *
		 * @return string Путь сохранения файлов.
		 */
		public function get_path_to_save() {
			return $this->path_to_save;
		}

		/**
		 * Сгенерировать путь сохранения файлов по-умолчанию.
		 *
		 * @return string Путь сохранения файлов.
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
		 * @return string Новый путь до файла.
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