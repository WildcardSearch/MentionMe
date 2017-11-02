<?php
/**
 * Wildcard Helper Classes - Plugin Installer
 *
 * a generic installer for MyBB Plugins that accepts
 * a data file and performs installation functions
 * in a non-destructive way according to the provided
 * information
 *
 */

class WildcardPluginInstaller010202 implements WildcardPluginInstallerInterface010000
{
	/**
	 * @const version
	 */
	const VERSION = '1.2.2';

	/**
	 * @var object a copy of the MyBB db object
	 */
	protected $db;

	/**
	 * @var array the table data
	 */
	protected $tables = array();

	/**
	 * @var array the table names
	 */
	protected $tableNames = array();

	/**
	 * @var array the column data
	 */
	protected $columns = array();

	/**
	 * @var array the settings data
	 */
	protected $settings = array();

	/**
	 * @var array the setting names
	 */
	protected $settingNames = array();

	/**
	 * @var array the setting group data
	 */
	protected $settinggroups = array();

	/**
	 * @var array the setting group names
	 */
	protected $settingGroupNames = array();

	/**
	 * @var array the template data
	 */
	protected $templates = array();

	/**
	 * @var array the template names
	 */
	protected $templateNames = array();

	/**
	 * @var array the template group data
	 */
	protected $templategroups = array();

	/**
	 * @var array the template group names
	 */
	protected $templategroupNames = array();

	/**
	 * @var array the template data
	 */
	protected $styleSheets = array();

	/**
	 * @var array the template names
	 */
	protected $styleSheetNames = array();

	/**
	 * @var array the image data
	 */
	protected $images = array();

	/**
	 * load the installation data and prepare for anything
	 *
	 * @param  string path to the install data
	 * @return void
	 */
	public function __construct($path = '')
	{
		if (!trim($path) ||
			!file_exists($path)) {
			return;
		}

		global $lang, $db;
		require_once $path;
		foreach (array('tables', 'columns', 'settings', 'templates', 'images', 'styleSheets') as $key) {
			if (!is_array($$key) ||
				empty($$key)) {
				continue;
			}

			$this->$key = $$key;
			switch ($key) {
			case 'styleSheets':
				foreach (array('acp', 'forum') as $key) {
					if (!is_array($styleSheets[$key]) ||
						empty($styleSheets[$key])) {
						$this->styleSheetNames[$key] = array();
						continue;
					}

					foreach (array_keys($styleSheets[$key]) as $name) {
						// stylesheets need the extension appended
						$this->styleSheetNames[$key][] = $name . '.css';
					}
				}
				break;
			case 'settings':
				$this->settingGroupNames = array_keys($settings);
				foreach ($settings as $group => $info) {
					foreach ($info['settings'] as $name => $setting) {
						$this->settingNames[] = $name;
					}
				}
				break;
			case 'templates':
				$this->templategroupNames = array_keys($templates);
				foreach ($templates as $group => $info) {
					foreach ($info['templates'] as $name => $template) {
						$this->templateNames[] = $name;
					}
				}
				break;
			case 'columns':
			case 'images':
				break;
			default:
				$singular = substr($key, 0, strlen($key) - 1);
				$property = "{$singular}Names";
				$this->$property = array_keys($$key);
				break;
			}
		}

		// snag a copy of the db object
		$this->db = $db;
	}

	/**
	 * install all the elements stored in the installation data file
	 *
	 * @return void
	 */
	public function install()
	{
		$this->addTables();
		$this->addColumns();
		$this->addSettings();
		$this->addTemplates();
		$this->addStyleSheets();
		$this->addImages();
	}

	/**
	 * uninstall all elements as provided in the install data
	 *
	 * @return void
	 */
	public function uninstall()
	{
		$this->removeTables();
		$this->removeColumns();
		$this->remove('settinggroups', 'name', $this->settingGroupNames);
		$this->remove('settings', 'name', $this->settingNames);
		$this->remove('templategroups', 'prefix', $this->templategroupNames);
		$this->remove('templates', 'title', $this->templateNames);
		$this->removeStyleSheets();
		rebuild_settings();
	}

	/**
	 * create a correctly collated table from an array of options
	 *
	 * @param  string table name without prefix
	 * @param  array the columns
	 * @return void
	 */
	protected function addTable($table, $columns)
	{
		static $collation;
		if (!isset($collation) ||
			strlen($collation) == 0) {
			// only build collation for the first table
			$collation = $this->db->build_create_table_collation();
		}

		// build the column list
		$sep = $columnList = '';
		foreach ($columns as $title => $definition) {
			$columnList .= "{$sep}{$title} {$definition}";
			$sep = ',';
		}

		// create the table if it doesn't already exist
		if (!$this->tableExists($table)) {
			$table =  TABLE_PREFIX . $table;
			$this->db->write_query("CREATE TABLE {$table} ({$columnList}) ENGINE={$this->db->table_type}{$collation};");
		}
	}

	/**
	 * create multiple tables from stored info
	 *
	 * @param  array database tables and their columns
	 * @return void
	 */
	protected function addTables()
	{
		if (!is_array($this->tables) ||
			empty($this->tables)) {
			return false;
		}

		foreach ($this->tables as $table => $columns) {
			if ($this->tableExists($table)) {
				// if it already exists, just check that all the columns are present (and add them if not)
				$this->addColumns(array($table => $columns));
			} else {
				$this->addTable($table, $columns);
			}
		}
	}

	/**
	 * drop multiple database tables
	 *
	 * @return void
	 */
	protected function removeTables()
	{
		if (!is_array($this->tableNames) ||
			empty($this->tableNames)) {
			return;
		}

		$dropList = implode(', ' . TABLE_PREFIX, $this->tableNames);
		$this->db->drop_table($dropList);
	}

	/**
	 * add columns in the list to a table (if they do not already exist)
	 *
	 * @param  array tables and columns
	 * @return void
	 */
	protected function addColumns($columns = '')
	{
		if (!is_array($columns) ||
			empty($columns)) {
			$columns = $this->columns;
		}

		foreach ($columns as $table => $allColumns) {
			$sep = $addedColumns = '';
			foreach ($allColumns as $title => $definition) {
				if (!$this->fieldExists($table, $title)) {
					$addedColumns .= "{$sep}{$title} {$definition}";
					$sep = ', ADD ';
				}
			}
			if (strlen($addedColumns) > 0) {
				// trickery, again
				$this->db->add_column($table, $addedColumns, '');
			}
		}
	}

	/**
	 * drop multiple listed columns
	 *
	 * @param  array tables and columns
	 * @return void
	 */
	protected function removeColumns()
	{
		if (!is_array($this->columns) ||
			empty($this->columns)) {
			return;
		}

		foreach ($this->columns as $table => $columns) {
			$sep = $droppedColumns = '';
			foreach ($columns as $title => $definition) {
				if ($this->fieldExists($table, $title)) {
					$droppedColumns .= "{$sep}{$title}";
					$sep = ', DROP ';
				}
			}
			if (strlen($droppedColumns) > 0) {
				// tricky, tricky xD
				$result = $this->db->drop_column($table, $droppedColumns);
			}
		}
	}

	/**
	 * create multiple setting groups
	 *
	 * @param  array setting groups
	 * @return array setting groups and gids
	 */
	protected function addSettingGroups($groups)
	{
		if (!is_array($groups) ||
			empty($groups)) {
			return false;
		}

		$insertArray = $gids = array();
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

	/**
	 * create settings from an array
	 *
	 * @param  array groups and settings
	 * @return void
	 */
	protected function addSettings()
	{
		if (!is_array($this->settings) ||
			empty($this->settings)) {
			return;
		}

		foreach ($this->settings as $group => $data) {
			$gids = $this->addSettingGroups(array($group => $data['group']));
			$gid = $gids[$group];

			$insertArray = array();
			foreach ($data['settings'] as $name => $setting) {
				$setting['gid'] = $gid;
				// does the setting already exist?
				$query = $this->db->simple_select('settings', 'sid', "name='{$name}'");
				if ($this->db->num_rows($query) > 0) {
					$setting['sid'] = (int) $this->db->fetch_field($query, 'sid');

					// update the info (but leave the value alone)
					unset($setting['value']);
					$this->db->update_query('settings', $setting, "name='{$name}'");
				} else {
					$insertArray[] = $setting;
				}
			}
			if (!empty($insertArray)) {
				$this->db->insert_query_multiple('settings', $insertArray);
			}
		}
		rebuild_settings();
	}

	/**
	 * create or update the template groups stored in the object
	 *
	 * @return void
	 */
	protected function addTemplateGroups()
	{
		if (!is_array($this->templates) ||
			empty($this->templates)) {
			return;
		}

		$insertArray = $update_array = array();

		foreach ($this->templates as $prefix => $data) {
			$query = $this->db->simple_select('templategroups', 'gid', "prefix='{$prefix}'");
			if ($this->db->num_rows($query) > 0) {
				$gid = (int) $this->db->fetch_field($query, 'gid');
				$this->db->update_query('templategroups', $data['group'], "gid='{$gid}'");
			} else {
				$insertArray[] = $data['group'];
			}
		}

		if (!empty($insertArray)) {
			$this->db->insert_query_multiple('templategroups', $insertArray);
		}
	}

	/**
	 * create multiple templates from stored info
	 *
	 * @return void
	 */
	protected function addTemplates()
	{
		if (!is_array($this->templates) ||
			empty($this->templates)) {
			return;
		}

		$this->addTemplateGroups();

		$insertArray = array();
		foreach ($this->templates as $group => $data) {
			foreach ($data['templates'] as $title => $template) {
				$title = $this->db->escape_string($title);
				$template = $this->db->escape_string($template);
				$templateArray = array(
					"title" => $title,
					"template" => $template,
					"sid" => -2,
					"version" => 1,
					"dateline" => TIME_NOW,
				);

				$query = $this->db->simple_select('templates', 'tid', "title='{$title}' AND sid IN('-2', '-1')");
				if ($this->db->num_rows($query) > 0) {
					$tid = (int) $this->db->fetch_field($query, 'tid');
					$this->db->update_query('templates', $templateArray, "tid='{$tid}'");
				} else {
					$insertArray[] = $templateArray;
				}
			}
		}

		if (!empty($insertArray)) {
			$this->db->insert_query_multiple('templates', $insertArray);
		}
	}

	/**
	 * add any listed style sheets
	 *
	 * @return void
	 */
	protected function addStyleSheets()
	{
		if (!is_array($this->styleSheets) ||
			empty($this->styleSheets) ||
			(empty($this->styleSheets['acp']) &&
			empty($this->styleSheets['forum']))) {
			return;
		}

		global $config;

		if (!empty($this->styleSheets['acp'])) {
			// if there is a sub-folder for images, make sure it starts with a slash
			$mainFolder = $this->styleSheets['folder'];
			if ($mainFolder &&
			   !substr($mainFolder, 1, 1) !== '/') {
				$mainFolder = "/{$mainFolder}";
			}

			foreach ($this->buildThemeList(true) as $folder) {
				// set up a path and make sure we can write to it
				$path = MYBB_ADMIN_DIR . "styles/{$folder}";

				if ($mainFolder &&
				    !is_dir("{$path}{$mainFolder}") &&
				    !mkdir("{$path}{$mainFolder}", 0777, true)) {
					continue;
				}

				foreach ($this->styleSheets['acp'] as $filename => $details) {
					// if there is a sub-folder make sure it has a trailing slash
					if ($details['folder'] &&
						substr($details['folder'], strlen($details['folder']) - 1, 1) != '/') {
						$details['folder'] .= '/';
					}

					$fullPath = "{$path}{$mainFolder}/{$details['folder']}{$filename}.css";
					file_put_contents($fullPath, $details['stylesheet']);
				}
			}
		}

		if (empty($this->styleSheets['forum'])) {
			return;
		}

		foreach ($this->styleSheets['forum'] as $name => $data) {
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
			$styleSheet = array(
				'name' => $name,
				'tid' => 1,
				'attachedto' => $this->db->escape_string($attachedto),
				'stylesheet' => $this->db->escape_string($data['stylesheet']),
				'cachefile' => $name,
				'lastmodified' => TIME_NOW,
            );

			// update any children
			$this->db->update_query('themestylesheets', array(
				"attachedto" => $styleSheet['attachedto']
			), "name='{$name}'");

			// now update/insert the master stylesheet
			$query = $this->db->simple_select('themestylesheets', 'sid', "tid='1' AND name='{$name}'");
			$sid = (int) $this->db->fetch_field($query, 'sid');

			if ($sid) {
				$this->db->update_query('themestylesheets', $styleSheet, "sid='{$sid}'");
			} else {
				$sid = $this->db->insert_query('themestylesheets', $styleSheet);
				$styleSheet['sid'] = (int) $sid;
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

	/**
	 * completely remove any style sheets in install_data.php
	 *
	 * @return void
	 */
	protected function removeStyleSheets()
	{
		if (empty($this->styleSheetNames) ||
			!is_array($this->styleSheetNames) ||
			(empty($this->styleSheets['acp']) &&
			empty($this->styleSheets['forum']))) {
			return;
		}

		if (!empty($this->styleSheets['acp'])) {
			// if there is a sub-folder for images, make sure it starts with a slash
			$mainFolder = $this->styleSheets['folder'];
			if ($mainFolder &&
			   !substr($mainFolder, 1, 1) !== '/') {
				$mainFolder = "/{$mainFolder}";
			}

			foreach ($this->buildThemeList(true) as $folder) {
				// set up a path and make sure we can write to it
				$path = MYBB_ADMIN_DIR . "styles/{$folder}";

				if ($mainFolder &&
				    !is_dir("{$path}{$mainFolder}")) {
					continue;
				}

				foreach ($this->styleSheetNames['acp'] as $filename) {
					// if there is a sub-folder make sure it has a trailing slash
					if ($details['folder'] &&
						substr($details['folder'], strlen($details['folder']) - 1, 1) != '/') {
						$details['folder'] .= '/';
					}

					@unlink(MYBB_ADMIN_DIR . "styles/{$folder}{$mainFolder}/{$details['folder']}{$filename}");
				}
			}
		}

		if (empty($this->styleSheets['forum'])) {
			return;
		}

		// get a list and form the WHERE clause
		$styleSheetList = "'" . implode("','", $this->styleSheetNames['forum']) . "'";
		$where = "name={$styleSheetList}";
		if (count($this->styleSheetNames['forum']) > 1) {
			$where = "name IN({$styleSheetList})";
		}

		// find the master and any children
		$query = $this->db->simple_select('themestylesheets', 'tid,name', $where);

		// delete them all from the server
		while ($styleSheet = $this->db->fetch_array($query)) {
			@unlink(MYBB_ROOT."cache/themes/{$styleSheet['tid']}_{$styleSheet['name']}");
			@unlink(MYBB_ROOT."cache/themes/theme{$styleSheet['tid']}/{$styleSheet['name']}");
		}

		// then delete them from the database
		$this->db->delete_query('themestylesheets', $where);

		// now remove them from the CSS file list
		require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
		update_theme_stylesheet_list(1, false, true);
	}

	/**
	 * copy default images to each theme
	 *
	 * @return void
	 */
	protected function addImages()
	{
		if (!is_array($this->images) ||
		   empty($this->images) ||
		   (!$this->images['forum'] && !$this->images['acp'])) {
			return;
		}

		// if there is a sub-folder for images, make sure it has a trailing slash
		$mainFolder = $this->images['folder'];
		if ($mainFolder &&
		   !substr($mainFolder, 1, 1) !== '/') {
			$mainFolder = "/{$mainFolder}";
		}

		// handle ACP images
		if (is_array($this->images['acp'])) {
			// load all detected themes
			foreach ($this->buildThemeList(true) as $foldername) {
				// set up a path and make sure we can write to it
				$path = MYBB_ADMIN_DIR . "styles/{$foldername}";

				if ((!is_dir("{$path}/images") &&
				   !mkdir("{$path}/images", 0777, true)) ||
				   ($mainFolder &&
				    !is_dir("{$path}/images{$mainFolder}") &&
				    !mkdir("{$path}/images{$mainFolder}", 0777, true))) {
					continue;
				}

				foreach ($this->images['acp'] as $filename => $details) {
					// if there is a sub-folder make sure it has a trailing slash
					if ($details['folder'] &&
						substr($details['folder'], strlen($details['folder']) - 1, 1) != '/') {
						$details['folder'] .= '/';
					}

					// don't overwrite or upgrades will kill custom images
					$fullPath = MYBB_ADMIN_DIR . "styles/{$foldername}/images{$mainFolder}/{$details['folder']}{$filename}";
					if (!file_exists($fullPath)) {
						file_put_contents($fullPath, base64_decode($details['image']));
					}
				}
			}
		}

		// handle the forum side images if any
		if (is_array($this->images['forum'])) {
			global $mybb;

			foreach ($this->buildThemeList() as $dir) {
				// make sure our folders exist
				$path = MYBB_ROOT . $dir;
				if (!is_dir($path) ||
				   ($mainFolder &&
				    !is_dir("{$path}{$mainFolder}") &&
				    !mkdir("{$path}{$mainFolder}", 0777, true))) {
					continue;
				}

				foreach ($this->images['forum'] as $filename => $details) {
					// if this attribute is set, install the images in language directory
					if ($details['lang']) {
						$fullPath = "{$path}/{$mybb->settings['bblanguage']}/{$filename}";
					} else {
						// if there is a sub-folder ensure that it has a trailing slash
						if ($details['folder'] &&
							substr($details['folder'], strlen($details['folder']) - 1, 1) != '/') {
							$details['folder'] .= '/';
						}
						$fullPath = "{$path}{$mainFolder}/{$details['folder']}{$filename}";
					}

					// don't overwrite or upgrades will kill custom images
					if (!file_exists($fullPath)) {
						file_put_contents($fullPath, base64_decode($details['image']));
					}
				}
			}
		}
	}

	/**
	 * removed rows from a named table when values of the
	 * named column are matched with members of the list
	 *
	 * @param  string table name without prefix
	 * @param  string field name
	 * @param  array string values
	 * @return void
	 */
	protected function remove($table, $field, $list)
	{
		if (!is_array($list)) {
			$list = array($list);
		}

		if (empty($list)) {
			return;
		}

		if ($this->tableExists($table) &&
			$this->fieldExists($table, $field)) {
			$delete_list = "'" . implode("','", $list) . "'";
			$this->db->delete_query($table, "{$field} IN ({$delete_list})");
		}
	}

	/**
	 * verify the existence of named table
	 *
	 * @param  string table name without prefix
	 * @return bool true if it exists, false if not
	 */
	protected function tableExists($table)
	{
		static $tableList;

		if (!isset($tableList)) {
			$tableList = $this->buildTableList();
		}
		return isset($tableList[$this->db->table_prefix . $table]);
	}

	/**
	 * build an array of all the tables in the current database
	 *
	 * @return array keys for the table names and 1 for the values
	 */
	protected function buildTableList()
	{
		global $config;

		$query = $this->db->write_query("
			SHOW TABLES
			FROM `{$config['database']['database']}`
		");

		$tableList = array();
		while ($row = $this->db->fetch_array($query)) {
			$tableList[array_pop($row)] = 1;
		}
		return $tableList;
	}

	/**
	 * verify the existence of the named column of the named table
	 *
	 * @param  string table name without prefix
	 * @param  string field name
	 * @return bool true if it exists/false if not
	 */
	protected function fieldExists($table, $field)
	{
		static $fieldList;

		if (!isset($fieldList[$table])) {
			$fieldList[$table] = $this->buildFieldList($table);
		}
		return isset($fieldList[$table][$field]);
	}

	/**
	 * build an array of all the columns of the named table
	 *
	 * @param  string table name without prefix
	 * @return array keys for the field names and 1 for the values
	 */
	protected function buildFieldList($table)
	{
		$fieldList = array();

		$fieldInfo = $this->db->show_fields_from($table);
		foreach ($fieldInfo as $info) {
			$fieldList[$info['Field']] = 1;
		}
		return $fieldList;
	}

	/**
	 * build an array of all the installed themes
	 *
	 * @param  bool acp or forum
	 * @return array keys of folder names
	 */
	private function buildThemeList($acp = false)
	{
		static $cache;
		$folderList = array();

		if ($acp === true) {
			if (isset($cache['acp'])) {
				return $cache['acp'];
			}

			foreach (new DirectoryIterator(MYBB_ADMIN_DIR . 'styles') as $di) {
				$folder = $di->getFilename();

				if ($di->isDot() ||
					!$di->isDir() ||
					@!file_exists(MYBB_ADMIN_DIR . "styles/{$folder}/main.css")) {
					continue;
				}

				$folderList[] = $folder;
			}

			$cache['acp'] = $folderList;
		} else {
			if (isset($cache['forum'])) {
				return $cache['forum'];
			}

			$duplicates = array();
			$query = $this->db->simple_select('themes', 'pid, properties');
			while ($theme = $this->db->fetch_array($query)) {
				$properties = unserialize($theme['properties']);
				$folder = $properties['imgdir'];

				if (!isset($duplicates[$folder])) {
					$duplicates[$folder] = 1;
					$folderList[] = $folder;
				}
			}

			$cache['forum'] = $folderList;
		}

		return $folderList;
	}
}

?>
