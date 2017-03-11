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

    public function pickup_lists_get($id)
    {
        $this->db->from('pickups p');
        $this->db->join('users u', 'u.user_id = p.user_id');
        $this->db->where('agent_id', $id);
        $items = $this->db->get()->result_array();

        $return = [
            'status' => true,
            'data' => $items
        ];

        $this->set_response($return, REST_Controller::HTTP_OK);
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

    public function withdrawal_pending_lists_get()
    {
        $data = $this->db->get_where('withdrawals', ['status' => false])->result_array();
        $return = [
            'status' => true,
            'data' => $data
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function withdrawal_done_lists_get()
    {
        $data = $this->db->get_where('withdrawals', ['status' => true])->result_array();
        $return = [
            'status' => true,
            'data' => $data
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function withdrawal_post()
    {
        $this->form_validation->set_rules('pin', 'PIN', 'required');

        if ($this->form_validation->run() == FALSE)
            $this->set_response(
                [
                    'status' => false,
                    'message' => validation_errors()
                ],
                REST_Controller::HTTP_BAD_REQUEST
            );


        $id = $this->input->post('id');
        $pin = $this->input->post('pin');

        $withdrawal = $this->db->get_where('withdrawals', [
            'withdrawal_id' => $id
        ])->row();

        if (is_null($withdrawal))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'data withdrawal tidak ditemukan'
                ],
                REST_Controller::HTTP_OK
            );

        $user = $this->db->get_where('users', [
            'user_id' => $withdrawal->user_id,
            'pin' => $pin
        ])->row();

        if (is_null($user))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'PIN salah'
                ],
                REST_Controller::HTTP_OK
            );

        $agent = $this->db->get_where('agents', [
            'agent_id' => $withdrawal->agent_id
        ])->row();

        if ((int) $agent->balance < (int) $withdrawal->amount)
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'Balance tidak cukup'
                ],
                REST_Controller::HTTP_OK
            );

        $this->db->update('withdrawals', ['status'=> true, 'commission' => 1000], ['withdrawal_id' => $id]);

        $user_new_amount = (int) $user->balance - (int) $withdrawal->amount;
        $agent_new_amount = (int) $agent->balance + (int) $withdrawal->amount + 1000;
        $this->db->update('users', ['balance' => (int) $user_new_amount], ['user_id' => $user->user_id]);
        $this->db->update('agents', ['balance' => $agent_new_amount], ['agent_id' => $agent->agent_id]);

        $this->set_response(
            [
                'status' => true,
                'balance' => $agent_new_amount,
                'commission' => 1000
            ],
            REST_Controller::HTTP_OK
        );
    }

    public function collect_post()
    {
        $this->form_validation->set_rules('pin', 'PIN', 'required');
        $this->form_validation->set_rules('phone', 'No Telepon', 'trim|required|is_natural');
        $this->form_validation->set_rules('trash_id', 'Sampah', 'trim|required|integer');
        $this->form_validation->set_rules('agent_id', 'Agent', 'trim|required|integer');
        $this->form_validation->set_rules('weight', 'Agent', 'trim|required|numeric');

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
        $trash_id = $this->input->post('trash_id');
        $weight = $this->input->post('weight');
        $agent_id = $this->input->post('agent_id');

        $user = $this->db->get_where('users', [
            'phone' => $phone,
            'pin' => $pin
        ])->row();

        if (is_null($user))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'PIN salah'
                ],
                REST_Controller::HTTP_OK
            );

        $trash = $this->db->get_where('trash', [
            'trash_id' => $trash_id,
        ])->row();

        if (is_null($trash))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'data sampah tidak ditemukan'
                ],
                REST_Controller::HTTP_OK
            );

        $price = (int) $trash->price;
        $weight = (float) $weight;
        $total = $price * $weight;

        $commission = 0.08 * $total;

        $point = floor($total/1000);

        $biaya = 0.02 * $total;

        $agent = $this->db->get_where('agents', [
            'agent_id' => $agent_id
        ])->row();

        if (is_null($user))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'Agent tidak ditemukan'
                ],
                REST_Controller::HTTP_OK
            );

        if ((int) $agent->balance < (int) $total)
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'Balance tidak cukup'
                ],
                REST_Controller::HTTP_OK
            );
        $cost = (int) ($commission + $biaya);
        $transfer_balance = (int) $total - $cost;

        $user_new_amount = (int) $user->balance + $transfer_balance;

        $user_new_point = (int) $user->point + (int) $point;

        $agent_new_amount = (int) $agent->balance - ($total - $commission);
        $this->db->update('users', ['balance' => (int) $user_new_amount, 'point' => $user_new_point],
            ['user_id' => $user->user_id]);
        $this->db->update('agents', ['balance' => $agent_new_amount], ['agent_id' => $agent->agent_id]);

        $data = [
            'user_id' => $user->user_id,
            'agent_id' => $agent_id,
            'trash_id' => $trash_id,
            'price' => $price,
            'weight' => $weight,
            'total' => $total,
            'commission' => $commission,
            'point' => $point,
        ];

        $this->db->insert('collects', $data);

        $this->set_response(
            [
                'status' => true,
                'balance' => $agent_new_amount,
                'trash_price' => $price,
                'trash_weight' => $weight,
                'trash_value' => $total,
                'cost' => $cost,
                'transfered_balance' => $transfer_balance
            ],
            REST_Controller::HTTP_OK
        );
    }

    public function collect_history_get($id)
    {
        $this->db->from('collects c');
        $this->db->join('users u', 'u.user_id = c.user_id');
        $this->db->where('agent_id', $id);
        $items = $this->db->get()->result_array();

        $return = [
            'status' => true,
            'data' => $items
        ];

        $this->set_response($return, REST_Controller::HTTP_OK);
    }


}