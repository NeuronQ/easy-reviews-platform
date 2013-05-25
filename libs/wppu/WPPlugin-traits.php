<?php
/**
 * Traits for augmenting WPPlugin (for older PHP versions just turn them
 * into abstract classes and inherit from them pretending you're doing
 * mixins or multiple inheritance... same effect)
 */

/**
 * DAL functions (sugar over $wpdb)
 */
trait DAL
{
	public function db_get($what, $id, $output_type = OBJECT)
	{
		global $wpdb;

		$r = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$this->table_prefix}$what WHERE id = %d",
			$id), $output_type);

		if (!$r) {
			$this->log("ERROR getting $what with id $id from db");
			$this->log_output('$wpdb->print_error()', get_defined_vars());
		}

		return $r;
	}

	public function db_get_all($what, $output_type = OBJECT)
	{
		global $wpdb;

		$r = $wpdb->get_results("SELECT * FROM {$this->table_prefix}$what", $output_type);

		if (!$r) {
			$this->log("ERROR getting $what entities from db");
			$this->log_output('$wpdb->print_error()', get_defined_vars());
		}

		return $r;
	}

	public function db_new($what, $data)
	{
		global $wpdb;

		$r = $wpdb->insert($this->table_prefix . $what, $data);

		if (!$r) {
			$this->log("ERROR saving to db $what with data: " . var_export($data, true));
			$this->log_output('$wpdb->print_error()', get_defined_vars());
		}

		return $wpdb->insert_id;
	}

	public function db_update($what, $data, $id)
	{
		global $wpdb;

		$r = $wpdb->update($this->table_prefix . $what, $data, array('id' => $id));

		if (!$r) {
			$this->log("ERROR updating in db $what $id with data: " . var_export($data, true));
			$this->log_output('$wpdb->print_error()', get_defined_vars());
		}

		return $r;
	}

	public function db_delete($what, $id)
	{
		global $wpdb;

		$r = $wpdb->query($wpdb->prepare(
			"DELETE FROM {$this->table_prefix}$what WHERE id = %d",
			$id));

		if (!$r) {
			$this->log("ERROR deleting from db $what $id");
			$this->log_output('$wpdb->print_error()', get_defined_vars());
		}

		return $r;
	}
}