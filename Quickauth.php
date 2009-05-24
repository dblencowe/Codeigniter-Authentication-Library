<?php
/* 
 * @Package Quick Authentication Library
 * @author David Blencowe
 * @link http://www.syntaxmonster.net
 * @version 1.0.0
 * @since Version 1.0.0
 */

class Quickauth
{
	var $CI;
    var $_username;

    function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('session');
		$this->CI->load->helper('url');
		$this->CI->load->database();
        $this->CI->load->helper('email');
        $this->CI->load->helper('string');
	}

	function Quickauth()
	{
         self::__construct();
	}


    /**
     * Used for restricting users to certain controllers and functions
     * by their user level.
     * @param Int $group
     * @return TRUE/Error
     */
    function restrict($restrict_to = NULL)
    {
		if($restrict_to !== NULL)
		{
            if($this->CI->session->userdata('logged_in') == TRUE)
            {
                $this->CI->db->where('name', $restrict_to);
                $query = $this->CI->db->get('groups');
                $level = $query->row_array();
                $users_level = $this->CI->session->userdata('group');

                    if($users_level >= $level['id'])
                    {
                        return TRUE;
                    }
                    else
                    {
                        show_error('You do not have sufficient privileges to access this page.');
                    }
            }
        }
        else
        {
            // Page locked to everyone
            show_error('You cannot access this page');
        }
    }

    /**
     * Check the database too see if the username that is passed to the function exists.
     * If it does then it is set to the global $_username variable for later use
     * @param String $username
     * @return TRUE/FALSE
     */

    function _username_exists($username)
    {
        $this->CI->db->where('username',  $username);
        $query = $this->CI->db->get('users');

        if($query->num_rows() <> 1)
        {
            return FALSE;
        }
        else
        {
            $this->_username = $username;
            return TRUE;
        }
    }

    /**
     * Encrypts the submitted password and then checks it in the database
     * using the value of the global $_username variable. If True is returned
     * then the username and password submitted by the user are correct and they
     * should then get logged in (See login function)
     * @param String $password
     * @return TRUE/FALSE
     */
    function _check_correct_password($str)
    {
		$this->CI->db->where('username', $this->_username);
		$query = $this->CI->db->get('users');
		$result = $query->row();

		if($result->password == $this->encrypt($str))
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
    }

    /**
     * Returns the number of characters in a string after it has been trimemd for
     * whitespace.
     * @param String $string
     * @return Int
     */
    function check_string_length($string)
    {
       $string = trim($string);
       return strlen($string);
    }
    /**
     * This function will encrypt any data passed to it.
     * It is primarily used for encrypting passwords before
     * querying the database.
     * @param String $data
     * @return String
     */
    function encrypt($data)
    {
        return sha1($data);
    }

	/**
	* Check if a user is logged in
	*
	* @access public
	* @param string
	* @return bool
	*/
	function logged_in()
	{
		return $this->CI->session->userdata('logged_in');
	}

	/**
	* Log a user out (destroy all session variables)
	*
	* @access public
	*/
	function logout()
	{
		$this->CI->session->sess_destroy();
	}

    /**
     * Function for logging users in
     * Accepts three arguements which correspond to username and password
     * submitted from form and the target to redirect too if logged
     * in succesfully
     * @param String $username
     * @param String $password
     * @param String $redirect
     */
    function login($username, $password, $redirect = NULL)
    {
        if($this->check_string_length($username) > 30)
        {
            show_error('Usernames can be no longer than 30 characters');
        }
        elseif($this->_username_exists($username) !== TRUE)
        {
            show_error('The username you provided does not exist');
        }
        elseif($this->check_string_length($password) > 32)
        {
            show_error('Passwords can be no longer than 32 chracters');
        }
        elseif($this->_check_correct_password($password) !== TRUE)
        {
            show_error('You specified an incorrect password');
        }
        else
        {
            $this->CI->db->where('username', $username);
            $query = $this->CI->db->get('users');
            $row = $query->row_array();
            $data = array(
                            'username' => $username,
                            'user_id' => $row['id'],
                            'group' => $row['groupid'],
                            'logged_in' => TRUE
                        );
            $this->CI->session->set_userdata($data);

            redirect($redirect);
        }

    }
    /**
     * Function for registering users
     * Accepts three arguements from a form {Username, Password, Email}
     * Checks for things liek confirm password should be called in
     * the calling controller although this function will sanatize data
     * for security
     *
     * @param String $username
     * @param String $password
     * @param String $email
     */

    function register($username, $password, $email)
    {
        if($this->check_string_length($username) > 30)
        {
            show_error('Usernames can be no longer than 30 characters');
        }
        elseif($this->_username_exists($username) === TRUE)
        {
            show_error('The username you provided is already in the database');
        }
        elseif($this->check_string_length($password) > 32)
        {
            show_error('Passwords can be no longer than 32 chracters');
        }
        elseif(!valid_email($email))
        {
            show_error('The email you submitted is invalid');
        }
        else
        {
            $password = $this->encrypt($password);

            $data = array(
                            "username" => $username,
                            "password" => $password,
                            "email" => $email,
                            "ip" => $this->CI->input->ip_address()
                         );

            $this->CI->db->insert('users', $data);
            show_error('You have been registered succesfully');
        }
    }

    /**
     * This function will email a user a newly generated password
     * $userdata can be a password or an email submitted from a form
     * @param String $userdata
     */
    function retrieve_password($userdata)
    {

        $email['newline'] = "\r\n";
        $this->CI->load->library('email', $email);

        if(valid_email($userdata))
        {
          $this->CI->db->where('email', $userdata);
          $new_password = random_string('alnum', 9);
          $this->CI->db->where('username', $userdata);
          $this->CI->db->update('password', $this->encrypt($new_password));
          $query = $this->CI->db->get('users');
          $result = $query->row();

          if($query->num_rows() !== 1)
          {
            show_error('This email is not registered');
          }
          else
          {
              $message = "Hey there, \r\n";
              $message .= "You or someone posing as you recently requested a new password at";
              $message .= $this->CI->config->item->base_url()."\r\n";
              $message .= "Your randomly generated password is: ".$new_password."\r\n";
              $message .= "Thanks, \r\n".$this->CI->config->item->base_url();

              $this->email->from($this->CI->config->site_email(), 'Automated Password Recovery');
              $this->email->to($result->email());

              $this->email->subject('Password Recovery');
              $this->email->message($message);

              $this->email->send();
          }
        }
        else
        {
          $new_password = random_string('alnum', 9);
          $this->CI->db->where('username', $userdata);
          $this->CI->db->update('password', $this->encrypt($new_password));
          $query = $this->CI->db->get('users');
          $result = $query->row();

          if($query->num_rows() !== 1)
          {
            show_error('This username is not registered');
          }
          else
          {     

              $message = "Hey there, \r\n";
              $message .= "You or someone posing as you recently requested a new password at";
              $message .= $this->CI->config->item->base_url()."\r\n";
              $message .= "Your randomly generated password is: ".$new_password."\r\n";
              $message .= "Thanks, \r\n".$this->CI->config->item->base_url();

              $this->email->from($this->CI->config->site_email(), 'Automated Password Recovery');
              $this->email->to($result->email());

              $this->email->subject('Password Recovery');
              $this->email->message($message);

              $this->email->send();
          }
        }
    }
}

?>
