<?php

use PDO;

class Option {

	static private $table_name = 'options';
	static private $table = '';

	static protected $pdo = null;

	/**
	 * options
	 *
	 * (default value: array())
	 *
	 * Stores all options from database for easy fetching
	 * without preforming a query each time, improving performance
	 *
	 * @var array
	 * @access private
	 */
	static $options = array();

	static function init()
	{

		$table_prefix = ''; // table prefix

		// Make table name
		static::$table = '`'.$table_prefix.static::$table_name.'`';

		// Load pdo connection
		static::$pdo = ''; // PDO connection

		// Preload all options
		static::$options = static::fetch_all();

	}

	static function fetch_all() {

		$output = array();

		// Fetch all options
		$all_options = static::$pdo->query('SELECT * FROM '.static::$table.' ORDER BY `lang_code` DESC')->fetchAll(PDO::FETCH_ASSOC);

		// Organize all options into array
		foreach ($all_options as $row) {
			$value_json = json_decode($row['value'], true);
			$row['value'] = (is_array($value_json)) ? $value_json : $row['value'];
			$option_lang = (!empty($row['lang_code'])) ? $row['lang_code'] : 'all';
			$output[$row['key']][$option_lang] = $row;
		}

		return $output;

	}

	static function get_full($key = null, $default = null, $lang = null) {

		if (!$key) return $default;

		// Fetch possible options
		// Fetch as array
		$options = (!empty(static::$options[$key])) ? static::$options[$key] : array();

		// Prepare data output
		$data = array(
			'id' => null,
			'key' => $key,
			'value' => (is_array($default)) ? json_encode($default) : $default,
			'lang_code' => null
		);

		// Fetch current option in array
		$current_option = current($options);

		// Does the current options belongs to a language or is a query with language
		$is_lang_option = !empty($current_option['lang_code']) || $lang;

		// Language to return
		$return_lang = ($lang) ? $lang : ''; // core()->curr_lang

		// Loop through options and find the corresponding to the lang we want
		if ($is_lang_option) {
			$data['lang_code'] = $return_lang;

			// Set current as null
			// If an option does not exist for a
			// specific language, will return null correctly
			$current_option = null;

			foreach ($options as $row) {

				// If current option is the one we look for, set it as current and exit loop
				if ($row['lang_code'] === $return_lang) {
					$current_option = $row;
					break;
				}
			}
		}

		// If an an option exists and has an id, store it into $data for output
		if (isset($current_option['id']))
			$data['id'] = $current_option['id'];

		// If an an option exists and has an value, store it into $data for output
		if (isset($current_option['value']))
			$data['value'] = $current_option['value'];

		// If an option is a defined constant and is not language based, make it the value
		if (defined($key) && !$is_lang_option)
			$data['value'] = constant($key);

		// Check if option value is json
		$option_json = (!is_array($data['value'])) ? json_decode($data['value'], true) : $data['value'];

		// If option is json, replace the value with the decoded data
		if ($option_json !== null)
			$data['value'] = $option_json;

		// Output option data
		return $data;

	}


	static function get($key = null, $default = null, $lang = null)
	{

		$segments = is_array($key) ? $key : explode('.', $key);

		// Fetch full option
		$option = static::get_full(array_shift($segments), $default, $lang);

		$value = $option['value'];

		if (count($segments) < 1)
			return $value;

		foreach ($segments as $segment)
		{
			if ( ! is_array($value) or ! array_key_exists($segment, $value))
			{
				return $default;
			}

			$value = $value[$segment];
		}

		// Return option value
		return $value;
	}


	static function set($key, $value, $lang = null)
	{
		// Fetch full option
		return static::update($key, $value, $lang);
	}


	static function update($key, $value, $lang = null)
	{

		$segments = is_array($key) ? $key : explode('.', $key);

		// Fetch option
		$current_option = static::get_full(array_shift($segments), null, $lang);

		// remove empty array entries
		if (is_array($value)) {
			$value = array_filter($value, function($v) {
				return $v !== "";
			});
		}

		// Pre populate data from fetched option
		$data = $current_option;

		// Static option lang
		$option_lang = (!empty($data['lang_code'])) ? $data['lang_code'] : 'all';

		// Grab value as reference for further manipulaiton
		$current_value = &$current_option['value'];

		$segment_count = 0;
		$segment_total = count($segments);

		// If there are multiple segments, set/update specified
		if (!empty($segments)) {

			// Will manipulate $current_option['value'] due to reference assignment to $current_value
			foreach ($segments as $segment)
			{
				++$segment_count;

				// If current value is not array, set it as such
				if ( ! is_array($current_value) )
				{
					$current_value = array();
				}
			
				// If the intended key is not set, create and assign value
				if ( ! array_key_exists($segment, $current_value))
				{
					$current_value = array_merge($current_value, array($segment => $value));
				}
				// If the key already exits, check if is last segment and set value
				else if ( $segment_count == $segment_total )
				{
					$current_value[$segment] = $value;
				}
				
				// Narrow result array to the next iteration
				$current_value = &$current_value[$segment];
			}

			$data['value'] = $current_option['value'];

		}
		// If the key is shallow, update whole value
		else
		{
			$data['value'] = $value;
		}

		// Encode value as json for db insertion
		if (is_array($data['value']))
			$data['value'] = json_encode($data['value']);

		// If this is a lang option update, store the lang in $data
		if ($lang)
			$data['lang_code'] = $lang;

		// If option has id/exists, update it
		if (!empty($data['id'])) {

			$statement_data = array(
				'id' => $data['id'],
				'value' => $data['value'],
			);
			$options_statement = 'UPDATE '.static::$table.' SET `value` = :value WHERE `id` = :id';

		}
		// If option does not exist and is a lang option, create
		elseif (empty($data['id']) && !empty($data['lang_code'])) {

			$statement_data = array(
				'key' => $data['key'],
				'value' => $data['value'],
				'lang_code' => $data['lang_code'],
			);
			$options_statement = 'INSERT INTO '.static::$table.' (`key`, `value`, `lang_code`) VALUES (:key, :value, :lang_code)';

			// Update static option
			static::$options[$data['key']][$option_lang] = $data;

		}
		// If option does not exist, create
		else {

			$statement_data = array(
				'key' => $data['key'],
				'value' => $data['value'],
			);
			$options_statement = 'INSERT INTO '.static::$table.' (`key`, `value`) VALUES (:key, :value)';

		}

		// Prepare statement
		$do_option = static::$pdo->prepare($options_statement);

		// Execute statement
		$result = $do_option->execute($statement_data);

		// Store new id if insert
		if (empty($data['id']))
			$data['id'] = static::$pdo->lastInsertId();

		// Update static option
		static::$options[$data['key']][$option_lang] = $data;

		// Execute
		return $result;
	}


	public function delete($key, $lang = null)
	{

	}


}
