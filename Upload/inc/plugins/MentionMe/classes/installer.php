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
	protected $tables = array();
	protected $table_names = array();

	protected $columns = array();

	protected $settings = array();
	protected $setting_names = array();

	protected $settinggroups = array();
	protected $settinggroup_names = array();

	protected $templates = array();
	protected $template_names = array();

	public function __construct($path)
	{
		if(trim($path) && file_exists($path))
		{
			global $lang, $db;
			require_once $path;
			foreach(array('tables', 'columns', 'settings', 'templates') as $key)
			{
				if(is_array($$key) && !empty($$key))
				{
					$this->$key = $$key;
					switch($key)
					{
						case 'settings':
							$this->settinggroup_names = array_keys($settings);
							foreach($settings as $group => $info)
							{
								foreach($info['settings'] as $name => $setting)
								{
									$this->setting_names[] = $name;
								}
							}
							break;
						case 'columns':
							$this->columns = $columns;
							break;
						default:
							$singular = substr($key, 0, strlen($key) - 1);
							$property = "{$singular}_names";
							$this->$property = array_keys($$key);
							break;
					}
				}
			}
			return true;
		}
		return false;
	}

	public function install()
	{
		$this->add_tables();
		$this->add_columns();
		$this->add_settings();
		$this->add_templates();
	}

	public function uninstall()
	{
		$this->remove_tables();
		$this->remove_columns();
		$this->remove_('settinggroups', 'name', $this->settinggroup_names);
		$this->remove_('settings', 'name', $this->setting_names);
		$this->remove_('templates', 'title', $this->template_names);
		rebuild_settings();
	}

	/*
	 * add_table()
	 *
	 * create a correctly collated table from an array of options
	 *
	 * @param - $table - (string) table name without prefix
	 * @param - $columns - (array) an associative array of columns
	 */
	private function add_table($table, $columns)
	{
		global $db;
		static $collation;
		if(!isset($collation) || strlen($collation) == 0)
		{
			// only build collation for the first table
			$collation = $db->build_create_table_collation();
		}

		// build the column list
		$sep = $column_list = '';
		foreach($columns as $title => $definition)
		{
			$column_list .= "{$sep}{$title} {$definition}";
			$sep = ',';
		}

		// create the table if it doesn't already exist
		if(!$db->table_exists($table))
		{
			$table =  TABLE_PREFIX . $table;
			$db->write_query("CREATE TABLE {$table} ({$column_list}) ENGINE={$db->table_type}{$collation};");
		}
	}

	/*
	 * add_tables()
	 *
	 * create multiple tables from stored info
	 *
	 * @param - $tables - (array) an associative array of database tables and their columns
	 */
	public function add_tables()
	{
		if(!is_array($this->tables) || empty($this->tables))
		{
			return false;
		}

		global $db;
		foreach($this->tables as $table => $columns)
		{
			if($db->table_exists($table))
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
	 */
	public function remove_tables()
	{
		if(!is_array($this->table_names) || empty($this->table_names))
		{
			return;
		}

		global $db;
		$drop_list = implode(', ' . TABLE_PREFIX, $this->table_names);
		$db->drop_table($drop_list);
	}

	/*
	 * add_columns()
	 *
	 * add columns in the list to a table (if they do not already exist)
	 *
	 * @param - $column_list - (array) an associative array of tables and columns
	 */
	public function add_columns()
	{
		if(!is_array($this->columns) || empty($this->columns))
		{
			return false;
		}

		global $db;
		foreach($this->columns as $table => $columns)
		{
			$sep = $added_columns = '';
			foreach($columns as $title => $definition)
			{
				if(!$db->field_exists($title, $table))
				{
					$added_columns .= "{$sep}{$title} {$definition}";
					$sep = ', ADD ';
				}
			}
			if(strlen($added_columns) > 0)
			{
				// trickery, again
				$db->add_column($table, $added_columns, '');
			}
		}
	}

	/*
	 * remove_columns()
	 *
	 * drop multiple listed columns
	 *
	 * @param - $column_list - (array) an associative array of tables and columns
	 */
	public function remove_columns()
	{
		if(!is_array($this->columns) || empty($this->columns))
		{
			return;
		}

		global $db;
		foreach($this->columns as $table => $columns)
		{
			$sep = $dropped_columns = '';
			foreach($columns as $title => $definition)
			{
				if($db->field_exists($title, $table))
				{
					$dropped_columns .= "{$sep}{$title}";
					$sep = ', DROP ';
				}
			}
			if(strlen($dropped_columns) > 0)
			{
				// tricky, tricky xD
				$result = $db->drop_column($table, $dropped_columns);
			}
		}
	}

	/*
	 * add_settinggroups()
	 *
	 * create multiple setting groups
	 *
	 * @param - $groups - (array) an associative array of setting groups
	 *
	 * return: an associative array of setting groups and gids
	 */
	private function add_settinggroups($groups)
	{
		if(!is_array($groups) || empty($groups))
		{
			return false;
		}

		global $db;
		$insert_array = $gids = array();
		foreach($groups as $name => $group)
		{
			$query = $db->simple_select('settinggroups', 'gid', "name='{$name}'");
			if($db->num_rows($query) > 0)
			{
				$group['gid'] = (int) $db->fetch_field($query, 'gid');
				$gids[$name] = $group['gid'];
				$db->update_query('settinggroups', $group, "name='{$name}'");
			}
			else
			{
				$gid = $db->insert_query('settinggroups', $group);
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
	 */
	public function add_settings()
	{
		if(!is_array($this->settings) || empty($this->settings))
		{
			return;
		}

		global $db;
		foreach($this->settings as $group => $data)
		{
			$gids = $this->add_settinggroups(array($group => $data['group']));
			$gid = $gids[$group];

			$insert_array = array();
			foreach($data['settings'] as $name => $setting)
			{
				$setting['gid'] = $gid;
				// does the setting already exist?
				$query = $db->simple_select('settings', 'sid', "name='{$name}'");
				if($db->num_rows($query) > 0)
				{
					$setting['sid'] = (int) $db->fetch_field($query, 'sid');

					// if so update the info (but leave the value alone)
					unset($setting['value']);
					$db->update_query('settings', $setting, "name='{$name}'");
				}
				else
				{
					$insert_array[] = $setting;
				}
			}
			if(!empty($insert_array))
			{
				$db->insert_query_multiple('settings', $insert_array);
			}
		}
		rebuild_settings();
	}

	/*
	 * add_templates()
	 *
	 * create multiple templates from stored info
	 *
	 * @param - $templates - (array) an associative array of template data
	 */
	public function add_templates()
	{
		if(!is_array($this->templates) || empty($this->templates))
		{
			return false;
		}

		global $db;
		$insert_arrays = array();
		foreach($this->templates as $title => $template)
		{
			$title = $db->escape_string($title);
			$template = $db->escape_string($template);
			$query = $db->simple_select('templates', 'tid', "title='{$title}'");
			if($db->num_rows($query) > 0)
			{
				$tid = (int) $db->fetch_field($query, 'tid');
				$update_array = array
				(
					"title" => $title,
					"template" => $template
				);
				$db->update_query('templates', $update_array, "tid='{$tid}'");
			}
			else
			{
				$insert_arrays[] = array
				(
					"title" => $title,
					"template" => $template,
					"sid" => -1
				);
			}
		}
		if(!empty($insert_arrays))
		{
			$db->insert_query_multiple('templates', $insert_arrays);
		}
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

		global $db;
		if($db->table_exists($table) && $db->field_exists($field, $table))
		{
			$delete_list = "'" . implode("','", $list) . "'";
			$db->delete_query($table, "{$field} IN ({$delete_list})");
		}
	}
}

?>
