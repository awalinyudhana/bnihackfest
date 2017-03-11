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

    public function login_post()
    {
        $this->form_validation->set_rules('phone', 'No Telepon', 'trim|required|is_natural');
        $this->form_validation->set_rules('pin', 'PIN', 'required');

        if ($this->form_validation->run() == FALSE)
            $this->set_response(
                [
                    'status' => false,
                    'message' => validation_errors()
                ],
                REST_Controller::HTTP_BAD_REQUEST
            );

        $phone = $this->input->post('phone');
        $pin = $this->input->post('pin');

        $data = $this->db->get_where('agents', [
            'phone' => $phone,
            'pin' => $pin
        ])->row();


        $return =[];
        if (is_null($data))
        {
            $return = [
                'status' => false,
                'message' => 'Nomor Telepon atau PIN salah'
            ];
        }
        else
        {
            $return = [
                'status' => true,
                'data' => $data
            ];
        }


        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function by_id_get($id)
    {
        $user = $this->db->get_where('agents', ['agent_id' => $id])->row_array();
        $user['profile_path'] = site_url('uploads/'. $user['profile']);

        $return = [
            'status' => true,
            'data' => $user
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);
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

        $agent = $this->db->get_where('agents', ['agent_id' => $id])->row_array();

        $balance = $amount + $agent['balance'];

        $this->db->update('agents', ['balance' => $balance], ['agent_id' => $id]);

        $return = [
            'status' => true,
            'balance' => $balance
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);

    }

    public function transfer_post()
    {

    }


}