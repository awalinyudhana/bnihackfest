<?php

require APPPATH . '/libraries/REST_Controller.php';

/**
 * Class Agent
 */
class Agent extends REST_Controller
{
    /**
     * Agent constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function pickup_lists_get()
    {
        $items = $this->db->get_where('users', ['alert' => true])->result_array();

        $this->set_response($items, REST_Controller::HTTP_OK);
    }

    public function deposit()
    {
        $id = $this->input->post('id');
        $amount = $this->input->post('amount');

        $user = $this->db->get_where('users', ['user_id' => $id])->row_array();

        $balance = $amount + $user['balance'];

        $this->db->update('users', ['balance' => $balance], ['user_id' => $id]);

        $return = [
            'status' => true,
            'balance' => $balance
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);

    }


}