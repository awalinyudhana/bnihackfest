<?php

require APPPATH . '/libraries/REST_Controller.php';

/**
 * Class Users
 */
class Users extends REST_Controller
{
    /**
     * Users constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function by_id_get()
    {
        $user = $this->db->get_where('users', ['user_id' => 1])->row_array();
        $user['profile_path'] = site_url('uploads/'. $user['profile']);

        $this->set_response($user, REST_Controller::HTTP_OK);

    }
}