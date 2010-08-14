<?php
/**
 * @name QuickAuth
 * @author Dave Blencowe
 * @author_url http://www.daveblencowe.com
 * @version 2.2
 * @license Free for use and modification, without credit given
 *
 * Quickauth authentication library for Codeigniter. Quickauth aims to provide
 * basic features with a minimal of front end content so that it's easy to drop
 * in to an application and customize to your needs
 */
class Quickauth
{

	var $ci;
	var $_tables = array (
		'users' => 'users',
		'groups' => 'groups',
		'group_memberships' => 'group_memberships'
	);
	var $login = "authentication/login";

	var $locale = array (
		'invalid_login_credentials' => '',
		'succesful_registration' => '',
		'logged_out' => '',
		'guest_name' => '',
		'failed_restrict' => '',
		'failed_restrict_nologin' => '',
	);

	function __construct()
	{
		$this->ci =& get_instance();
	}

	/**
	 * Log a user in using the supplied username and password combination
	 *
	 * @param <string> Supplied Username
	 * @param <string> Supplied Password
	 * @return <bool> True for a succesful login, False for no login + error
	 */
	function login($username, $password)
	{
		$this->ci->db->where('username', $username);
		$this->ci->db->where('password', $this->encrypt($password));
		$q = $this->ci->db->get($this->_tables['users']);
		if ($q->num_rows() > 0) {
			$a = $q->row_array();
			$session_data = array (
				'userid' => $a['id']
			);

			$this->ci->session->set_userdata($session_data);
			return true;
		} else {
			ui_set_error($this->locale['invalid_login_credentials']);
			return false;
		}
	}

	/**
	 * Register a user. Data should be supplied in an array format using the
	 * structure specified below
	 *
	 * @param <array> Array, structured as follows: array('username', 'password'
	 * , 'firstname', 'lastname');
	 * @return <bool> true
	 */
	function register($data)
	{
		$data['password'] = $this->encrypt($data['password']);
		$type = $data['type'];
		unset($data['type']);

		$this->ci->db->insert($this->_tables['users'], $data);
		$id = $this->ci->db->insert_id();

		foreach ($type as $var) {
			$array = array (
				'userid' => $id,
				'groupid' => $var
			);

			$this->ci->db->insert($this->_tables['group_memberships'], $array);
		}

		ui_set_message($this->locale['succesful_registration']);
		return true;
	}

	/**
	 * Log a user out by destroying their session, then set a ui message and
	 * return
	 *
	 * @return <bool> True, to symbolise a succesful logout. Plus ui_set_message
	 */
	function logout()
	{
		$this->ci->session->destroy();
		ui_set_message($this->locale['logged_out']);
		return true;
	}

	/*
     	* @to-do: Build a password recovery function for next version, using CI
     	* Email library against a global config
    	*/
	function recover_password($user)
	{

	}

	/**
	 * Get the data on a user from the user table. Also parse their full name in
	 * to $data['name'] for convinience
	 *
	 * @param <int>   The individual users id. If blank will be for current user
	 * @return <array> Data for the user, or guest if not logged in
	 */
	function user($id = null)
	{
		if ($id == null) $id = $this->ci->session->userdata('userid');

		// If the user is not signed in then assign them guest credentials and
		// return
		if (!$this->logged_in()) {
			$data->username = "guest";
			$data->name = $this->locale['guest_name'];
			return $data;
		}

		// Get the specified users credentials from the users table and return
		// them
		$this->ci->db->where('id', $id);
		$q = $this->ci->db->get($this->_tables['users']);
		$data = $q->row();
		$data->name = $data->firstname." ".$data->lastname;

		return $data;
	}

	/**
	 * Check to see if a user is logged in. If not then don't return anything
	 *
	 * @return <bool> Return True if user is logged in, else return nothing
	 */
	function logged_in()
	{
		$id = $this->ci->session->userdata('userid');
		if ($id) {
			return true;
		}
	}

	/**
	 * Restrict a controller to a user group, logged in users, or exclude the
	 * function from an existing restriction
	 *
	 * @param <String> The name of a group from the group table, E.g: "admin"
	 * @return Returns no usable values but uses ui_set_error on failed auth
	 */
	function restrict($group = null)
	{
		/* if the argument value is false the page should not be restricted,
	 	* Useful for excluding functions from controllers restricted at a construct
	 	* level
		*/
		if ($group == "false") return;

		/* Anything past here requires at least some form of login so redirect
	 	* if user is not logged in. If $group is null will only allow logged
	 	* in users to access the page
		*/
		if (!$this->logged_in()) {
			ui_set_error($this->locale['failed_restrict_nologin']);
			redirect($this->login);
		}

		$userid = $this->ci->session->userdata('userid');
		$ci->db->where('userid', $id);
		$q = $ci->db->get($this->_tables['group_memberships']);
		$groups = $q->result_array();
		foreach ($groups as $grp) {
			$ci->db->where('id', $grp['groupid']);
			$q = $ci->db->get($this->_tables['groups']);
			$var = $q->row_array();
			$user_groups[] = $var['name'];
		}

		if (!in_array($group, $user_groups)) {
			ui_set_error($this->locale['failed_restrict']);
			die();
		}

		return;
	}

	/**
	 * Encrypt a string (usually a password) ready for use within the library
	 * Uses SHA1 encryption against the Codeigniter encryption key
	 *
	 * @param <string> The string to be encrypted
	 * @return <string> The encrypted hash
	 */
	function encrypt($string)
	{
		if (empty ($this->ci->config->item('encryption_key'))) show_error('You must set the encryption key in your config file for Quickauth to function');
		$string = sha1($string.$this->ci->config->item('encryption_key'));
		return $string;
	}

	/**
	 * Return an array of groups that a user is a member of
	 *
	 * @param <int> A valid UserId
	 * @return <array> A list of group names
	 */
	function get_groups($id)
	{
		$this->ci->db->where('userid', $id);
		$q = $this->ci->db->get($this->_tables['group_memberships']);
		$rst = $q->result_array();
		$groups = array();
		foreach ($rst as $k=>$v) {
			$this->ci->db->where('id', $v['groupid']);
			$q = $this->ci->db->get($this->_tables['groups']);
			$r = $q->row_array();
			$groups[] = $r['title'];
		}
		return $groups;
	}

	/**
	 * Add a group to the database
	 *
	 * @param <string> Group Name
	 * @return <int> Group ID
	 */
	function create_group($title)
	{
		$data['title'] = $title;
		if (!$this->group_exists($title)) {
			$this->ci->db->insert($this->_tables['groups'], $data);
			return $this->ci->db->insert_id();
		} else {
			return $this->get_group_id($title);
		}
		
	}

	/**
	 * Check if a group exists in the system
	 *
	 * @param <string> Group name
	 * @return <bool> Will return true if the group exists
	 */
	function group_exists($title)
	{
		$this->ci->db->where('title', $title);
		$q = $this->ci->db->get($this->_tables['groups']);
		if ($q->num_rows() > 0) {
			return true;
		}
	}

	/**
	 * Get the unique identifier for a group
	 *
	 * @param <string> Group name
	 * @return <int> Group ID
	 */
	function get_group_id($title)
	{
		$this->ci->db->where('title', $title);
		$qry = $this->ci->db->get($this->_tables['groups']);
		return $qry->row()->id;
	}
}
