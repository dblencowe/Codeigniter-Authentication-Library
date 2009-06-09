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
    var $_table = array(
                    'users' => 'users',
                    'groups' => 'groups'
                    );

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
    * @param String $restrict_to Name of the group
    * @return TRUE/Error
    */
    function restrict( $restrict_to = NULL, $redirect_to_login = FALSE )
    {
        if ( $restrict_to !== NULL)
        {
            if ($this->CI->session->userdata('logged_in') == TRUE)
            {
                $this->CI->db->where('name', $restrict_to);
                $query = $this->CI->db->get($this->_table['groups']);
                $level = $query->row_array();
                $users_level = $this->CI->session->userdata('group_id');

                if ($users_level >= $level['id'])
                {
                    return TRUE;
                }
                else
                {
                    show_error('You do not have sufficient privileges to access this page. <a href="javascript:history.back();">back</a>');
                }
            }
            else
            {
                if ($redirect_to_login == FALSE)
                {
                    show_error('You are not logged in. <a href="javascript:history.back();">back</a>');
                }
                else
                {
                    redirect($redirect_to_login);
                }
            }
        }
        else
        {
            // Page locked to everyone
            show_error('You cannot access this page. <a href="javascript:history.back();">back</a>');
        }
    }

    /**
    * Check the database too see if the username that is passed to the function exists.
    * If it does then it is set to the global $_username variable for later use
    * @param String $username
    * @return TRUE/FALSE
    */
    function _username_exists( $username )
    {
        $this->CI->db->where('username', $username);
        $this->CI->db->limit(1);
        $query = $this->CI->db->get($this->_table['users']);

        if ($query->num_rows() !== 1)
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
    function _check_correct_password( $password )
    {
        $this->CI->db->select('password');
        $this->CI->db->where('username', $this->_username);
        $this->CI->db->limit(1);
        $query = $this->CI->db->get($this->_table['users']);
        $result = $query->row();

        if ($result->password == $this->encrypt($password))
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
    function check_string_length( $string )
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
    function encrypt( $data )
    {
        if ($this->CI->config->item('encryption_key') !== NULL)
        {
            return sha1($this->CI->config->item('encryption_key').$data);
        }
        else
        {
            show_error('Please set an encryption key in your config file. <a href="javascript:history.back();">back</a>');
        }
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
    function login( $username, $password, $redirect = NULL )
    {
        if ($this->check_string_length($username) > 30)
        {
            show_error('Usernames can be no longer than 30 characters. <a href="javascript:history.back();">back</a>');
        }
        elseif ($this->_username_exists($username) !== TRUE)
        {
            show_error('The username you provided does not exist. <a href="javascript:history.back();">back</a>');
        }
        elseif ($this->check_string_length($password) > 32)
        {
            show_error('Passwords can be no longer than 32 chracters. <a href="javascript:history.back();">back</a>');
        }
        elseif ($this->_check_correct_password($password) !== TRUE)
        {
            show_error('You specified an incorrect password. <a href="javascript:history.back();">back</a>');
        }
        else
        {
            $this->CI->db->where('username', $username);
            $query = $this->CI->db->get($this->_table['users']);
            $row = $query->row_array();
            if ($row['activated'] === 0)
            {
                show_error('This account has not been activated yet. <a href="javascript:history.back();">back</a>');
            }
            else
            {
                $data = array(
                                'username' => $username,
                                'user_id' => $row['id'],
                                'group_id' => $row['group_id'],
                                'logged_in' => TRUE
                              );
                $this->CI->session->set_userdata($data);

                if ($redirect !== NULL )
                {
                    redirect($redirect);
                }
            }
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
    function register( $username, $password, $email )
    {
        if ($this->check_string_length($username) > 30)
        {
            show_error('Usernames can be no longer than 30 characters. <a href="javascript:history.back();">back</a>');
        }
        elseif ($this->_username_exists($username) === TRUE)
        {
            show_error('The username you provided is already in the database. <a href="javascript:history.back();">back</a>');
        }
        elseif($this->check_string_length($password) > 32)
        {
        show_error('Passwords can be no longer than 32 chracters. <a href="javascript:history.back();">back</a>');
        }
        elseif (!valid_email($email))
        {
            show_error('The email you submitted is invalid. <a href="javascript:history.back();">back</a>');
        }
        else
        {
            $password = $this->encrypt($password);

            $data = array(
                            "id" => '',
                            "username" => $username,
                            "password" => $password,
                            "email" => $email,
                            //"ip" => $this->CI->input->ip_address()
                            );

            $this->CI->db->insert($this->_table['users'], $data);
            return TRUE;
        }
    }

    /**
     * This function is for activating a user account.
     * You must supply a valid username from the database.
     * @param String $username
     */
    function activate_user( $username )
    {
        if ($username !== NULL)
        {
            $this->CI->db->set( 'activated', 1 );
            $this->CI->db->where('username', $username);
            $this->CI->db->update($this->_table['users']);
            return TRUE;
        }
        else
        {
            return 0;
        }
    }

    /**
    * This function will email a user a newly generated password
    * $userdata can be a password or an email submitted from a form
    * @param String $userdata
    */
    function retrieve_password( $userdata )
    {
        $email['newline'] = "\r\n";
        $this->CI->load->library('email', $email);

        if (valid_email($userdata))
        {
            $this->CI->db->where('email', $userdata);
            $this->CI->db->limit(1);
            $query = $this->CI->db->get($this->_table['users']);
            $result = $query->row();

            if ($query->num_rows() !== 1)
            {
                show_error('This email is not registered. <a href="javascript:history.back();">back</a>');
            }
            else
            {
                $new_password = random_string('alnum', 9);
                $this->CI->db->where('email', $userdata);
                $this->CI->db->set('password', $this->encrypt($new_password));
                $this->CI->db->update($table['users']);

                $message = "Hey there, \r\n";
                $message .= "You or someone posing as you recently requested a new password at";
                $message .= base_url()."\r\n";
                $message .= "Your randomly generated password is: ".$new_password."\r\n";
                $message .= "Thanks, \r\n".base_url();

                $this->email->from($this->CI->config->site_email(), 'Automated Password Recovery');
                $this->email->to($result->email);

                $this->email->subject('Password Recovery');
                $this->email->message($message);

                $this->email->send();
            }
        }
        else
        {
            $this->CI->db->where('username', $userdata);
            $this->CI->db->limit(1);
            $query = $this->CI->db->get($this->_table['users']);
            $result = $query->row();

            if ($query->num_rows() !== 1)
            {
                show_error('This username is not registered. <a href="javascript:history.back();">back</a>');
            }
            else
            {

                $new_password = random_string('alnum', 9);
                $this->CI->db->where('username', $userdata);
                $this->CI->db->set('password', $this->encrypt($new_password));
                $this->CI->db->update($table['users']);

                $message = "Hey there, \r\n";
                $message .= "You or someone posing as you recently requested a new password at";
                $message .= base_url()."\r\n";
                $message .= "Your randomly generated password is: ".$new_password."\r\n";
                $message .= "Thanks, \r\n".base_url();

                $this->email->from($this->CI->config->site_email(), 'Automated Password Recovery');
                $this->email->to($result->email);

                $this->email->subject('Password Recovery');
                $this->email->message($message);

                $this->email->send();
            }
        }
    }
}

/* End of file Quickauth.php */
/* Location: ./application/libraries/Quickauth.php */