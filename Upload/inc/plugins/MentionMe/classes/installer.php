<?php
/*
 * Wildcard Helper Classes
 * ACP - MyBB Plug-in Installer
 *
 * a generic installer for MyBB Plug-ins that accepts a data file and performs
 * installation functions in a non-destructive way according to the provided information
 */

class WildcardPluginInstaller
{
	const VERSION = 1.1;

	// a local copy of the MyBB db object
	private $db;

	// a copy of the install data tables
	protected $tables = array();

	// storage for the list of table names
	protected $table_names = array();

	// a copy of the install data columns
	protected $columns = array();

	// a copy of the install data settings
	protected $settings = array();

	// storage for the list of setting names
	protected $setting_names = array();

	// a copy of the install data setting groups
	protected $settinggroups = array();

	// storage for the list of setting group names
	protected $settinggroup_names = array();

	// a copy of the install data templates
	protected $templates = array();

	// storage for the list of template names
	protected $template_names = array();

	// a copy of the install data template groups
	protected $templategroups = array();

	// storage for the list of template group names
	protected $templategroup_names = array();

	/**
	 * __construct()
	 *
	 * load the installation data and prepare for anything
	 *
	 * @param - $path - (string) path to the install data
	 * @return: n/a
	 */
	public function __construct($path)
	{
		if(!trim($path) || !file_exists($path))
		{
			return;
		}

		global $lang, $db;

		// get all the install data
		require_once $path;

		// now loop through the known values and extract the info in a way we can use
		foreach(array('tables', 'columns', 'settings', 'templates', 'style_sheets') as $key)
		{
			if(!is_array($$key) || empty($$key))
			{
				continue;
			}

			// assing the main values as-is
			$this->$key = $$key;

			// now produce the x_names and xgroup_names placeholders
			$singular = substr($key, 0, strlen($key) - 1);
			$property = "{$singular}_names";
			$poperty_group = "{$singular}group_names";

			switch($key)
			{
				case 'style_sheets':
					// stylesheets need the extension appended
					foreach(array_keys($style_sheets) as $name)
					{
						$this->style_sheet_names[] = $name . '.css';
					}
					break;
				case 'templates':
				case 'settings':
					// these items have groups and special cases for the main items
					$this->$poperty_group = array_keys($$key);
					foreach($$key as $group => $info)
					{
						$this->$property = array_keys($info[$key]);
					}
					break;
				case 'columns':
					// columns doesn't need anything else
					break;
				default:
					// all the others are simple
					$this->$property = array_keys($$key);
					break;
			}
		}

		// snag a copy of the db object
		$this->db = $db;
	}

	/*
	 * install()
	 *
	 * install all the elements stored in the installation data file
	 *
	 * @return: n/a
	 */
	public function install()
	{
		$this->add_tables();
		$this->add_columns();
		$this->add_settings();
		$this->add_templates();
		$this->add_style_sheets();
	}

	/*
	 * uninstall()
	 *
	 * uninstall all elements as provided in the install data
	 *
	 * @return: n/a
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
	 * add_table()
	 *
	 * create a correctly collated table from an array of options
	 *
	 * @param - $table - (string) table name without prefix
	 * @param - $columns - (array) an associative array of columns
	 * @return: n/a
	 */
	private function add_table($table, $columns)
	{
		static $collation;
		if(!isset($collation) || strlen($collation) == 0)
		{
			// only build collation for the first table
			$collation = $this->db->build_create_table_collation();
		}

		// build the column list
		$sep = $column_list = '';
		foreach($columns as $title => $definition)
		{
			$column_list .= "{$sep}{$title} {$definition}";
			$sep = ',';
		}

		// create the table if it doesn't already exist
		if(!$this->table_exists($table))
		{
			$table =  TABLE_PREFIX . $table;
			$this->db->write_query("CREATE TABLE {$table} ({$column_list}) ENGINE={$this->db->table_type}{$collation};");
		}
	}

	/*
	 * add_tables()
	 *
	 * create multiple tables from stored info
	 *
	 * @param - $tables - (array) an associative array of database tables and their columns
	 * @return: n/a
	 */
	public function add_tables()
	{
		if(!is_array($this->tables) || empty($this->tables))
		{
			return false;
		}

		foreach($this->tables as $table => $columns)
		{
			if($this->table_exists($table))
			{
				// if it already exists, just check that all the columns are present (and add them if not)
				$this->add_columns(array($table => $columns));
			}
			else
			{
				$this->add_table($table, $columns);
			}
		}
	}

	/*
	 * remove_tables()
	 *
	 * drop multiple database tables
	 *
	 * @return: n/a
	 */
	public function remove_tables()
	{
		if(!is_array($this->table_names) || empty($this->table_names))
		{
			return;
		}

		$drop_list = implode(', ' . TABLE_PREFIX, $this->table_names);
		$this->db->drop_table($drop_list);
	}

	/*
	 * add_columns()
	 *
	 * add columns in the list to a table (if they do not already exist)
	 *
	 * @param - $column_list - (array) an associative array of tables and columns
	 * @return: n/a
	 */
	public function add_columns()
	{
		if(!is_array($this->columns) || empty($this->columns))
		{
			return false;
		}

		foreach($this->columns as $table => $columns)
		{
			$sep = $added_columns = '';
			foreach($columns as $title => $definition)
			{
				if(!$this->field_exists($table, $title))
				{
					$added_columns .= "{$sep}{$title} {$definition}";
					$sep = ', ADD ';
				}
			}
			if(strlen($added_columns) > 0)
			{
				// trickery, again
				$this->db->add_column($table, $added_columns, '');
			}
		}
	}

	/*
	 * remove_columns()
	 *
	 * drop multiple listed columns
	 *
	 * @param - $column_list - (array) an associative array of tables and columns
	 * @return: n/a
	 */
	public function remove_columns()
	{
		if(!is_array($this->columns) || empty($this->columns))
		{
			return;
		}

		foreach($this->columns as $table => $columns)
		{
			$sep = $dropped_columns = '';
			foreach($columns as $title => $definition)
			{
				if($this->field_exists($table, $title))
				{
					$dropped_columns .= "{$sep}{$title}";
					$sep = ', DROP ';
				}
			}
			if(strlen($dropped_columns) > 0)
			{
				// tricky, tricky xD
				$result = $this->db->drop_column($table, $dropped_columns);
			}
		}
	}

	/*
	 * add_settinggroups()
	 *
	 * create multiple setting groups
	 *
	 * @param - $groups - (array) an associative array of setting groups
	 * @return: an associative array of setting groups and gids
	 */
	private function add_settinggroups($groups)
	{
		if(!is_array($groups) || empty($groups))
		{
			return false;
		}

		$insert_array = $gids = array();
		foreach($groups as $name => $group)
		{
			$query = $this->db->simple_select('settinggroups', 'gid', "name='{$name}'");
			if($this->db->num_rows($query) > 0)
			{
				$group['gid'] = (int) $this->db->fetch_field($query, 'gid');
				$gids[$name] = $group['gid'];
				$this->db->update_query('settinggroups', $group, "name='{$name}'");
			}
			else
			{
				$gid = $this->db->insert_query('settinggroups', $group);
				$gids[$name] = $gid;
			}
		}
		return $gids;
	}

	/*
	 * add_settings()
	 *
	 * create settings from an array
	 *
	 * @param - $settings - (array) an associative array of groups and settings
	 * @return: n/a
	 */
	public function add_settings()
	{
		if(!is_array($this->settings) || empty($this->settings))
		{
			return;
		}

		foreach($this->settings as $group => $data)
		{
			$gids = $this->add_settinggroups(array($group => $data['group']));
			$gid = $gids[$group];

			$insert_array = array();
			foreach($data['settings'] as $name => $setting)
			{
				$setting['gid'] = $gid;
				// does the setting already exist?
				$query = $this->db->simple_select('settings', 'sid', "name='{$name}'");
				if($this->db->num_rows($query) > 0)
				{
					$setting['sid'] = (int) $this->db->fetch_field($query, 'sid');

					// if so update the info (but leave the value alone)
					unset($setting['value']);
					$this->db->update_query('settings', $setting, "name='{$name}'");
				}
				else
				{
					$insert_array[] = $setting;
				}
			}
			if(!empty($insert_array))
			{
				$this->db->insert_query_multiple('settings', $insert_array);
			}
		}
		rebuild_settings();
	}

	/*
	 * add_template_groups()
	 *
	 * create or update the template groups stored in the object
	 *
	 * @return: n/a
	 */
	public function add_template_groups()
	{
		if(!is_array($this->templates) || empty($this->templates))
		{
			return;
		}

		$insert_array = $update_array = array();

		foreach($this->templates as $prefix => $data)
		{
			$query = $this->db->simple_select('templategroups', 'gid', "prefix='{$prefix}'");
			if($this->db->num_rows($query) > 0)
			{
				$gid = (int) $this->db->fetch_field($query, 'gid');
				$this->db->update_query('templategroups', $data['group'], "gid='{$gid}'");
			}
			else
			{
				$insert_array[] = $data['group'];
			}
		}

		if(!empty($insert_array))
		{
			$this->db->insert_query_multiple('templategroups', $insert_array);
		}
	}

	/*
	 * add_templates()
	 *
	 * create multiple templates from stored info
	 *
	 * @param - $templates - (array) an associative array of template data
	 * @return: n/a
	 */
	public function add_templates()
	{
		if(!is_array($this->templates) || empty($this->templates))
		{
			return;
		}

		$this->add_template_groups();

		$insert_array = array();
		foreach($this->templates as $group => $data)
		{
			foreach($data['templates'] as $title => $template)
			{
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
				if($this->db->num_rows($query) > 0)
				{
					$tid = (int) $this->db->fetch_field($query, 'tid');
					$this->db->update_query('templates', $template_array, "tid='{$tid}'");
				}
				else
				{
					$insert_array[] = $template_array;
				}
			}
		}

		if(!empty($insert_array))
		{
			$this->db->insert_query_multiple('templates', $insert_array);
		}
	}

	/*
	 * add_style_sheets()
	 *
	 * add any listed style sheets
	 *
	 * @return: n/a
	 */
	public function add_style_sheets()
	{
		if(!is_array($this->style_sheets) || empty($this->style_sheets))
		{
			return;
		}

		global $config;
		foreach($this->style_sheets as $name => $data)
		{
			$attachedto = $data['attachedto'];
			if(is_array($data['attachedto']))
			{
				$attachedto = array();
				foreach($data['attachedto'] as $file => $actions)
				{
					if(is_array($actions))
					{
						$actions = implode(",", $actions);
					}

					if($actions)
					{
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

			if($sid)
			{
				$this->db->update_query('themestylesheets', $stylesheet, "sid='{$sid}'");
			}
			else
			{
				$sid = $this->db->insert_query('themestylesheets', $stylesheet);
				$stylesheet['sid'] = (int) $sid;
			}

			// now cache the actual files
			require_once MYBB_ROOT . "{$config['admin_dir']}/inc/functions_themes.php";
			cache_stylesheet(1, $data['cachefile'], $data['stylesheet']);

			// and update the list
			update_theme_stylesheet_list(1);
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
		if(empty($this->style_sheet_names) || !is_array($this->style_sheet_names))
		{
			return;
		}

		global $config;

		// get a list and form the WHERE clause
		$ss_list = "'" . implode("','", $this->style_sheet_names) . "'";
		$where = "name={$ss_list}";
		if(count($this->style_sheet_names) > 1)
		{
			$where = "name IN({$ss_list})";
		}

		// find the master and any children
		$query = $this->db->simple_select('themestylesheets', 'tid,name', $where);

		// delete them all from the server
		while($stylesheet = $this->db->fetch_array($query))
		{
			@unlink(MYBB_ROOT."cache/themes/{$stylesheet['tid']}_{$stylesheet['name']}");
			@unlink(MYBB_ROOT."cache/themes/theme{$stylesheet['tid']}/{$stylesheet['name']}");
		}

		// then delete them from the database
		$this->db->delete_query('themestylesheets', $where);

		// now remove them from the list
		require_once MYBB_ROOT . "{$config['admin_dir']}/inc/functions_themes.php";
		update_theme_stylesheet_list(1);
	}

	/*
	 * remove_()
	 *
	 * removed rows from a named table when values of the named column
	 * are matched with members of the list
	 *
	 * @param - $table - (string) table name without prefix
	 * @param - $field - (string) field name
	 * @param - $list - (array) an unindexed array of string values
	 * @return: n/a
	 */
	private function remove_($table, $field, $list)
	{
		if(!is_array($list))
		{
			$list = array($list);
		}

		if(empty($list))
		{
			return;
		}

		if($this->table_exists($table) && $this->field_exists($table, $field))
		{
			$delete_list = "'" . implode("','", $list) . "'";
			$this->db->delete_query($table, "{$field} IN ({$delete_list})");
		}
	}

	/*
	 * table_exists()
	 *
	 * verify the existence of named table
	 *
	 * @param - $table - (string) table name without prefix
	 * @returns: (bool) true if it exists/false if not
	 */
	public function table_exists($table)
	{
		static $table_list;

		if(!isset($table_list))
		{
			$table_list = $this->build_table_list();
		}
		return isset($table_list[$this->db->table_prefix . $table]);
	}

	/*
	 * build_table_list()
	 *
	 * build an array of all the tables in the current database
	 * @returns: (array) an array with keys for the table names and 1 for the values
	 */
	private function build_table_list()
	{
		global $config;

		$query = $this->db->write_query("
			SHOW TABLES
			FROM {$config['database']['database']}
		");

		$table_list = array();
		while ($row = $this->db->fetch_array($query)) {
			$table_list[array_pop($row)] = 1;
		}
		return $table_list;
	}

	/*
	 * field_exists()
	 *
	 * verify the existence of the named column of the named table
	 *
	 * @param - $table - (string) table name without prefix
	 * @param - $field - (string) field name
	 * @returns: (bool) true if it exists/false if not
	 */
	public function field_exists($table, $field)
	{
		static $field_list;

		if(!isset($field_list[$table]))
		{
			$field_list[$table] = $this->build_field_list($table);
		}
		return isset($field_list[$table][$field]);
	}

	/*
	 * build_field_list()
	 *
	 * build an array of all the columns of the named table
	 *
	 * @param - $table - (string) table name without prefix
	 * @returns: (array) an array with keys for the field names and 1 for the values
	 */
	private function build_field_list($table)
	{
		$field_list = array();

		$field_info = $this->db->show_fields_from($table);
		foreach($field_info as $info)
		{
			$field_list[$info['Field']] = 1;
		}
		return $field_list;
	}
}

?>
