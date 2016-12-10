<?php
/**
 * Wildcard Helper Classes - Plug-in Installer
 *
 * a generic installer for MyBB Plugins that accepts
 * a data file and performs installation functions
 * in a non-destructive way according to the provided
 * information
 *
 */

class WildcardPluginInstaller
{
	/*
	 * @const  version
	 */
	const VERSION = '1.2';

	/*
	 * @var object a copy of the MyBB db object
	 */
	private $db;

	/*
	 * @var array the table data
	 */
	protected $tables = array();

	/*
	 * @var array the table names
	 */
	protected $table_names = array();

	/*
	 * @var array the column data
	 */
	protected $columns = array();

	/*
	 * @var array the settings data
	 */
	protected $settings = array();

	/*
	 * @var array the setting names
	 */
	protected $setting_names = array();

	/*
	 * @var array the setting group data
	 */
	protected $settinggroups = array();

	/*
	 * @var array the setting group names
	 */
	protected $settinggroup_names = array();

	/*
	 * @var array the template data
	 */
	protected $templates = array();

	/*
	 * @var array the template names
	 */
	protected $template_names = array();

	/*
	 * @var array the template group data
	 */
	protected $templategroups = array();

	/*
	 * @var array the template group names
	 */
	protected $templategroup_names = array();

	/*
	 * @var array the template data
	 */
	protected $style_sheets = array();

	/*
	 * @var array the template names
	 */
	protected $style_sheet__names = array();

	/*
	 * @var array the image data
	 */
	protected $images = array();

	/*
	 * load the installation data and prepare for anything
	 *
	 * @param  string path to the install data
	 * @return void
	 */
	public function __construct($path)
	{
		if (!trim($path) ||
			!file_exists($path)) {
			return;
		}

		global $lang, $db;
		require_once $path;
		foreach (array('tables', 'columns', 'settings', 'templates', 'images', 'style_sheets') as $key) {
			if (!is_array($$key) ||
				empty($$key)) {
				continue;
			}

			$this->$key = $$key;
			switch ($key) {
			case 'style_sheets':
				// stylesheets need the extension appended
				foreach (array_keys($style_sheets) as $name) {
					$this->style_sheet_names[] = $name . '.css';
				}
				break;
			case 'settings':
				$this->settinggroup_names = array_keys($settings);
				foreach ($settings as $group => $info) {
					foreach ($info['settings'] as $name => $setting) {
						$this->setting_names[] = $name;
					}
				}
				break;
			case 'templates':
				$this->templategroup_names = array_keys($templates);
				foreach ($templates as $group => $info) {
					foreach ($info['templates'] as $name => $template) {
						$this->template_names[] = $name;
					}
				}
				break;
			case 'columns':
			case 'images':
				break;
			default:
				$singular = substr($key, 0, strlen($key) - 1);
				$property = "{$singular}_names";
				$this->$property = array_keys($$key);
				break;
			}
		}

		// snag a copy of the db object
		$this->db = $db;
	}

	/*
	 * install all the elements stored in the installation data file
	 *
	 * @return void
	 */
	public function install()
	{
		$this->add_tables();
		$this->add_columns();
		$this->add_settings();
		$this->add_templates();
		$this->add_style_sheets();
		$this->add_images();
	}

	/*
	 * uninstall all elements as provided in the install data
	 *
	 * @return void
	 */
	public function uninstall()
	{
		$this->remove_tables();
		$this->remove_columns();
		$this->remove_('settinggroups', 'name', $this->settinggroup_names);
		$this->remove_('settings', 'name', $this->setting_names);
		$this->remove_('templategroups', 'prefix', $this->templategroup_names);
		$this->remove_('templates', 'title', $this->template_names);
		$this->remove_style_sheets();
		rebuild_settings();
	}

	/*
	 * create a correctly collated table from an array of options
	 *
	 * @param  string table name without prefix
	 * @param  array the columns
	 * @return void
	 */
	private function add_table($table, $columns)
	{
		static $collation;
		if (!isset($collation) ||
			strlen($collation) == 0) {
			// only build collation for the first table
			$collation = $this->db->build_create_table_collation();
		}

		// build the column list
		$sep = $column_list = '';
		foreach ($columns as $title => $definition) {
			$column_list .= "{$sep}{$title} {$definition}";
			$sep = ',';
		}

		// create the table if it doesn't already exist
		if (!$this->table_exists($table)) {
			$table =  TABLE_PREFIX . $table;
			$this->db->write_query("CREATE TABLE {$table} ({$column_list}) ENGINE={$this->db->table_type}{$collation};");
		}
	}

	/*
	 * create multiple tables from stored info
	 *
	 * @param  array database tables and their columns
	 * @return void
	 */
	public function add_tables()
	{
		if (!is_array($this->tables) ||
			empty($this->tables)) {
			return false;
		}

		foreach ($this->tables as $table => $columns) {
			if ($this->table_exists($table)) {
				// if it already exists, just check that all the columns are present (and add them if not)
				$this->add_columns(array($table => $columns));
			} else {
				$this->add_table($table, $columns);
			}
		}
	}

	/*
	 * drop multiple database tables
	 *
	 * @return void
	 */
	public function remove_tables()
	{
		if (!is_array($this->table_names) ||
			empty($this->table_names)) {
			return;
		}

		$drop_list = implode(', ' . TABLE_PREFIX, $this->table_names);
		$this->db->drop_table($drop_list);
	}

	/*
	 * add columns in the list to a table (if they do not already exist)
	 *
	 * @param array tables and columns
	 * @return void
	 */
	public function add_columns($columns = '')
	{
		if (!is_array($columns) ||
			empty($columns)) {
			$columns = $this->columns;
		}

		foreach ($columns as $table => $all_columns) {
			$sep = $added_columns = '';
			foreach ($all_columns as $title => $definition) {
				if (!$this->field_exists($table, $title)) {
					$added_columns .= "{$sep}{$title} {$definition}";
					$sep = ', ADD ';
				}
			}
			if (strlen($added_columns) > 0) {
				// trickery, again
				$this->db->add_column($table, $added_columns, '');
			}
		}
	}

	/*
	 * drop multiple listed columns
	 *
	 * @param array an associative array of tables and columns
	 * @return void
	 */
	public function remove_columns()
	{
		if (!is_array($this->columns) ||
			empty($this->columns)) {
			return;
		}

		foreach ($this->columns as $table => $columns) {
			$sep = $dropped_columns = '';
			foreach ($columns as $title => $definition) {
				if ($this->field_exists($table, $title)) {
					$dropped_columns .= "{$sep}{$title}";
					$sep = ', DROP ';
				}
			}
			if (strlen($dropped_columns) > 0) {
				// tricky, tricky xD
				$result = $this->db->drop_column($table, $dropped_columns);
			}
		}
	}

	/*
	 * create multiple setting groups
	 *
	 * @param  array an associative array of setting groups
	 * @return array setting groups and gids
	 */
	private function add_settinggroups($groups)
	{
		if (!is_array($groups) ||
			empty($groups)) {
			return false;
		}

		$insert_array = $gids = array();
		foreach ($groups as $name => $group) {
			$query = $this->db->simple_select('settinggroups', 'gid', "name='{$name}'");
			if ($this->db->num_rows($query) > 0) {
				$group['gid'] = (int) $this->db->fetch_field($query, 'gid');
				$gids[$name] = $group['gid'];
				$this->db->update_query('settinggroups', $group, "name='{$name}'");
			} else {
				$gid = $this->db->insert_query('settinggroups', $group);
				$gids[$name] = $gid;
			}
		}
		return $gids;
	}

	/*
	 * create settings from an array
	 *
	 * @param  array an associative array of groups and settings
	 * @return void
	 */
	public function add_settings()
	{
		if (!is_array($this->settings) ||
			empty($this->settings)) {
			return;
		}

		foreach ($this->settings as $group => $data) {
			$gids = $this->add_settinggroups(array($group => $data['group']));
			$gid = $gids[$group];

			$insert_array = array();
			foreach ($data['settings'] as $name => $setting) {
				$setting['gid'] = $gid;
				// does the setting already exist?
				$query = $this->db->simple_select('settings', 'sid', "name='{$name}'");
				if ($this->db->num_rows($query) > 0) {
					$setting['sid'] = (int) $this->db->fetch_field($query, 'sid');

					// if so update the info (but leave the value alone)
					unset($setting['value']);
					$this->db->update_query('settings', $setting, "name='{$name}'");
				} else {
					$insert_array[] = $setting;
				}
			}
			if (!empty($insert_array)) {
				$this->db->insert_query_multiple('settings', $insert_array);
			}
		}
		rebuild_settings();
	}

	/*
	 * create or update the template groups stored in the object
	 *
	 * @return void
	 */
	public function add_template_groups()
	{
		if (!is_array($this->templates) ||
			empty($this->templates)) {
			return;
		}

		$insert_array = $update_array = array();

		foreach ($this->templates as $prefix => $data) {
			$query = $this->db->simple_select('templategroups', 'gid', "prefix='{$prefix}'");
			if ($this->db->num_rows($query) > 0) {
				$gid = (int) $this->db->fetch_field($query, 'gid');
				$this->db->update_query('templategroups', $data['group'], "gid='{$gid}'");
			} else {
				$insert_array[] = $data['group'];
			}
		}

		if (!empty($insert_array)) {
			$this->db->insert_query_multiple('templategroups', $insert_array);
		}
	}

	/*
	 * create multiple templates from stored info
	 *
	 * @return void
	 */
	public function add_templates()
	{
		if (!is_array($this->templates) ||
			empty($this->templates)) {
			return;
		}

		$this->add_template_groups();

		$insert_array = array();
		foreach ($this->templates as $group => $data) {
			foreach ($data['templates'] as $title => $template) {
				$title = $this->db->escape_string($title);
				$template = $this->db->escape_string($template);
				$template_array = array(
					"title" => $title,
					"template" => $template,
					"sid" => -2,
					"version" => 1,
					"dateline" => TIME_NOW,
				);

				$query = $this->db->simple_select('templates', 'tid', "title='{$title}' AND sid IN('-2', '-1')");
				if ($this->db->num_rows($query) > 0) {
					$tid = (int) $this->db->fetch_field($query, 'tid');
					$this->db->update_query('templates', $template_array, "tid='{$tid}'");
				} else {
					$insert_array[] = $template_array;
				}
			}
		}

		if (!empty($insert_array)) {
			$this->db->insert_query_multiple('templates', $insert_array);
		}
	}

	/*
	 * add any listed style sheets
	 *
	 * @return void
	 */
	public function add_style_sheets()
	{
		if (!is_array($this->style_sheets) ||
			empty($this->style_sheets)) {
			return;
		}

		global $config;
		foreach ($this->style_sheets as $name => $data) {
			$attachedto = $data['attachedto'];
			if (is_array($data['attachedto'])) {
				$attachedto = array();
				foreach ($data['attachedto'] as $file => $actions) {
					if (is_array($actions)) {
						$actions = implode(",", $actions);
					}

					if ($actions) {
						$file = "{$file}?{$actions}";
					}
					$attachedto[] = $file;
				}
				$attachedto = implode("|", $attachedto);
			}

			$name = $this->db->escape_string($name) . '.css';
			$stylesheet = array(
				'name' => $name,
				'tid' => 1,
				'attachedto' => $this->db->escape_string($attachedto),
				'stylesheet' => $this->db->escape_string($data['stylesheet']),
				'cachefile' => $name,
				'lastmodified' => TIME_NOW,
            );

			// update any children
			$this->db->update_query('themestylesheets', array(
				"attachedto" => $stylesheet['attachedto']
			), "name='{$name}'");

			// now update/insert the master stylesheet
			$query = $this->db->simple_select('themestylesheets', 'sid', "tid='1' AND cachefile='{$name}'");
			$sid = (int) $this->db->fetch_field($query, 'sid');

			if ($sid) {
				$this->db->update_query('themestylesheets', $stylesheet, "sid='{$sid}'");
			} else {
				$sid = $this->db->insert_query('themestylesheets', $stylesheet);
				$stylesheet['sid'] = (int) $sid;
			}

			// now cache the actual files
			require_once MYBB_ROOT . "{$config['admin_dir']}/inc/functions_themes.php";

			if(!cache_stylesheet(1, $data['cachefile'], $data['stylesheet']))
			{
				$this->db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
			}

			// and update the CSS file list
			update_theme_stylesheet_list(1, false, true);
		}
	}

	/*
	 * remove_style_sheets()
	 *
	 * completely remove any style sheets in install_data.php
	 *
	 * @return: n/a
	 */
	public function remove_style_sheets()
	{
		if (empty($this->style_sheet_names) ||
			!is_array($this->style_sheet_names)) {
			return;
		}

		global $config;

		// get a list and form the WHERE clause
		$ss_list = "'" . implode("','", $this->style_sheet_names) . "'";
		$where = "name={$ss_list}";
		if (count($this->style_sheet_names) > 1) {
			$where = "name IN({$ss_list})";
		}

		// find the master and any children
		$query = $this->db->simple_select('themestylesheets', 'tid,name', $where);

		// delete them all from the server
		while ($stylesheet = $this->db->fetch_array($query)) {
			@unlink(MYBB_ROOT."cache/themes/{$stylesheet['tid']}_{$stylesheet['name']}");
			@unlink(MYBB_ROOT."cache/themes/theme{$stylesheet['tid']}/{$stylesheet['name']}");
		}

		// then delete them from the database
		$this->db->delete_query('themestylesheets', $where);

		// now remove them from the CSS file list
		require_once MYBB_ROOT . "{$config['admin_dir']}/inc/functions_themes.php";
		update_theme_stylesheet_list(1, false, true);
	}

	/*
	 * copy default images to each theme
	 *
	 * @return void
	 */
	public function add_images()
	{
		if (!is_array($this->images) ||
		   empty($this->images) ||
		   (!$this->images['forum'] && !$this->images['acp'])) {
			return;
		}

		// if there is a sub-folder for images, make sure it has a trailing slash
		$main_folder = $this->images['folder'];
		if ($main_folder &&
		   !substr($main_folder, 1, 1) !== '/') {
			$main_folder = "/{$main_folder}";
		}

		// handle ACP images
		if (is_array($this->images['acp'])) {
			// load all detected themes
			foreach (new DirectoryIterator(MYBB_ADMIN_DIR . '/styles') as $folder) {
				if ($folder->isDot() ||
				   !$folder->isDir()) {
					continue;
				}

				$foldername = $folder->getFilename();

				// set up a path and make sure we can write to it
				$path = MYBB_ADMIN_DIR . "/styles/{$foldername}";
				if (@!file_exists("{$path}/main.css") ||
				   (!is_dir("{$path}/images") &&
				   !mkdir("{$path}/images", 0777, true)) ||
				   ($main_folder &&
				    !is_dir("{$path}/images{$main_folder}") &&
				    !mkdir("{$path}/images{$main_folder}", 0777, true))) {
					continue;
				}

				foreach ($this->images['acp'] as $filename => $details) {
					// if there is a sub-folder make sure it has a trailing slash
					if ($details['folder'] &&
						substr($details['folder'], strlen($details['folder']) - 1, 1) != '/') {
						$details['folder'] .= '/';
					}

					// don't overwrite or upgrades will kill custom images
					$full_path = MYBB_ADMIN_DIR . "/styles/{$foldername}/images{$main_folder}/{$details['folder']}{$filename}";
					if (!file_exists($full_path)) {
						file_put_contents($full_path, base64_decode($details['image']));
					}
				}
			}
		}

		// handle the forum side images if any
		if (is_array($this->images['forum'])) {
			global $mybb, $db;

			// get all the theme folders
			$all_dirs = array();
			$query = $db->simple_select('themes', 'pid, properties');
			while ($theme = $db->fetch_array($query)) {
				$properties = unserialize($theme['properties']);
				$all_dirs[$properties['imgdir']] = $properties['imgdir'];
			}

			foreach ($all_dirs as $dir) {
				// make sure our folders exist
				$path = MYBB_ROOT . $dir;
				if (!is_dir($path) ||
				   ($main_folder &&
				    !is_dir("{$path}{$main_folder}") &&
				    !mkdir("{$path}{$main_folder}", 0777, true))) {
					continue;
				}

				foreach ($this->images['forum'] as $filename => $details) {
					// if this attribute is set, install the images in language directory
					if ($details['lang']) {
						$full_path = "{$path}/{$mybb->settings['bblanguage']}/{$filename}";
					} else {
						// if there is a sub-folder ensure that it has a trailing slash
						if ($details['folder'] &&
							substr($details['folder'], strlen($details['folder']) - 1, 1) != '/') {
							$details['folder'] .= '/';
						}
						$full_path = "{$path}{$main_folder}/{$details['folder']}{$filename}";
					}

					// don't overwrite or upgrades will kill custom images
					if (!file_exists($full_path)) {
						file_put_contents($full_path, base64_decode($details['image']));
					}
				}
			}
		}
	}

	/*
	 * removed rows from a named table when values of the named column
	 * are matched with members of the list
	 *
	 * @param  string table name without prefix
	 * @param  string field name
	 * @param  array string values
	 * @return void
	 */
	private function remove_($table, $field, $list)
	{
		if (!is_array($list)) {
			$list = array($list);
		}

		if (empty($list)) {
			return;
		}

		if ($this->table_exists($table) &&
			$this->field_exists($table, $field)) {
			$delete_list = "'" . implode("','", $list) . "'";
			$this->db->delete_query($table, "{$field} IN ({$delete_list})");
		}
	}

	/*
	 * verify the existence of named table
	 *
	 * @param  string table name without prefix
	 * @return bool true if it exists, false if not
	 */
	public function table_exists($table)
	{
		static $table_list;

		if (!isset($table_list)) {
			$table_list = $this->build_table_list();
		}
		return isset($table_list[$this->db->table_prefix . $table]);
	}

	/*
	 * build an array of all the tables in the current database
	 *
	 * @return array keys for the table names and 1 for the values
	 */
	private function build_table_list()
	{
		global $config;

		$query = $this->db->write_query("
			SHOW TABLES
			FROM `{$config['database']['database']}`
		");

		$table_list = array();
		while ($row = $this->db->fetch_array($query)) {
			$table_list[array_pop($row)] = 1;
		}
		return $table_list;
	}

	/*
	 * verify the existence of the named column of the named table
	 *
	 * @param  string table name without prefix
	 * @param  string field name
	 * @return bool true if it exists/false if not
	 */
	public function field_exists($table, $field)
	{
		static $field_list;

		if (!isset($field_list[$table])) {
			$field_list[$table] = $this->build_field_list($table);
		}
		return isset($field_list[$table][$field]);
	}

	/*
	 * build an array of all the columns of the named table
	 *
	 * @param  string table name without prefix
	 * @return array keys for the field names and 1 for the values
	 */
	private function build_field_list($table)
	{
		$field_list = array();

		$field_info = $this->db->show_fields_from($table);
		foreach ($field_info as $info) {
			$field_list[$info['Field']] = 1;
		}
		return $field_list;
	}
}

?>
